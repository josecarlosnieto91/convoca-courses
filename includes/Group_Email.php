<?php
/**
 * Correo al grupo de alumnado de un curso (PRO).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aviso a todo el alumnado de un curso.
 *
 * Se manda persona a persona por la cola del ecosistema (no con un `Bcc` a pelo): así cada envío
 * queda registrado y un fallo se apunta como pendiente en vez de perderse.
 */
class Group_Email {

	/**
	 * ¿Se puede usar?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'correo_grupo' );
	}

	/**
	 * Manda un correo a todo el alumnado del curso.
	 *
	 * @param int    $course_id Curso.
	 * @param string $subject   Asunto.
	 * @param string $body      Cuerpo.
	 * @return int|\WP_Error Cuántos salieron, o error si la puerta está cerrada.
	 */
	public static function send( int $course_id, string $subject, string $body ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'convoca_courses_pro_required', __( 'El correo al grupo es una función PRO.', 'convoca-courses' ) );
		}

		if ( $course_id <= 0 ) {
			return new \WP_Error( 'convoca_courses_no_course', __( 'El curso no existe.', 'convoca-courses' ) );
		}

		$subject = trim( $subject );
		$body    = trim( $body );

		if ( '' === $subject || '' === $body ) {
			return new \WP_Error( 'convoca_courses_empty_mail', __( 'Hacen falta asunto y mensaje.', 'convoca-courses' ) );
		}

		$enviados = 0;

		foreach ( Roster::students( $course_id ) as $alumno ) {
			if ( Notifications::group_message( (int) $alumno['user_id'], $course_id, $subject, $body ) ) {
				++$enviados;
			}
		}

		return $enviados;
	}
}
