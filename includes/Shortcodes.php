<?php
/**
 * API pública del módulo: shortcodes.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes de portada, índice y lección.
 */
class Shortcodes {

	/**
	 * Registra los shortcodes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'convoca_curso', array( __CLASS__, 'course' ) );
		add_shortcode( 'convoca_curso_lecciones', array( __CLASS__, 'lessons' ) );
		add_shortcode( 'convoca_curso_leccion', array( __CLASS__, 'lesson' ) );
	}

	/**
	 * Portada del curso.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function course( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		$user_id = get_current_user_id();
		$state   = Access::summary( $user_id, $course_id );
		$free    = Enrollment::free_seats( $course_id );

		$enrollment_id = Enrollment::find( $user_id, $course_id );
		$status        = $enrollment_id > 0 ? CPT_Matricula::status( $enrollment_id ) : '';
		$payment_url   = $enrollment_id > 0 ? Course_Payment::payment_url( $enrollment_id ) : '';
		$receipt_url   = $enrollment_id > 0 ? Course_Payment::receipt_url( $enrollment_id ) : '';

		return Certificate::render_template(
			'curso.php',
			array(
				'course_id'         => $course_id,
				'title'             => (string) get_the_title( $course_id ),
				'content'           => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $course_id ) ),
				'price'             => CPT_Curso::price( $course_id ) > 0 ? CPT_Curso::formatted_price( $course_id ) : '',
				'seats'             => $free,
				'enrolled'          => $state['enrolled'],
				'progress'          => $state,
				'certificate'       => CPT_Curso::issues_certificate( $course_id ),
				'modules'           => self::modules_data( $course_id, $user_id ),
				'logged_in'         => $user_id > 0,
				'enroll_url'        => Handlers::enroll_form_action(),
				'enroll_nonce'      => Handlers::enroll_nonce(),
				'needs_payment'     => Course_Payment::course_needs_payment( $course_id ),
				'enrollment_status' => $status,
				'payment_url'       => $payment_url,
			),
			false
		);
	}

	/**
	 * Índice de lecciones con progreso.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function lessons( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_lecciones'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		$user_id = get_current_user_id();

		return Certificate::render_template(
			'lecciones.php',
			array(
				'course_id' => $course_id,
				'title'     => (string) get_the_title( $course_id ),
				'enrolled'  => Access::is_enrolled( $user_id, $course_id ),
				'progress'  => Access::summary( $user_id, $course_id ),
				'modules'   => self::modules_data( $course_id, $user_id ),
			),
			false
		);
	}

	/**
	 * Render de una lección.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function lesson( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_leccion'
		);

		$lesson_id = (int) $atts['id'];
		if ( ! Hierarchy::is_lesson( $lesson_id ) ) {
			return self::error_box();
		}

		$user_id   = get_current_user_id();
		$course_id = Hierarchy::course_of_lesson( $lesson_id );

		if ( ! Access::can_view_lesson( $user_id, $lesson_id ) ) {
			return Certificate::render_template(
				'leccion-bloqueada.php',
				array(
					'course_id'  => $course_id,
					'title'      => (string) get_the_title( $lesson_id ),
					'enrolled'   => Access::is_enrolled( $user_id, $course_id ),
					'course_url' => $course_id > 0 ? (string) get_permalink( $course_id ) : '',
				),
				false
			);
		}

		$type       = CPT_Leccion::type( $lesson_id );
		$attachment = CPT_Leccion::get_int( $lesson_id, 'adjunto_id', 0 );
		$video      = array(
			'src'      => '',
			'external' => false,
		);

		$video_allowed = License::video_allowed();

		if ( 'video' === $type && $video_allowed ) {
			$video = Video::lesson_source( $lesson_id );
		}

		return Certificate::render_template(
			'leccion.php',
			array(
				'lesson_id'      => $lesson_id,
				'course_id'      => $course_id,
				'title'          => (string) get_the_title( $lesson_id ),
				'type'           => $type,
				'content'        => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $lesson_id ) ),
				'attachment_id'  => $attachment,
				'attachment'     => $attachment > 0 ? (string) wp_get_attachment_url( $attachment ) : '',
				'video'          => $video,
				'video_allowed'  => $video_allowed,
				'completed'      => Progress::is_completed( $user_id, $lesson_id ),
				'complete_url'   => Handlers::complete_form_action(),
				'complete_nonce' => Handlers::complete_nonce( $lesson_id ),
				'duration'       => CPT_Leccion::get_int( $lesson_id, 'duracion', 0 ),
			),
			false
		);
	}

	/**
	 * Datos de módulos y lecciones para las plantillas.
	 *
	 * @param int $course_id Curso.
	 * @param int $user_id   Persona.
	 * @return array<int, array<string, mixed>>
	 */
	public static function modules_data( int $course_id, int $user_id ): array {
		$modules = array();
		$done    = Progress::completed_lesson_ids( $user_id, $course_id );
		$index   = 0;

		foreach ( CPT_Modulo::of_course( $course_id ) as $module ) {
			$lessons = array();

			foreach ( CPT_Leccion::of_module( (int) $module->ID ) as $lesson ) {
				++$index;
				$lessons[] = array(
					'id'        => (int) $lesson->ID,
					'title'     => (string) $lesson->post_title,
					'type'      => CPT_Leccion::type( (int) $lesson->ID ),
					'duration'  => CPT_Leccion::get_int( (int) $lesson->ID, 'duracion', 0 ),
					'completed' => in_array( (int) $lesson->ID, $done, true ),
					'locked'    => ! Access::is_unlocked( $user_id, $course_id, (int) $lesson->ID ),
					'number'    => $index,
				);
			}

			$modules[] = array(
				'id'      => (int) $module->ID,
				'title'   => (string) $module->post_title,
				'content' => (string) $module->post_content,
				'lessons' => $lessons,
			);
		}

		return $modules;
	}

	/**
	 * Resuelve el curso: el atributo manda; si falta, el curso de la entrada actual.
	 *
	 * @param int $course_id Identificador del atributo.
	 * @return int
	 */
	private static function resolve_course_id( int $course_id ): int {
		if ( Hierarchy::is_course( $course_id ) ) {
			return $course_id;
		}

		$current = get_the_ID();
		if ( is_int( $current ) && Hierarchy::is_course( $current ) ) {
			return $current;
		}

		return 0;
	}

	/**
	 * Aviso cuando falta el curso o la lección.
	 *
	 * @return string
	 */
	private static function error_box(): string {
		return '<div class="convoca-courses-notice convoca-courses-notice--error">'
			. esc_html__( 'No se ha encontrado el curso o la lección indicados.', 'convoca-courses' )
			. '</div>';
	}
}
