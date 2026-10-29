<?php
/**
 * Condiciones de venta publicables.
 *
 * La plantilla vive en `templates/condiciones.md` con marcadores; los datos los pone cada organización en
 * Ajustes, así que el mismo plugin sirve a cualquier entidad sin tocar código.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Texto legal y su shortcode.
 */
class Condiciones {

	/**
	 * Shortcode público.
	 */
	public const SHORTCODE = 'convoca_condiciones';

	/**
	 * Opción con el texto propio de la instalación.
	 */
	public const OPTION = 'convoca_courses_condiciones_texto';

	/**
	 * Opción con el texto de la casilla de consentimiento.
	 */
	public const OPTION_CONSENTIMIENTO = 'convoca_courses_consentimiento_texto';

	/**
	 * Opción que habilita ofrecer la renuncia al desistimiento.
	 */
	public const OPTION_RENUNCIA = 'convoca_courses_renuncia_activa';

	/**
	 * Meta de la matrícula: cuándo se aceptó.
	 */
	public const META_CONSENTIMIENTO = '_convoca_consentimiento';

	/**
	 * Meta de la matrícula: renuncia expresa al desistimiento.
	 */
	public const META_RENUNCIA = '_convoca_renuncia_desistimiento';

	/**
	 * Opción con el texto propio de la casilla de renuncia.
	 */
	public const OPTION_RENUNCIA_TEXTO = 'convoca_courses_renuncia_texto';

	/**
	 * Meta de la matrícula: texto exacto del consentimiento que se mostró.
	 */
	public const META_CONSENTIMIENTO_TEXTO = '_convoca_consentimiento_texto';

	/**
	 * Meta de la matrícula: prueba de la renuncia (texto, fecha, IP y navegador).
	 */
	public const META_RENUNCIA_PRUEBA = '_convoca_renuncia_prueba';

	/**
	 * Texto por defecto de la casilla obligatoria.
	 *
	 * @return string
	 */
	public static function texto_consentimiento(): string {
		$propio = (string) get_option( self::OPTION_CONSENTIMIENTO, '' );

		if ( '' !== trim( $propio ) ) {
			return $propio;
		}

		return __( 'He leído las condiciones de venta y sé que tengo 14 días naturales para desistir sin justificar el motivo.', 'convoca-courses' );
	}

	/**
	 * ¿Se ofrece la renuncia expresa al desistimiento?
	 *
	 * Solo tiene efecto si la instalación lo activa: la excepción del art. 103.m exige consentimiento previo
	 * expreso y separado, y no se puede dar por supuesto.
	 *
	 * @return bool
	 */
	public static function renuncia_activa(): bool {
		return (bool) get_option( self::OPTION_RENUNCIA, false );
	}

	/**
	 * Texto de la casilla de renuncia.
	 *
	 * Lo que exige el artículo 103.m del TRLGDCU, en una sola casilla y **opcional**:
	 *
	 *  - consentimiento **previo y expreso** (el de esta casilla, no el de las condiciones generales);
	 *  - que se diga **con claridad** que al empezar a consumir el contenido se pierde el derecho;
	 *  - que quede **prueba** de ese consentimiento (de eso se encarga `prueba()` al guardar la matrícula).
	 *
	 * Y dos cosas que no se pueden callar: la renuncia alcanza **solo al contenido digital**, y hay una
	 * alternativa real (marcarla o no marcarla), porque si el consentimiento no es libre la cláusula es abusiva.
	 *
	 * La entidad puede cambiar el texto en Ajustes: esta es la redacción de fábrica, revisada para que se pueda
	 * leer en voz alta sin ambigüedad. No es un permiso para no devolver: es la excepción del 103.m, con su
	 * alcance y su límite.
	 *
	 * @return string
	 */
	public static function texto_renuncia(): string {
		$propio = (string) get_option( self::OPTION_RENUNCIA_TEXTO, '' );

		if ( '' !== trim( $propio ) ) {
			return $propio;
		}

		return __( 'Quiero empezar el curso ahora y no esperar a que pasen los 14 días naturales de desistimiento. Entiendo que, en cuanto abra el contenido digital del curso —las lecciones y sus materiales—, pierdo el derecho de desistimiento sobre esa parte, y que no podré pedir la devolución de lo ya consumido. Puedo marcar o no esta casilla: si no la marco, hago el curso igualmente y conservo los 14 días completos. Lo que no sea contenido digital (sesiones en directo, tutorías o material impreso) sigue sujeto al derecho de desistimiento aunque marque esta casilla.', 'convoca-courses' );
	}

