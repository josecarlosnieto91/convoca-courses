<?php
/**
 * Vínculo entre una lección y la página que la publica, y URL propia de lección.
 *
 * El vínculo se anota al guardar la página (metadato `_convoca_pagina_id` en la lección) y la URL propia
 * sale del rewrite `/cursos/<curso>/leccion/<slug>/`. La página queda como respaldo para el contenido
 * anterior a este cambio.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enlaces de lección por metadato y por rewrite.
 */
class Lesson_Links {

	/**
	 * Metadato en la lección con el id de la página que la publica.
	 */
	public const META_PAGE = '_convoca_pagina_id';

	/**
	 * Metadato en la página con las lecciones que contiene, para poder limpiar.
	 */
	public const META_LIST = '_convoca_lecciones';

	/**
	 * Mapa lección → página leído de postmeta.
	 *
	 * @var array<int, int>|null
	 */
	private static ?array $mapa_meta = null;

	/**
	 * Engancha guardado y URLs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'save_post', array( __CLASS__, 'sync_page' ), 20, 1 );
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
		// Las reglas guardadas en la base de datos no se regeneran al actualizar ficheros: sin esto, la URL
		// propia responde 404 hasta que alguien guarde los permalinks a mano.
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * Regenera las reglas una vez por versión.
	 *
	 * @return void
	 */
	public static function maybe_flush(): void {
		$guardada = (string) get_option( 'convoca_courses_rewrite_version', '' );

		if ( defined( 'CONVOCA_COURSES_VERSION' ) && CONVOCA_COURSES_VERSION === $guardada ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'convoca_courses_rewrite_version', defined( 'CONVOCA_COURSES_VERSION' ) ? CONVOCA_COURSES_VERSION : '1' );
	}

