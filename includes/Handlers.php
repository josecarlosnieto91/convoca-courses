<?php
/**
 * Formularios públicos sin JavaScript: admin-post + nonce + redirect.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manejadores de las acciones públicas y del refresco de la URL de vídeo.
 */
class Handlers {

	/**
	 * Acción del formulario de matrícula.
	 */
	public const ACTION_ENROLL = 'convoca_courses_enroll';

	/**
	 * Acción del formulario de baja.
	 */
	public const ACTION_CANCEL = 'convoca_courses_cancel';

	/**
	 * Acción del formulario de lección completada.
	 */
	public const ACTION_COMPLETE = 'convoca_courses_complete';

	/**
	 * Acción AJAX que renueva la URL firmada del vídeo.
	 */
	public const ACTION_VIDEO = 'convoca_courses_video';

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_ENROLL, array( __CLASS__, 'handle_enroll' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_ENROLL, array( __CLASS__, 'handle_enroll' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_COMPLETE, array( __CLASS__, 'handle_complete' ) );
		add_action( 'wp_ajax_' . self::ACTION_VIDEO, array( __CLASS__, 'handle_video' ) );
	}

	/**
	 * URL del formulario de matrícula.
	 *
	 * @return string
	 */
	public static function enroll_form_action(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/**
	 * Nonce del formulario de matrícula.
	 *
	 * @return string
	 */
	public static function enroll_nonce(): string {
		return wp_create_nonce( self::ACTION_ENROLL );
	}

	/**
	 * Nonce del formulario de lección completada.
	 *
	 * @param int $lesson_id Lección.
	 * @return string
	 */
	public static function complete_nonce( int $lesson_id ): string {
		return wp_create_nonce( self::ACTION_COMPLETE . '_' . $lesson_id );
	}

	/**
	 * URL del formulario de completar lección.
	 *
	 * @return string
	 */
	public static function complete_form_action(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/**
	 * Atiende la matrícula.
	 *
	 * @return void
	 */
	public static function handle_enroll(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_ENROLL ) ) {
			self::redirect_back( $course_id, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			$target = $course_id > 0 ? (string) get_permalink( $course_id ) : home_url( '/' );
			wp_safe_redirect( wp_login_url( $target ) );
			exit;
		}

		// Curso de pago con el gateway activo: la matrícula nace PENDIENTE y se manda a la pasarela.
		// Sin gateway (o con precio 0) el flujo es el de siempre: matrícula activa al momento.
		$needs_payment = Course_Payment::course_needs_payment( $course_id );

		$result = Enrollment::enroll(
			get_current_user_id(),
			$course_id,
			array(
				'estado' => $needs_payment ? 'pendiente' : 'activa',
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_back( $course_id, 'error', $result->get_error_code() );
			return;
		}

		if ( ! $needs_payment ) {
			self::redirect_back( $course_id, 'enrolled' );
			return;
		}

		$payment = Course_Payment::create( (int) $result, $course_id );

		if ( is_wp_error( $payment ) ) {
			// La matrícula queda pendiente y sin enlace: se avisa y se puede reintentar o cobrar a mano.
			self::redirect_back( $course_id, 'payment_error' );
			return;
		}

		wp_safe_redirect( (string) $payment['payment_url'] );
		exit;
	}

	/**
	 * Atiende la baja de matrícula.
	 *
	 * @return void
	 */
	public static function handle_cancel(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
		$course_id     = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		$nonce         = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_CANCEL . '_' . $enrollment_id ) ) {
			self::redirect_back( $course_id, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			self::redirect_back( $course_id, 'error' );
			return;
		}

		if ( CPT_Matricula::user_id( $enrollment_id ) !== get_current_user_id() && ! Access::can_manage() ) {
			self::redirect_back( $course_id, 'error' );
			return;
		}

		Enrollment::cancel( $enrollment_id );
		self::redirect_back( $course_id, 'cancelled' );
	}

	/**
	 * Atiende la marca de lección completada (con y sin JavaScript).
	 *
	 * @return void
	 */
	public static function handle_complete(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$lesson_id = isset( $_POST['lesson_id'] ) ? (int) $_POST['lesson_id'] : 0;
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_COMPLETE . '_' . $lesson_id ) ) {
			self::redirect_back( 0, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() || ! Access::can_view_lesson( get_current_user_id(), $lesson_id ) ) {
			self::redirect_back( Hierarchy::course_of_lesson( $lesson_id ), 'error' );
			return;
		}

		$user_id   = get_current_user_id();
		$course_id = Hierarchy::course_of_lesson( $lesson_id );

		Progress::complete( $user_id, $course_id, $lesson_id );

		$enrollment_id = Enrollment::active_id( $user_id, $course_id );
		if ( $enrollment_id > 0 ) {
			$progress = Progress::for_course( $user_id, $course_id );
			if ( $progress['total'] > 0 && $progress['done'] >= $progress['total'] ) {
				Enrollment::set_status( $enrollment_id, 'completada' );
			}
		}

		$referer = wp_get_referer();
		if ( $referer ) {
			wp_safe_redirect( add_query_arg( 'convoca_msg', 'completed', $referer ) );
			exit;
		}

		self::redirect_back( $course_id, 'completed' );
	}

