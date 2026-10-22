<?php
/**
 * Shortcode de tareas: qué se pide, qué se ha entregado y cómo va.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Datos de las tareas de un curso para la plantilla.
 */
class Task_View {

	/**
	 * Datos completos para una persona.
	 *
	 * @param int $course_id Curso.
	 * @param int $user_id   Persona.
	 * @return array<string, mixed>
	 */
	public static function data( int $course_id, int $user_id ): array {
		$tareas = array();

		foreach ( CPT_Tarea::of_course( $course_id ) as $tarea ) {
			$task_id = (int) $tarea->ID;
			$entrega = $user_id > 0 ? Task::submission( $task_id, $user_id ) : null;
			$adjunto = null !== $entrega ? (int) $entrega['adjunto_id'] : 0;
			$plazo   = CPT_Tarea::deadline( $task_id );

			$tareas[] = array(
				'id'          => $task_id,
				'title'       => (string) $tarea->post_title,
				'intro'       => (string) apply_filters( 'the_content', (string) $tarea->post_content ),
				'deadline'    => $plazo,
				'weight'      => CPT_Tarea::weight( $task_id ),
				'allows_file' => CPT_Tarea::allows_file( $task_id ),
				'submission'  => $entrega,
				'attachment'  => $adjunto > 0 ? (string) wp_get_attachment_url( $adjunto ) : '',
				'nonce'       => Handlers::task_nonce( $task_id ),
			);
		}

		return array(
			'course_id'  => $course_id,
			'title'      => $course_id > 0 ? (string) get_the_title( $course_id ) : '',
			'logged_in'  => $user_id > 0,
			'enrolled'   => $course_id > 0 && Access::is_enrolled( $user_id, $course_id ),
			'can_submit' => $course_id > 0 && Access::can_submit_task( $user_id, $course_id ),
			'tasks'      => $tareas,
			'form_url'   => Handlers::task_form_action(),
			'notice'     => self::notice(),
		);
	}

	/**
	 * Aviso pedido en la URL.
	 *
	 * @return string
	 */
	private static function notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del aviso a pintar.
		$key = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : '';

		return '' === $key ? '' : Handlers::message( $key );
	}
}