	/**
	 * Regla de URL propia de la lección.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		add_rewrite_rule(
			'^cursos/([^/]+)/leccion/([^/]+)/?$',
			'index.php?convoca_curso_slug=$matches[1]&convoca_leccion_slug=$matches[2]',
			'top'
		);
	}

	/**
	 * Variables de la URL propia.
	 *
	 * @param array<int, string> $vars Variables públicas.
	 * @return array<int, string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'convoca_curso_slug';
		$vars[] = 'convoca_leccion_slug';

		return $vars;
	}

	/**
	 * Sincroniza los metadatos de vínculo al guardar una página.
	 *
	 * @param int $page_id Página guardada.
	 * @return void
	 */
	public static function sync_page( int $page_id ): void {
		if ( wp_is_post_revision( $page_id ) || wp_is_post_autosave( $page_id ) ) {
			return;
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::sync_content( $page_id, (string) $post->post_content, (string) $post->post_status, (string) $post->post_type );
	}

	/**
	 * Anota o limpia los vínculos de una página a partir de su contenido y su estado.
	 *
	 * Separado de `sync_page()` para poder probarlo sin un WordPress de verdad.
	 *
	 * @param int    $page_id   Página.
	 * @param string $contenido Contenido.
	 * @param string $estado    Estado de la página.
	 * @param string $tipo      Tipo de contenido.
	 * @return void
	 */
	public static function sync_content( int $page_id, string $contenido, string $estado, string $tipo ): void {
		if ( 'page' !== $tipo ) {
			return;
		}

		$antes = array_map( 'intval', (array) get_post_meta( $page_id, self::META_LIST, true ) );
		$ahora = self::lesson_ids_in( $contenido );

		if ( 'publish' !== $estado ) {
			$ahora = array();
		}

		foreach ( $ahora as $lesson_id ) {
			if ( CPT_Leccion::TYPE === get_post_type( $lesson_id ) ) {
				update_post_meta( $lesson_id, self::META_PAGE, $page_id );
			}
		}

		foreach ( array_diff( $antes, $ahora ) as $lesson_id ) {
			if ( (int) get_post_meta( $lesson_id, self::META_PAGE, true ) === $page_id ) {
				delete_post_meta( $lesson_id, self::META_PAGE );
			}
		}

		update_post_meta( $page_id, self::META_LIST, $ahora );
		self::$mapa_meta = null;
	}

	/**
	 * Lecciones citadas en un contenido.
	 *
	 * @param string $contenido Contenido de la página.
	 * @return array<int, int>
	 */
	public static function lesson_ids_in( string $contenido ): array {
		if ( ! preg_match_all( '/\[convoca_curso_leccion\b[^\]]*id\s*=\s*["\'](\d+)["\']/', $contenido, $m ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
	}

	/**
	 * URL de una lección: propia si se puede, página como respaldo.
	 *
	 * @param int $lesson_id Lección.
	 * @return string
	 */
	public static function url( int $lesson_id ): string {
		if ( $lesson_id <= 0 ) {
			return '';
		}

		$slug       = (string) get_post_field( 'post_name', $lesson_id );
		$curso      = Hierarchy::course_of_lesson( $lesson_id );
		$slug_curso = $curso > 0 ? (string) get_post_field( 'post_name', $curso ) : '';

		if ( '' !== $slug && '' !== $slug_curso && '' !== (string) get_option( 'permalink_structure' ) ) {
			return home_url( user_trailingslashit( 'cursos/' . $slug_curso . '/leccion/' . $slug ) );
		}

		$pagina = self::pagina_de( $lesson_id );

		return $pagina > 0 ? (string) get_permalink( $pagina ) . '#leccion-' . $lesson_id : '';
	}

	/**
	 * Página que publica una lección: primero el metadato, luego el contenido.
	 *
	 * @param int $lesson_id Lección.
	 * @return int
	 */
	public static function pagina_de( int $lesson_id ): int {
		$meta = self::mapa_meta();

		if ( isset( $meta[ $lesson_id ] ) && 'publish' === get_post_status( $meta[ $lesson_id ] ) ) {
			return $meta[ $lesson_id ];
		}

		return Shortcodes::pagina_por_contenido( $lesson_id );
	}

	/**
	 * Mapa lección → página desde postmeta, en una sola consulta.
	 *
	 * @return array<int, int>
	 */
	private static function mapa_meta(): array {
		if ( null !== self::$mapa_meta ) {
			return self::$mapa_meta;
		}

		self::$mapa_meta = array();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- una lectura por petición, cacheada en el mapa.
		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_PAGE
			)
		);

		foreach ( (array) $filas as $fila ) {
			self::$mapa_meta[ (int) $fila->post_id ] = (int) $fila->meta_value;
		}

		return self::$mapa_meta;
	}

	/**
	 * Sirve la lección por su URL propia.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		$slug = (string) get_query_var( 'convoca_leccion_slug' );

		if ( '' === $slug || is_admin() ) {
			return;
		}

		$lecciones = get_posts(
			array(
				'post_type'      => CPT_Leccion::TYPE,
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$lesson_id = isset( $lecciones[0] ) ? (int) $lecciones[0] : 0;
		if ( $lesson_id <= 0 ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		$course_id  = Hierarchy::course_of_lesson( $lesson_id );
		$slug_curso = (string) get_query_var( 'convoca_curso_slug' );

		if ( '' !== $slug_curso && $course_id > 0 && (string) get_post_field( 'post_name', $course_id ) !== $slug_curso ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		if ( ! Access::can_view_lesson( get_current_user_id(), $lesson_id ) ) {
			$destino = $course_id > 0 ? (string) get_permalink( $course_id ) : home_url( '/' );
			wp_safe_redirect( $destino );
			exit;
		}

		$html = Shortcodes::lesson( array( 'id' => (string) $lesson_id ) );

		status_header( 200 );
		get_header();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del propio shortcode, ya escapado en sus plantillas.
		echo '<main class="convoca-courses convoca-courses--leccion">' . $html . '</main>';
		get_footer();
		exit;
	}
}
