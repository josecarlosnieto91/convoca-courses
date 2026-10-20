<?php
/**
 * Acceso del alumnado a cursos y lecciones.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reglas de acceso: matrícula y desbloqueo secuencial.
 */
class Access {

	/**
	 * ¿La persona está matriculada y puede entrar al curso?
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function is_enrolled( int $user_id, int $course_id ): bool {
		return Enrollment::active_id( $user_id, $course_id ) > 0;
	}

	/**
	 * ¿La persona gestiona el módulo?
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'convoca_manage_courses' ) || current_user_can( 'manage_options' );
	}

	/**
	 * ¿Puede ver el contenido de la lección?
	 *
	 * @param int $user_id   Persona.
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function can_view_lesson( int $user_id, int $lesson_id ): bool {
		$course_id = Hierarchy::course_of_lesson( $lesson_id );
		if ( 0 === $course_id ) {
			return false;
		}

		if ( self::can_manage() ) {
			return true;
		}

		if ( ! self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}

		return self::is_unlocked( $user_id, $course_id, $lesson_id );
	}

	/**
	 * ¿Está desbloqueada la lección según el orden del curso?
	 *
	 * Sin desbloqueo secuencial, todas están abiertas. Con él, una lección se abre cuando todas las
	 * anteriores están completadas.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function is_unlocked( int $user_id, int $course_id, int $lesson_id ): bool {
		if ( ! CPT_Curso::is_sequential( $course_id ) ) {
			return true;
		}

		$done = Progress::completed_lesson_ids( $user_id, $course_id );

		foreach ( CPT_Leccion::of_course( $course_id ) as $lesson ) {
			if ( (int) $lesson->ID === $lesson_id ) {
				return true;
			}
			if ( ! in_array( (int) $lesson->ID, $done, true ) ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Progreso mostrado al alumnado, con el estado de matrícula resuelto.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return array{enrolled: bool, done: int, total: int, percent: float}
	 */
	public static function summary( int $user_id, int $course_id ): array {
		$progress = Progress::for_course( $user_id, $course_id );

		return array(
			'enrolled' => self::is_enrolled( $user_id, $course_id ),
			'done'     => $progress['done'],
			'total'    => $progress['total'],
			'percent'  => $progress['percent'],
		);
	}
}
