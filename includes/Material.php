<?php
/**
 * Material de las lecciones: servido solo a quien tiene acceso.
 *
 * Los adjuntos no se enlazan por la URL de `uploads/`: se sirven por un endpoint que comprueba la matrícula
 * en cada petición. Para que la URL directa tampoco valga, el fichero se mueve a una carpeta que nginx
 * bloquea (`uploads/convoca-privado/`).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protección del material del curso.
 */
class Material {

	/**
	 * Carpeta, dentro de uploads, que nginx no sirve.
	 */
	public const CARPETA = 'convoca-privado';

	/**
	 * Meta del curso: su material es público aunque el curso tenga precio.
	 */
	public const META_PUBLICO = '_convoca_material_publico';

	/**
	 * Engancha el endpoint y la migración.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * Regla del endpoint.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		add_rewrite_rule(
			'^convoca-material/(\d+)/(\d+)/?$',
			'index.php?convoca_material_adjunto=$matches[1]&convoca_material_leccion=$matches[2]',
			'top'
		);
	}

	/**
	 * Variables de la URL.
	 *
	 * @param array<int, string> $vars Variables.
	 * @return array<int, string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'convoca_material_adjunto';
		$vars[] = 'convoca_material_leccion';

		return $vars;
	}

	/**
	 * Regenera las reglas una vez por versión.
	 *
	 * @return void
	 */
	public static function maybe_flush(): void {
		$guardada = (string) get_option( 'convoca_courses_material_rewrite', '' );

		if ( defined( 'CONVOCA_COURSES_VERSION' ) && CONVOCA_COURSES_VERSION === $guardada ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'convoca_courses_material_rewrite', defined( 'CONVOCA_COURSES_VERSION' ) ? CONVOCA_COURSES_VERSION : '1' );
	}

	/**
	 * ¿El material de este curso es privado?
	 *
	 * Por defecto es privado cuando el curso se vende y público cuando es gratuito; el curso puede forzar
	 * lo contrario con la opción `_convoca_material_publico`.
	 *
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function privado( int $course_id ): bool {
		if ( $course_id <= 0 ) {
			return true;
		}

		$opcion = (string) get_post_meta( $course_id, self::META_PUBLICO, true );
		if ( '1' === $opcion ) {
			return false;
		}
		if ( '0' === $opcion ) {
			return true;
		}

		$precio = Pricing::for_user( $course_id, 0 );

		return (float) $precio['precio'] > 0;
	}

	/**
	 * URL del material de una lección.
	 *
	 * @param int $attachment_id Adjunto.
	 * @param int $lesson_id     Lección.
	 * @return string
	 */
	public static function url( int $attachment_id, int $lesson_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		return home_url( user_trailingslashit( 'convoca-material/' . $attachment_id . '/' . $lesson_id ) );
	}

	/**
	 * Sirve el fichero si hay permiso.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		$adjunto = (int) get_query_var( 'convoca_material_adjunto' );

		if ( $adjunto <= 0 ) {
			return;
		}

		$leccion = (int) get_query_var( 'convoca_material_leccion' );
		$curso   = $leccion > 0 ? Hierarchy::course_of_lesson( $leccion ) : 0;

		if ( self::privado( $curso ) && ! Access::can_view_lesson( get_current_user_id(), $leccion ) ) {
			status_header( 403 );
			wp_die(
				esc_html__( 'Este material es para el alumnado matriculado.', 'convoca-courses' ),
				esc_html__( 'Acceso restringido', 'convoca-courses' ),
				array( 'response' => 403 )
			);
		}

		$ruta = get_attached_file( $adjunto );
		if ( ! is_string( $ruta ) || '' === $ruta || ! file_exists( $ruta ) ) {
			status_header( 404 );
			wp_die(
				esc_html__( 'El fichero no está disponible.', 'convoca-courses' ),
				esc_html__( 'No encontrado', 'convoca-courses' ),
				array( 'response' => 404 )
			);
		}

		nocache_headers();
		header( 'Content-Type: ' . (string) get_post_mime_type( $adjunto ) );
		header( 'Content-Length: ' . (string) filesize( $ruta ) );
		header( 'Content-Disposition: attachment; filename="' . basename( $ruta ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- servimos el fichero protegido.
		readfile( $ruta );
		exit;
	}

	/**
	 * Mueve un adjunto a la carpeta privada, de forma idempotente.
	 *
	 * @param int $attachment_id Adjunto.
	 * @return bool
	 */
	public static function mover_a_privado( int $attachment_id ): bool {
		$ruta = get_attached_file( $attachment_id );

		if ( ! is_string( $ruta ) || '' === $ruta || ! file_exists( $ruta ) ) {
			return false;
		}

		$subidas = wp_get_upload_dir();
		$destino = trailingslashit( $subidas['basedir'] ) . self::CARPETA;
		$nombre  = basename( $ruta );

		if ( dirname( $ruta ) === $destino ) {
			return true;
		}

		if ( ! wp_mkdir_p( $destino ) ) {
			return false;
		}

		$nuevo = trailingslashit( $destino ) . $nombre;
		if ( file_exists( $nuevo ) ) {
			$nuevo = trailingslashit( $destino ) . wp_unique_filename( $destino, $nombre );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- mover dentro de uploads.
		if ( ! @rename( $ruta, $nuevo ) ) {
			return false;
		}

		update_attached_file( $attachment_id, $nuevo );

		return true;
	}
}
