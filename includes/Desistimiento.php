<?php
/**
 * Desistimiento del contrato, sin cuenta.
 *
 * El alumno recibe en su correo un enlace firmado. Al confirmarlo se archiva la prueba, la matrícula pasa a
 * `cancelada` —y con ello se corta el acceso— y se avisa a la entidad para que devuelva el dinero.
 *
 * El enlace no mueve dinero: solo declara la voluntad. La devolución se ejecuta en el gateway.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Solicitud de desistimiento por enlace firmado.
 */
class Desistimiento {

	/**
	 * Parámetro del enlace con la matrícula.
	 */
	public const PARAM = 'convoca_desistir';

	/**
	 * Parámetro del enlace con el token.
	 */
	public const PARAM_TOKEN = 't';

	/**
	 * Meta de la matrícula: prueba de la solicitud.
	 */
	public const META_PRUEBA = '_convoca_desistimiento_prueba';

	/**
	 * Meta de la matrícula: estado de la solicitud.
	 */
	public const META_ESTADO = '_convoca_desistimiento';

	/**
	 * Días que vale el enlace. El plazo legal para desistir es de 14 días naturales; el enlace se deja algo
	 * más para que quien lo pide en el límite no se quede fuera por la hora del servidor.
	 */
	public const VIDA = 20 * DAY_IN_SECONDS;

	/**
	 * Engancha el shortcode y el enlace del correo.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'convoca_desistir', array( __CLASS__, 'render' ) );
	}

	/**
	 * Página que aloja el shortcode, creada una sola vez.
	 *
	 * Sin ella el enlace del correo no lleva a ninguna parte, así que se crea al instalar y se recuerda su ID.
	 *
	 * @return int
	 */
	public static function pagina(): int {
		$guardada = (int) get_option( 'convoca_courses_desistir_page', -1 );

		if ( $guardada > 0 && 'page' === get_post_type( $guardada ) ) {
			return $guardada;
		}

		$existente = get_page_by_path( 'desistir', 'OBJECT', 'page' );

		if ( $existente instanceof \WP_Post ) {
			update_option( 'convoca_courses_desistir_page', (int) $existente->ID );
			return (int) $existente->ID;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Desistir del curso', 'convoca-courses' ),
				'post_name'    => 'desistir',
				'post_content' => '[convoca_desistir]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $id > 0 ) {
			update_option( 'convoca_courses_desistir_page', $id );
			return $id;
		}

		return 0;
	}

	/**
	 * Firma un enlace para una matrícula.
	 *
	 * @param int $matricula_id Matrícula.
	 * @return string Token; vacío si la matrícula no es válida.
	 */
	public static function token( int $matricula_id ): string {
		if ( $matricula_id <= 0 ) {
			return '';
		}

		$expira = time() + self::VIDA;

		return $expira . '.' . hash_hmac( 'sha256', $matricula_id . '|' . $expira, wp_salt( 'auth' ) );
	}

	/**
	 * Comprueba un token en tiempo constante.
	 *
	 * @param int    $matricula_id Matrícula.
	 * @param string $token        Token recibido.
	 * @return bool
	 */
	public static function verificar( int $matricula_id, string $token ): bool {
		$partes = explode( '.', $token, 2 );

		if ( 2 !== count( $partes ) ) {
			return false;
		}

		$expira = (int) $partes[0];

		if ( $expira < time() ) {
			return false;
		}

		$esperado = hash_hmac( 'sha256', $matricula_id . '|' . $expira, wp_salt( 'auth' ) );

		return hash_equals( $esperado, $partes[1] );
	}

