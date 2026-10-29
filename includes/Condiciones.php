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
	 * Versión del texto de fábrica de la renuncia.
	 *
	 * Se guarda con cada prueba para poder decir «se le mostró la versión X» sin recalcular huellas.
	 */
	public const TEXTO_RENUNCIA_VERSION = '2026-10-29';

	/**
	 * Meta de la matrícula: prueba de la renuncia (texto, fecha, IP y navegador).
	 */
	public const META_RENUNCIA_PRUEBA = '_convoca_renuncia_prueba';

	/**
	 * Versión del texto que se mostró.
	 *
	 * Si la instalación lo ha cambiado en Ajustes, la versión de fábrica ya no aplica: se marca como propia.
	 *
	 * @return string
	 */
	public static function version_texto(): string {
		$propio = (string) get_option( self::OPTION_RENUNCIA_TEXTO, '' );

		if ( '' !== trim( $propio ) ) {
			$sello = (string) get_option( self::OPTION_RENUNCIA_TEXTO . '_fecha', '' );

			return 'propia' . ( '' !== $sello ? '-' . $sello : '' );
		}

		return self::TEXTO_RENUNCIA_VERSION;
	}

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
	 * ¿Se puede ofrecer la renuncia al desistimiento en este curso?
	 *
	 * Hacen falta dos cosas: que la instalación la tenga activada **y** que el curso sea cien por cien
	 * digital. La excepción del artículo 103.m solo alcanza al contenido digital sin soporte tangible; si el
	 * curso lleva sesiones en directo, tutorías o material físico, esa parte sigue siendo reembolsable y la
	 * casilla no debe aparecer.
	 *
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function renuncia_disponible( int $course_id ): bool {
		return self::renuncia_activa() && CPT_Curso::is_fully_digital( $course_id );
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

		$datos  = Entidad::datos();
		$sujeto = '' !== trim( (string) $datos['nombre'] ) ? (string) $datos['nombre'] : __( 'la entidad', 'convoca-courses' );

		$texto = __( 'Quiero empezar el curso ahora, sin esperar a que terminen los 14 días naturales de desistimiento. Sé que, al abrir el contenido digital del curso —las lecciones y sus materiales—, empieza su ejecución y pierdo el derecho de desistimiento sobre ese contenido digital, de modo que no podré pedir la devolución de lo que ya haya consumido (art. 103.m del TRLGDCU). Sé también que la parte del curso que no sea contenido digital —sesiones en directo, tutorías o material impreso, si las hubiera— sigue sujeta al derecho de desistimiento, aunque marque esta casilla. Entiendo que esta casilla es opcional: si no la marco, hago el curso igual y conservo los 14 días naturales completos. Y sé que {{entidad}} me enviará por correo la confirmación de esta contratación y de este consentimiento, y que la aceptación quedará registrada con su fecha y los datos técnicos de la conexión, según la política de privacidad.', 'convoca-courses' );

		return str_replace( '{{entidad}}', $sujeto, $texto );
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
			'version' => self::version_texto(),
		);
	}

	/**
	 * Deja constancia en la matrícula de lo que se aceptó y de lo que se renunció.
	 *
	 * La prueba se guarda **dos veces** y a propósito: un resumen en la matrícula (para verlo desde el
	 * escritorio) y la fila completa en la tabla propia, que sobrevive a la matrícula y se conserva cinco años.
	 *
	 * @param int    $matricula_id Matrícula.
	 * @param bool   $renuncia     Si se marcó la casilla de renuncia.
	 * @param int    $course_id    Curso (0 si no se conoce).
	 * @param string $email        Correo de quien contrata (0 si no se conoce).
	 * @return int Identificador de la prueba archivada, o 0.
	 */
	public static function registrar_aceptacion( int $matricula_id, bool $renuncia, int $course_id = 0, string $email = '' ): int {
		if ( $matricula_id <= 0 ) {
			return 0;
		}

		$efectiva = $renuncia && self::renuncia_activa() && ( 0 === $course_id || self::renuncia_disponible( $course_id ) );

		update_post_meta( $matricula_id, self::META_CONSENTIMIENTO, (string) current_time( 'mysql' ) );
		update_post_meta( $matricula_id, self::META_CONSENTIMIENTO_TEXTO, self::texto_consentimiento() );

		$prueba = array();

		if ( $efectiva ) {
			$prueba = self::prueba( self::texto_renuncia() );

			update_post_meta( $matricula_id, self::META_RENUNCIA, '1' );
			update_post_meta( $matricula_id, self::META_RENUNCIA_PRUEBA, (string) wp_json_encode( $prueba ) );
		}

		return Prueba_Consentimiento::guardar(
			array(
				'matricula_id'         => $matricula_id,
				'curso_id'             => $course_id,
				'email'                => $email,
				'consentimiento_texto' => self::texto_consentimiento(),
				'prueba'               => $prueba,
			)
		);
	}

	/**
	 * Confirmación en soporte duradero de lo aceptado (artículo 98.7 del TRLGDCU).
	 *
	 * No es cortesía: el artículo 103.m exige, para que se pierda el derecho, que el empresario **haya
	 * proporcionado una confirmación** con arreglo al 98.7 o al 99.2. Sin este correo, la renuncia no opera
	 * aunque la casilla esté marcada y el registro exista.
	 *
	 * El correo sirve además de prueba autónoma: quien lo recibe guarda el texto que aceptó y la fecha.
	 *
	 * @param int    $matricula_id Matrícula.
	 * @param int    $course_id    Curso.
	 * @param string $email        Destinatario.
	 * @param string $nombre       Nombre de quien contrata.
	 * @param bool   $renuncia     Si la renuncia quedó registrada.
	 * @param int    $prueba_id    Fila de la prueba archivada.
	 * @return bool Si salió el correo.
	 */
	public static function confirmar( int $matricula_id, int $course_id, string $email, string $nombre, bool $renuncia, int $prueba_id = 0 ): bool {
		if ( '' === $email || ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		$entidad = Entidad::datos();
		$curso   = (string) get_the_title( $course_id );
		$fecha   = (string) current_time( 'd/m/Y' );
		$lineas  = array();

		$lineas[] = sprintf(
			/* translators: 1: nombre de la persona, 2: nombre del curso. */
			__( 'Hola %1$s: queda confirmada tu matrícula en «%2$s».', 'convoca-courses' ),
			'' === $nombre ? __( 'alumna', 'convoca-courses' ) : $nombre,
			$curso
		);
		$lineas[] = '';
		$lineas[] = sprintf(
			/* translators: 1: fecha, 2: importe. */
			__( 'Fecha: %1$s. Importe: %2$s.', 'convoca-courses' ),
			$fecha,
			CPT_Curso::formatted_price( $course_id )
		);
		$lineas[] = '';

		if ( $renuncia ) {
			$lineas[] = __( 'Has pedido empezar el curso sin esperar los 14 días naturales de desistimiento. Este es el texto exacto que aceptaste:', 'convoca-courses' );
			$lineas[] = '';
			$lineas[] = '«' . self::texto_renuncia() . '»';
			$lineas[] = '';
			$lineas[] = __( 'En consecuencia, el contenido digital que ya has consumido queda fuera del derecho de desistimiento. Lo que no sea contenido digital (sesiones en directo, tutorías o material impreso) sigue sujeto a ese derecho.', 'convoca-courses' );
		} else {
			$lineas[] = __( 'No has renunciado al desistimiento: dispones de 14 días naturales desde hoy para desistir sin justificar el motivo y sin coste, salvo los gastos de devolución si los hubiera. No necesitas marcar ninguna casilla para hacer el curso.', 'convoca-courses' );
		}

		$lineas[] = '';
		$lineas[] = __( 'Cómo desistir: escribe un correo a la dirección que figura abajo indicando tu nombre, el curso y la fecha de la compra. Puedes usar el formulario oficial de desistimiento de la web. Te confirmaremos la recepción en soporte duradero.', 'convoca-courses' );
		$lineas[] = '';

		if ( '' !== (string) $entidad['nombre'] ) {
			$lineas[] = (string) $entidad['nombre'] . ( '' !== (string) $entidad['nif'] ? ' · NIF ' . $entidad['nif'] : '' );
		}
		if ( '' !== (string) $entidad['direccion'] ) {
			$lineas[] = (string) $entidad['direccion'];
		}
		if ( '' !== (string) $entidad['email_desistimiento'] ) {
			$lineas[] = __( 'Desistimientos y consultas:', 'convoca-courses' ) . ' ' . (string) $entidad['email_desistimiento'];
		}

		$lineas[] = '';
		$lineas[] = sprintf(
			/* translators: %d: años de conservación. */
			__( 'Guardamos esta confirmación y el registro de tu consentimiento de forma separada durante %d años, para poder atender cualquier reclamación. La información completa sobre el tratamiento está en la política de privacidad de la web.', 'convoca-courses' ),
			Prueba_Consentimiento::RETENCION_ANIOS
		);
		$lineas[] = '';
		$lineas[] = __( 'Conserva este correo: es la confirmación de tu contratación.', 'convoca-courses' );

		$enviado = Notifications::send_to_email(
			$email,
			sprintf(
				/* translators: %s: nombre del curso. */
				__( 'Confirmación de tu matrícula en «%s»', 'convoca-courses' ),
				$curso
			),
			implode( "\n", $lineas ),
			'confirmacion_98_7',
			array( 'matricula_id' => $matricula_id )
		);

		if ( $enviado && $prueba_id > 0 ) {
			Prueba_Consentimiento::marcar_confirmacion( $prueba_id );
		}

		return (bool) $enviado;
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