	/**
	 * Devuelve una URL firmada nueva para el vídeo de una lección.
	 *
	 * @return void
	 */
	public static function handle_video(): void {
		check_ajax_referer( self::ACTION_VIDEO, 'nonce' );

		$lesson_id = isset( $_POST['lesson_id'] ) ? (int) $_POST['lesson_id'] : 0;

		if ( ! Access::can_view_lesson( get_current_user_id(), $lesson_id ) || ! License::video_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes acceso a este vídeo.', 'convoca-courses' ) ), 403 );
		}

		$source = Video::lesson_source( $lesson_id );
		if ( '' === $source['src'] ) {
			wp_send_json_error( array( 'message' => __( 'El vídeo no está configurado.', 'convoca-courses' ) ), 404 );
		}

		wp_send_json_success( array( 'src' => $source['src'] ) );
	}

	/**
	 * Vuelve a la página de origen con un aviso legible.
	 *
	 * @param int    $course_id Curso.
	 * @param string $message   Clave del aviso.
	 * @param string $detail    Detalle adicional (código de error).
	 * @return void
	 */
	private static function redirect_back( int $course_id, string $message, string $detail = '' ): void {
		// Primero se vuelve a la página desde la que se envió el formulario: es la que tiene los
		// shortcodes, así que es la única donde el aviso y el progreso se ven. El permalink del curso
		// es el respaldo (y el destino cuando no hay referente, como en un enlace directo).
		$referer = wp_get_referer();
		if ( $referer ) {
			$target = (string) wp_validate_redirect( $referer, '' );
		} else {
			$target = '';
		}

		if ( '' === $target ) {
			$target = $course_id > 0 ? (string) get_permalink( $course_id ) : home_url( '/' );
		}

		$args = array( 'convoca_msg' => $message );
		if ( '' !== $detail ) {
			$args['convoca_detail'] = $detail;
		}

		wp_safe_redirect( add_query_arg( $args, $target ) );
		exit;
	}

	/**
	 * Mensaje legible para un aviso de la URL.
	 *
	 * @param string $key Clave del aviso.
	 * @return string
	 */
	public static function message( string $key ): string {
		switch ( $key ) {
			case 'enrolled':
				return __( 'Matrícula realizada. Ya puedes empezar el curso.', 'convoca-courses' );
			case 'cancelled':
				return __( 'Matrícula cancelada.', 'convoca-courses' );
			case 'completed':
				return __( 'Lección marcada como completada.', 'convoca-courses' );
			case 'pending_payment':
				return __( 'Matrícula reservada. Completa el pago para activarla.', 'convoca-courses' );
			case 'payment_error':
				return __( 'No se ha podido preparar el pago. La matrícula queda pendiente: inténtalo de nuevo o contacta con el centro.', 'convoca-courses' );
			case 'nonce':
				return __( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' );
			case 'error':
				return __( 'No se ha podido completar la operación.', 'convoca-courses' );
			default:
				return '';
		}
	}
}