	/**
	 * URL de desistimiento de una matrícula.
	 *
	 * @param int $matricula_id Matrícula.
	 * @return string
	 */
	public static function url( int $matricula_id ): string {
		$token = self::token( $matricula_id );

		if ( '' === $token ) {
			return '';
		}

		$base = get_permalink( self::pagina() );

		if ( ! is_string( $base ) || '' === $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg(
			array(
				self::PARAM       => $matricula_id,
				self::PARAM_TOKEN => rawurlencode( $token ),
			),
			$base
		);
	}

	/**
	 * ¿Se puede desistir de esta matrícula?
	 *
	 * @param int $matricula_id Matrícula.
	 * @return bool
	 */
	public static function disponible( int $matricula_id ): bool {
		if ( $matricula_id <= 0 ) {
			return false;
		}

		$estado = (string) get_post_meta( $matricula_id, CPT_Base::META_PREFIX . 'estado', true );

		if ( 'cancelada' === $estado ) {
			return false;
		}

		// Quien renunció al desistimiento no puede ejercerlo.
		return '1' !== (string) get_post_meta( $matricula_id, Condiciones::META_RENUNCIA, true );
	}

	/**
	 * Shortcode `[convoca_desistir]`.
	 *
	 * Acepta `convoca_desistir` y `t` por GET, o los usa del enlace de la página actual.
	 *
	 * @param array<string,mixed> $atts Atributos.
	 * @return string
	 */
	public static function render( $atts = array() ): string {
		unset( $atts );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el token firmado es la autorización.
		$matricula_id = isset( $_GET[ self::PARAM ] ) ? absint( wp_unslash( (string) $_GET[ self::PARAM ] ) ) : 0;
		$token        = isset( $_GET[ self::PARAM_TOKEN ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM_TOKEN ] ) ) : '';
		$confirmado   = isset( $_POST['convoca_desistir_confirmar'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $confirmado && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'convoca_desistir_' . $matricula_id ) ) {
			$resultado = self::solicitar( $matricula_id, $token );

			return '<p class="convoca-aviso convoca-aviso--ok">' . esc_html( $resultado ) . '</p>';
		}

		if ( ! self::verificar( $matricula_id, $token ) ) {
			return '<p class="convoca-aviso convoca-aviso--error">' . esc_html__( 'Este enlace no es válido o ha caducado.', 'convoca-courses' ) . '</p>';
		}

		if ( ! self::disponible( $matricula_id ) ) {
			return '<p class="convoca-aviso">' . esc_html__( 'Esta matrícula no admite desistimiento.', 'convoca-courses' ) . '</p>';
		}

		$html  = '<p>' . esc_html__( 'Si desistes, pierdes el acceso al curso en este momento y la entidad te devolverá el importe por el mismo medio de pago.', 'convoca-courses' ) . '</p>';
		$html .= '<form method="post">';
		$html .= wp_nonce_field( 'convoca_desistir_' . $matricula_id, '_wpnonce', true, false );
		$html .= '<p><button type="submit" name="convoca_desistir_confirmar" value="1" class="convoca-boton">' . esc_html__( 'Desistir del curso', 'convoca-courses' ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Registra la solicitud: prueba, corte de acceso y avisos.
	 *
	 * @param int    $matricula_id Matrícula.
	 * @param string $token        Token del enlace.
	 * @return string Mensaje para el alumno.
	 */
	public static function solicitar( int $matricula_id, string $token ): string {
		if ( ! self::verificar( $matricula_id, $token ) ) {
			return __( 'Este enlace no es válido o ha caducado.', 'convoca-courses' );
		}

		if ( ! self::disponible( $matricula_id ) ) {
			return __( 'Esta matrícula no admite desistimiento.', 'convoca-courses' );
		}

		$prueba = array(
			'fecha'     => current_time( 'mysql' ),
			'ip'        => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '',
			'navegador' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'texto'     => __( 'Declaro que desisto del contrato de enseñanza de este curso.', 'convoca-courses' ),
			'via'       => 'enlace',
		);

		update_post_meta( $matricula_id, self::META_PRUEBA, $prueba );
		update_post_meta( $matricula_id, self::META_ESTADO, 'solicitado' );
		Enrollment::set_status( $matricula_id, 'cancelada' );

		self::avisar( $matricula_id, $prueba );

		return __( 'Desistimiento registrado. Has perdido el acceso al curso y la entidad tramitará la devolución.', 'convoca-courses' );
	}

	/**
	 * Avisa a la entidad y al alumno.
	 *
	 * @param int                 $matricula_id Matrícula.
	 * @param array<string,mixed> $prueba       Prueba archivada.
	 * @return void
	 */
	private static function avisar( int $matricula_id, array $prueba ): void {
		$entidad = Entidad::datos();
		$course  = (int) get_post_meta( $matricula_id, CPT_Base::META_PREFIX . 'curso', true );
		$titulo  = $course > 0 ? (string) get_the_title( $course ) : '';
		$alumno  = (string) get_post_meta( $matricula_id, CPT_Base::META_PREFIX . 'email', true );
		$importe = (float) get_post_meta( $matricula_id, CPT_Base::META_PREFIX . 'importe', true );
		$detalle = sprintf(
			/* translators: 1: curso, 2: matrícula, 3: fecha, 4: importe */
			__( "Curso: %1\$s\nMatrícula: %2\$d\nFecha: %3\$s\nImporte a devolver: %4\$s", 'convoca-courses' ),
			$titulo,
			$matricula_id,
			(string) $prueba['fecha'],
			number_format_i18n( $importe, 2 )
		);

		$correo_entidad = (string) ( $entidad['correo'] ?? '' );
		if ( '' !== $correo_entidad ) {
			Notifications::send_to_email(
				$correo_entidad,
				__( 'Desistimiento solicitado', 'convoca-courses' ),
				$detalle . "\n\n" . __( 'El acceso al curso ya está cortado. La devolución se ejecuta desde el gateway.', 'convoca-courses' ),
				'desistimiento_entidad'
			);
		}

		if ( '' !== $alumno ) {
			Notifications::send_to_email(
				$alumno,
				__( 'Tu desistimiento ha quedado registrado', 'convoca-courses' ),
				$detalle . "\n\n" . __( 'Conserva este correo como prueba de tu solicitud.', 'convoca-courses' ),
				'desistimiento_alumno'
			);
		}
	}
}