	/**
	 * Prueba del consentimiento, para poder demostrarlo si alguien lo discute.
	 *
	 * Se guarda el texto **exacto** que se mostró (y su huella), con la fecha, la IP y el navegador de quien
	 * marcó. Sin el texto no se puede acreditar qué leyó; con él, la fecha y el agente, sí.
	 *
	 * @param string $texto  Texto que se mostró en la casilla.
	 * @param string $ambito A qué alcanza el consentimiento.
	 * @return array<string, string>
	 */
	public static function prueba( string $texto, string $ambito = 'contenido_digital' ): array {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$agente = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return array(
			'texto'   => $texto,
			'huella'  => hash( 'sha256', $texto ),
			'fecha'   => gmdate( 'c' ),
			'ip'      => $ip,
			'agente'  => mb_substr( $agente, 0, 255 ),
			'ambito'  => $ambito,
			'version' => defined( 'CONVOCA_COURSES_VERSION' ) ? (string) CONVOCA_COURSES_VERSION : '',
		);
	}

	/**
	 * Deja constancia en la matrícula de lo que se aceptó y de lo que se renunció.
	 *
	 * @param int  $matricula_id Matrícula.
	 * @param bool $renuncia     Si se marcó la casilla de renuncia.
	 * @return void
	 */
	public static function registrar_aceptacion( int $matricula_id, bool $renuncia ): void {
		if ( $matricula_id <= 0 ) {
			return;
		}

		update_post_meta( $matricula_id, self::META_CONSENTIMIENTO, (string) current_time( 'mysql' ) );
		update_post_meta( $matricula_id, self::META_CONSENTIMIENTO_TEXTO, self::texto_consentimiento() );

		if ( ! $renuncia || ! self::renuncia_activa() ) {
			return;
		}

		$texto = self::texto_renuncia();

		update_post_meta( $matricula_id, self::META_RENUNCIA, '1' );
		update_post_meta( $matricula_id, self::META_RENUNCIA_PRUEBA, (string) wp_json_encode( self::prueba( $texto ) ) );
	}

	/**
	 * Texto con los datos de la entidad ya puestos.
	 *
	 * @return string
	 */
	public static function texto(): string {
		$propio = (string) get_option( self::OPTION, '' );

		if ( '' !== trim( $propio ) ) {
			return Entidad::sustituir( $propio );
		}

		return Entidad::sustituir( self::plantilla() );
	}

	/**
	 * Texto de fábrica, sin sustituir.
	 *
	 * @return string
	 */
	public static function plantilla(): string {
		$ruta = dirname( __DIR__ ) . '/templates/condiciones.md';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		return is_readable( $ruta ) ? (string) file_get_contents( $ruta ) : '';
	}

	/**
	 * Pinta el texto como HTML.
	 *
	 * @return string
	 */
	public static function html(): string {
		$texto = self::texto();

		if ( '' === $texto ) {
			return '';
		}

		return wpautop( esc_html( $texto ) );
	}

	/**
	 * Shortcode: `[convoca_condiciones]`.
	 *
	 * @return string
	 */
	public static function render(): string {
		if ( ! Entidad::completa() ) {
			if ( current_user_can( 'manage_options' ) || Access::can_manage() ) {
				return '<div class="convoca-alert convoca-alert--warning">'
					. esc_html__( 'Faltan el nombre, el NIF o el correo de desistimiento de la entidad. Rellénalos en Cursos → Ajustes antes de publicar esta página.', 'convoca-courses' )
					. '</div>';
			}

			return '';
		}

		return '<div class="convoca-condiciones">' . self::html() . '</div>';
	}
}
