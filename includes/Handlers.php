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
	 * Acción del formulario que abre un intento de examen.
	 */
	public const ACTION_EXAM_OPEN = 'convoca_courses_exam_open';

	/**
	 * Acción del formulario que envía un examen.
	 */
	public const ACTION_EXAM_SUBMIT = 'convoca_courses_exam_submit';

	/**
	 * Acción del formulario de entrega de una tarea.
	 */
	public const ACTION_TASK_SUBMIT = 'convoca_courses_task_submit';

	/**
	 * Acción del formulario de corrección de una entrega (panel).
	 */
	public const ACTION_TASK_GRADE = 'convoca_courses_task_grade';

	/**
	 * Descarga del acta de notas en CSV.
	 */
	public const ACTION_GRADEBOOK_CSV = 'convoca_courses_gradebook_csv';

	/**
	 * Correo al grupo de alumnado (PRO).
	 */
	public const ACTION_GROUP_EMAIL = 'convoca_courses_group_email';

	/**
	 * Exportación del banco de preguntas (PRO).
	 */
	public const ACTION_BANK_EXPORT = 'convoca_courses_bank_export';

	/**
	 * Importación del banco de preguntas (PRO).
	 */
	public const ACTION_BANK_IMPORT = 'convoca_courses_bank_import';

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_ENROLL, array( __CLASS__, 'handle_enroll' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_ENROLL, array( __CLASS__, 'handle_enroll' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		// Estas cuatro las usa el alumnado, que puede no tener sesión de WordPress: sin la variante
		// `nopriv`, el POST llega a admin-post.php y no lo atiende nadie (lección sin completar,
		// página en blanco). Medido en el circuito del navegador de courses#27.
		add_action( 'admin_post_' . self::ACTION_COMPLETE, array( __CLASS__, 'handle_complete' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_COMPLETE, array( __CLASS__, 'handle_complete' ) );
		add_action( 'admin_post_' . self::ACTION_EXAM_OPEN, array( __CLASS__, 'handle_exam_open' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_EXAM_OPEN, array( __CLASS__, 'handle_exam_open' ) );
		add_action( 'admin_post_' . self::ACTION_EXAM_SUBMIT, array( __CLASS__, 'handle_exam_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_EXAM_SUBMIT, array( __CLASS__, 'handle_exam_submit' ) );
		add_action( 'admin_post_' . self::ACTION_TASK_SUBMIT, array( __CLASS__, 'handle_task_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_TASK_SUBMIT, array( __CLASS__, 'handle_task_submit' ) );
		add_action( 'admin_post_' . self::ACTION_TASK_GRADE, array( __CLASS__, 'handle_task_grade' ) );
		add_action( 'admin_post_' . self::ACTION_GRADEBOOK_CSV, array( __CLASS__, 'handle_gradebook_csv' ) );
		add_action( 'admin_post_' . self::ACTION_GROUP_EMAIL, array( __CLASS__, 'handle_group_email' ) );
		add_action( 'admin_post_' . self::ACTION_BANK_EXPORT, array( __CLASS__, 'handle_bank_export' ) );
		add_action( 'admin_post_' . self::ACTION_BANK_IMPORT, array( __CLASS__, 'handle_bank_import' ) );
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

		// Precio que le toca a esta persona: general, de socio o gratis por ser socio. Se decide en el
		// servidor y se guarda con la matrícula, para que después se pueda explicar qué se cobró.
		$precio        = Pricing::for_user( $course_id, get_current_user_id() );
		$needs_payment = Course_Payment::needs_payment( $precio['precio'], Course_Payment::gateway_available() );

		$result = Enrollment::enroll(
			get_current_user_id(),
			$course_id,
			array(
				'estado'        => $needs_payment ? 'pendiente' : 'activa',
				'importe'       => $precio['precio'],
				'motivo_precio' => $precio['motivo'],
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

		// No se exige sesión de WordPress: el alumnado matriculado por correo también completa lecciones.
		// Quien decide es `Access`, que resuelve la matrícula por usuario o por identidad (courses#27).
		if ( ! Access::can_view_lesson( get_current_user_id(), $lesson_id ) ) {
			self::redirect_back( Hierarchy::course_of_lesson( $lesson_id ), 'error' );
			return;
		}

		$user_id   = get_current_user_id();
		$course_id = Hierarchy::course_of_lesson( $lesson_id );

		$identidad     = Access::identidad_actual();
		$enrollment_id = Access::matricula_actual( $course_id );

		if ( 0 === $user_id && $identidad > 0 ) {
			Progress::complete_identidad( $identidad, $course_id, $lesson_id );
			$progress = Progress::for_course_identidad( $identidad, $course_id );
		} else {
			Progress::complete( $user_id, $course_id, $lesson_id );
			$progress = Progress::for_course( $user_id, $course_id );
		}

		if ( $enrollment_id > 0 && $progress['total'] > 0 && $progress['done'] >= $progress['total'] ) {
			Enrollment::set_status( $enrollment_id, 'completada' );
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
	 * URL del formulario de examen.
	 *
	 * @return string
	 */
	public static function exam_form_action(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/**
	 * Nonce del formulario que abre un examen.
	 *
	 * @param int $exam_id Examen.
	 * @return string
	 */
	public static function exam_open_nonce( int $exam_id ): string {
		return wp_create_nonce( self::ACTION_EXAM_OPEN . '_' . $exam_id );
	}

	/**
	 * Nonce del formulario que envía un intento.
	 *
	 * @param int $attempt_id Intento.
	 * @return string
	 */
	public static function exam_submit_nonce( int $attempt_id ): string {
		return wp_create_nonce( self::ACTION_EXAM_SUBMIT . '_' . $attempt_id );
	}

	/**
	 * Abre un intento de examen.
	 *
	 * @return void
	 */
	public static function handle_exam_open(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$exam_id = isset( $_POST['exam_id'] ) ? absint( wp_unslash( (string) $_POST['exam_id'] ) ) : 0;
		$nonce   = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_EXAM_OPEN . '_' . $exam_id ) ) {
			self::redirect_back( 0, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			self::redirect_to_login( '' );
			return;
		}

		$result = Attempts::open( get_current_user_id(), $exam_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( CPT_Examen::course_id( $exam_id ), self::exam_error_key( $result->get_error_code() ), $result->get_error_code() );
			return;
		}

		self::back_with( array( 'convoca_intento' => (int) $result ), CPT_Examen::course_id( $exam_id ) );
	}

	/**
	 * Envía un intento y lo corrige en el servidor.
	 *
	 * El tiempo y el número de intentos no se negocian aquí: los decide `Attempts`. Este método solo
	 * recoge lo que llegó.
	 *
	 * @return void
	 */
	public static function handle_exam_submit(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( (string) $_POST['attempt_id'] ) ) : 0;
		$nonce      = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		$entrantes  = isset( $_POST['convoca_respuesta'] ) && is_array( $_POST['convoca_respuesta'] ) ? wp_unslash( $_POST['convoca_respuesta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea valor a valor justo debajo.
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_EXAM_SUBMIT . '_' . $attempt_id ) ) {
			self::redirect_back( 0, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			self::redirect_to_login( '' );
			return;
		}

		$respuestas = array();
		foreach ( (array) $entrantes as $question_id => $valor ) {
			if ( is_array( $valor ) ) {
				$respuestas[ (int) $question_id ] = array_map( 'sanitize_text_field', array_map( 'strval', $valor ) );
				continue;
			}
			$respuestas[ (int) $question_id ] = sanitize_text_field( (string) $valor );
		}

		$result = Attempts::submit( $attempt_id, get_current_user_id(), $respuestas );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( 0, self::exam_error_key( $result->get_error_code() ), $result->get_error_code() );
			return;
		}

		self::back_with(
			array(
				'convoca_intento' => $attempt_id,
				'convoca_msg'     => ! empty( $result['recibido'] ) ? 'exam_sent' : 'exam_expired',
			),
			0
		);
	}

	/**
	 * URL del formulario de entrega de tareas.
	 *
	 * @return string
	 */
	public static function task_form_action(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/**
	 * Nonce del formulario de entrega de una tarea.
	 *
	 * @param int $task_id Tarea.
	 * @return string
	 */
	public static function task_nonce( int $task_id ): string {
		return wp_create_nonce( self::ACTION_TASK_SUBMIT . '_' . $task_id );
	}

	/**
	 * Nonce del formulario de corrección de una entrega.
	 *
	 * @param int $submission_id Entrega.
	 * @return string
	 */
	public static function task_grade_nonce( int $submission_id ): string {
		return wp_create_nonce( self::ACTION_TASK_GRADE . '_' . $submission_id );
	}

	/**
	 * Atiende la entrega de una tarea, con texto o fichero.
	 *
	 * El fichero va a la biblioteca de medios de WordPress: así hereda las comprobaciones de tipo y
	 * tamaño del sitio, en vez de tener un control propio que se olvida de una.
	 *
	 * @return void
	 */
	public static function handle_task_submit(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$task_id   = isset( $_POST['task_id'] ) ? absint( wp_unslash( (string) $_POST['task_id'] ) ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( (string) $_POST['course_id'] ) ) : 0;
		$texto     = isset( $_POST['convoca_entrega'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_entrega'] ) ) : '';
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_TASK_SUBMIT . '_' . $task_id ) ) {
			self::redirect_back( $course_id, 'nonce' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			self::redirect_to_login( '' );
			return;
		}

		$adjunto = 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- el nonce se verificó arriba y el fichero lo valida media_handle_upload().
		if ( isset( $_FILES['convoca_adjunto'] ) && ! empty( $_FILES['convoca_adjunto']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$subido = media_handle_upload( 'convoca_adjunto', 0 );

			if ( is_wp_error( $subido ) ) {
				self::redirect_back( $course_id, 'task_file', $subido->get_error_code() );
				return;
			}

			$adjunto = (int) $subido;
		}

		$result = Task::deliver( $task_id, get_current_user_id(), $texto, $adjunto );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( $course_id, 'task_error', $result->get_error_code() );
			return;
		}

		self::back_with( array( 'convoca_msg' => 'task_sent' ), $course_id );
	}

	/**
	 * Corrige una entrega desde el panel.
	 *
	 * @return void
	 */
	public static function handle_task_grade(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( (string) $_POST['submission_id'] ) ) : 0;
		$course_id     = isset( $_POST['course_id'] ) ? absint( wp_unslash( (string) $_POST['course_id'] ) ) : 0;
		$nota          = isset( $_POST['convoca_nota'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_nota'] ) ) : 0.0;
		$comentario    = isset( $_POST['convoca_comentario'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_comentario'] ) ) : '';
		$nonce         = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_TASK_GRADE . '_' . $submission_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Pro::can_grade() ) {
			wp_die( esc_html__( 'No tienes permiso para corregir entregas.', 'convoca-courses' ) );
		}

		$result = Task::grade( $submission_id, $nota, $comentario, get_current_user_id() );

		$aviso = is_wp_error( $result ) ? 'task_error' : 'task_graded';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'convoca-courses-entregas',
					'course_id'   => $course_id,
					'convoca_msg' => $aviso,
				),
				admin_url( 'edit.php?post_type=' . CPT_Curso::TYPE )
			)
		);
		exit;
	}

	/**
	 * URL de descarga del acta en CSV.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	public static function gradebook_csv_url( int $course_id ): string {
		return esc_url(
			add_query_arg(
				array(
					'action'         => self::ACTION_GRADEBOOK_CSV,
					'course_id'      => $course_id,
					'_convoca_nonce' => wp_create_nonce( self::ACTION_GRADEBOOK_CSV . '_' . $course_id ),
				),
				admin_url( 'admin-post.php' )
			)
		);
	}

	/**
	 * Descarga el acta del curso en CSV.
	 *
	 * @return void
	 */
	public static function handle_gradebook_csv(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el nonce se verifica justo debajo.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$nonce     = isset( $_GET['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, self::ACTION_GRADEBOOK_CSV . '_' . $course_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'El acta de notas solo la ve quien gestiona el curso.', 'convoca-courses' ) );
		}

		$csv = Gradebook::csv( Gradebook::rows( $course_id ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="acta-' . (int) $course_id . '.csv"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- es un CSV: escaparlo lo rompería.
		echo $csv;
		exit;
	}

	/**
	 * Manda el correo al grupo de alumnado de un curso.
	 *
	 * @return void
	 */
	public static function handle_group_email(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( (string) $_POST['course_id'] ) ) : 0;
		$subject   = isset( $_POST['convoca_asunto'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_asunto'] ) ) : '';
		$body      = isset( $_POST['convoca_mensaje'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_mensaje'] ) ) : '';
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_GROUP_EMAIL . '_' . $course_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Pro::can_grade() ) {
			wp_die( esc_html__( 'No tienes permiso para escribir al alumnado.', 'convoca-courses' ) );
		}

		$resultado = Group_Email::send( $course_id, $subject, $body );

		self::back_to_entregas( $course_id, is_wp_error( $resultado ) ? 'group_error' : 'group_sent' );
	}

	/**
	 * Exporta el banco de preguntas en CSV.
	 *
	 * @return void
	 */
	public static function handle_bank_export(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el nonce se verifica justo debajo.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$exam_id   = isset( $_GET['exam_id'] ) ? absint( wp_unslash( (string) $_GET['exam_id'] ) ) : 0;
		$nonce     = isset( $_GET['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, self::ACTION_BANK_EXPORT . '_' . $course_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permiso para exportar el banco de preguntas.', 'convoca-courses' ) );
		}

		$csv = Shared_Bank::export_csv( $course_id, $exam_id );

		if ( is_wp_error( $csv ) ) {
			wp_die( esc_html( $csv->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="preguntas-' . (int) $course_id . '.csv"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- es un CSV: escaparlo lo rompería.
		echo $csv;
		exit;
	}

	/**
	 * Importa el banco de preguntas desde un CSV.
	 *
	 * @return void
	 */
	public static function handle_bank_import(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( (string) $_POST['course_id'] ) ) : 0;
		$exam_id   = isset( $_POST['exam_id'] ) ? absint( wp_unslash( (string) $_POST['exam_id'] ) ) : 0;
		$csv       = isset( $_POST['convoca_csv'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_csv'] ) ) : '';
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_BANK_IMPORT . '_' . $exam_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permiso para importar preguntas.', 'convoca-courses' ) );
		}

		$resultado = Shared_Bank::import_csv( $course_id, $exam_id, $csv );

		$aviso = is_wp_error( $resultado ) ? 'bank_error' : 'bank_imported';

		wp_safe_redirect(
			add_query_arg(
				array(
					'convoca_msg'    => $aviso,
					'convoca_detail' => is_wp_error( $resultado ) ? $resultado->get_error_message() : (string) $resultado['creadas'],
				),
				(string) get_edit_post_link( $exam_id > 0 ? $exam_id : $course_id, 'raw' )
			)
		);
		exit;
	}

	/**
	 * Vuelve a la pantalla de entregas con un aviso.
	 *
	 * @param int    $course_id Curso.
	 * @param string $aviso     Clave del aviso.
	 * @return void
	 */
	private static function back_to_entregas( int $course_id, string $aviso ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'convoca-courses-entregas',
					'course_id'   => $course_id,
					'convoca_msg' => $aviso,
				),
				admin_url( 'edit.php?post_type=' . CPT_Curso::TYPE )
			)
		);
		exit;
	}

	/**
	 * Clave de aviso para un error del examen.
	 *
	 * @param string $code Código de error.
	 * @return string
	 */
	private static function exam_error_key( string $code ): string {
		switch ( $code ) {
			case 'convoca_courses_exam_locked':
				return 'exam_locked';
			case 'convoca_courses_no_attempts':
				return 'exam_no_attempts';
			case 'convoca_courses_exam_empty':
				return 'exam_empty';
			default:
				return 'exam_error';
		}
	}

	/**
	 * Vuelve a la página de origen añadiendo parámetros propios.
	 *
	 * @param array<string, mixed> $args Parámetros a añadir.
	 * @param int                  $course_id Curso de respaldo.
	 * @return void
	 */
	private static function back_with( array $args, int $course_id ): void {
		$referer = wp_get_referer();
		$target  = $referer ? (string) wp_validate_redirect( $referer, '' ) : '';

		if ( '' === $target ) {
			$target = $course_id > 0 ? (string) get_permalink( $course_id ) : home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( $args, $target ) );
		exit;
	}

	/**
	 * Manda al inicio de sesión volviendo a la página actual.
	 *
	 * @param string $target Destino posterior; vacío = el referente.
	 * @return void
	 */
	private static function redirect_to_login( string $target ): void {
		if ( '' === $target ) {
			$referer = wp_get_referer();
			$target  = $referer ? (string) wp_validate_redirect( $referer, home_url( '/' ) ) : home_url( '/' );
		}

		wp_safe_redirect( wp_login_url( $target ) );
		exit;
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
			case 'exam_sent':
				return __( 'Examen enviado y corregido.', 'convoca-courses' );
			case 'exam_expired':
				return __( 'Se ha pasado el tiempo del examen: el intento se ha cerrado sin corregir.', 'convoca-courses' );
			case 'exam_locked':
				return __( 'El examen todavía no está abierto: completa antes las lecciones que pide el curso.', 'convoca-courses' );
			case 'exam_no_attempts':
				return __( 'Has agotado los intentos de este examen.', 'convoca-courses' );
			case 'exam_empty':
				return __( 'El examen no tiene preguntas todavía.', 'convoca-courses' );
			case 'exam_error':
				return __( 'No se ha podido completar la operación con el examen.', 'convoca-courses' );
			case 'task_sent':
				return __( 'Tarea entregada.', 'convoca-courses' );
			case 'task_graded':
				return __( 'Entrega corregida y alumno avisado.', 'convoca-courses' );
			case 'task_file':
				return __( 'No se ha podido subir el fichero: revisa el tipo y el tamaño que admite el sitio.', 'convoca-courses' );
			case 'task_error':
				return __( 'No se ha podido completar la operación con la tarea.', 'convoca-courses' );
			case 'group_sent':
				return __( 'Mensaje enviado al alumnado del curso.', 'convoca-courses' );
			case 'group_error':
				return __( 'No se ha podido enviar el mensaje al alumnado.', 'convoca-courses' );
			case 'bank_imported':
				return __( 'Preguntas importadas.', 'convoca-courses' );
			case 'bank_error':
				return __( 'No se ha podido importar el banco de preguntas.', 'convoca-courses' );
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
