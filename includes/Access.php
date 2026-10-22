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

		// Curso caducado = solo lectura: puede volver a ver lo que ya había completado, nada más.
		if ( self::is_expired( $user_id, $course_id ) ) {
			return Progress::is_completed( $user_id, $lesson_id );
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

	/**
	 * ¿La ventana de matrícula está abierta ahora?
	 *
	 * Pura: recibe marcas de tiempo. Cero significa «sin límite por ese lado».
	 *
	 * @param int $apertura Desde cuándo se puede matricular (0 = sin límite).
	 * @param int $cierre   Hasta cuándo se puede matricular (0 = sin límite).
	 * @param int $ahora    Momento de la comprobación.
	 * @return bool
	 */
	public static function enrollment_window_open( int $apertura, int $cierre, int $ahora ): bool {
		if ( $apertura > 0 && $ahora < $apertura ) {
			return false;
		}

		if ( $cierre > 0 && $ahora > $cierre ) {
			return false;
		}

		return true;
	}

	/**
	 * ¿Ha caducado el acceso?
	 *
	 * Pura: recibe marcas de tiempo. Sin días de caducidad, el acceso no caduca nunca.
	 *
	 * @param int $inicio Cuándo empezó el acceso (matrícula).
	 * @param int $dias   Días de acceso; 0 o menos = indefinido.
	 * @param int $ahora  Momento de la comprobación.
	 * @return bool
	 */
	public static function access_expired( int $inicio, int $dias, int $ahora ): bool {
		if ( $dias <= 0 || $inicio <= 0 ) {
			return false;
		}

		return $ahora >= $inicio + ( $dias * DAY_IN_SECONDS );
	}

	/**
	 * Porcentaje de lecciones que hay que tener completadas para abrir el examen.
	 *
	 * Pura.
	 *
	 * @param string $modo       `todas`, `modulo` o `porcentaje`.
	 * @param int    $porcentaje Porcentaje configurado (solo se usa en modo `porcentaje`).
	 * @return float
	 */
	public static function required_percent( string $modo, int $porcentaje ): float {
		if ( 'porcentaje' === $modo ) {
			return (float) max( 0, min( 100, $porcentaje ) );
		}

		// `todas` y `modulo` exigen el 100 % de su ámbito: el curso entero o las lecciones del módulo.
		return 100.0;
	}

	/**
	 * ¿El progreso alcanzado permite abrir el examen?
	 *
	 * Pura: quien llama decide sobre qué ámbito se mide el porcentaje.
	 *
	 * @param string $modo       `todas`, `modulo` o `porcentaje`.
	 * @param int    $porcentaje Porcentaje configurado.
	 * @param float  $real       Porcentaje completado en el ámbito que toca.
	 * @return bool
	 */
	public static function progress_satisfied( string $modo, int $porcentaje, float $real ): bool {
		return $real + 0.0001 >= self::required_percent( $modo, $porcentaje );
	}

	/**
	 * ¿Se puede matricular ahora mismo en este curso?
	 *
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function is_enrollment_open( int $course_id ): bool {
		$apertura = CPT_Curso::enrollment_open_date( $course_id );
		$cierre   = CPT_Curso::enrollment_close_date( $course_id );

		$desde = '' !== $apertura ? (int) strtotime( $apertura . ' 00:00:00' ) : 0;
		$hasta = '' !== $cierre ? (int) strtotime( $cierre . ' 23:59:59' ) : 0;

		return self::enrollment_window_open( $desde, $hasta, (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo, no fecha mostrada.
	}

	/**
	 * ¿Ha caducado el acceso de esta persona a este curso?
	 *
	 * Dos formas de caducar, y manda la primera que llegue: fecha de fin del curso, o días de acceso
	 * contados desde la matrícula. Los cursos sin ninguna de las dos son indefinidos.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function is_expired( int $user_id, int $course_id ): bool {
		$enrollment_id = Enrollment::active_id( $user_id, $course_id );
		if ( $enrollment_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = (int) current_time( 'timestamp' );

		$fin = CPT_Curso::end_date( $course_id );
		if ( '' !== $fin ) {
			$hasta = (int) strtotime( $fin . ' 23:59:59' );
			if ( $hasta > 0 && $ahora > $hasta ) {
				return true;
			}
		}

		$dias = CPT_Curso::expiry_days( $course_id );
		if ( $dias <= 0 ) {
			return false;
		}

		$inicio = (int) get_post_time( 'U', true, $enrollment_id );

		return self::access_expired( $inicio, $dias, $ahora );
	}

	/**
	 * ¿Puede esta persona abrir el examen de este curso ahora?
	 *
	 * Todo se decide aquí, en el servidor: matrícula, caducidad y progreso. La interfaz solo enseña
	 * lo que esta función permite.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @param int $module_id Módulo del examen (0 si el examen es del curso entero).
	 * @return bool
	 */
	public static function can_take_exam( int $user_id, int $course_id, int $module_id = 0 ): bool {
		if ( self::can_manage() ) {
			return true;
		}

		if ( ! self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}

		if ( self::is_expired( $user_id, $course_id ) ) {
			return false;
		}

		$modo       = CPT_Curso::exam_access( $course_id );
		$porcentaje = CPT_Curso::exam_access_percent( $course_id );
		$real       = self::scope_percent( $user_id, $course_id, 'modulo' === $modo ? $module_id : 0 );

		return self::progress_satisfied( $modo, $porcentaje, $real );
	}

	/**
	 * Porcentaje completado en un ámbito: el curso entero, o las lecciones de un módulo.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @param int $module_id Módulo (0 = curso entero).
	 * @return float
	 */
	public static function scope_percent( int $user_id, int $course_id, int $module_id = 0 ): float {
		if ( $module_id <= 0 ) {
			$progress = Progress::for_course( $user_id, $course_id );

			return (float) $progress['percent'];
		}

		$lecciones = CPT_Leccion::of_module( $module_id );
		$total     = count( $lecciones );
		if ( 0 === $total ) {
			return 100.0;
		}

		$hechas = Progress::completed_lesson_ids( $user_id, $course_id );
		$dentro = 0;
		foreach ( $lecciones as $leccion ) {
			if ( in_array( (int) $leccion->ID, $hechas, true ) ) {
				++$dentro;
			}
		}

		return Progress::percent( $dentro, $total );
	}
}
