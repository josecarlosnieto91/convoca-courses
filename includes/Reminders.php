<?php
/**
 * Recordatorios automáticos (PRO).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Avisos que salen solos: inactividad y examen pendiente.
 *
 * Es PRO porque es trabajo de secretaría automatizado. Sin licencia no se programa nada: no se
 * envía, no se agenda y no queda un cron huérfano dando vueltas.
 */
class Reminders {

	/**
	 * Gancho del cron.
	 */
	public const HOOK = 'convoca_courses_reminders';

	/**
	 * Opción con el registro de avisos ya mandados (para no repetir).
	 */
	public const SENT_OPTION = 'convoca_courses_reminders_sent';

	/**
	 * Días sin actividad para el aviso de inactividad.
	 */
	public const INACTIVE_DAYS = 7;

	/**
	 * Días mínimos entre dos avisos de la misma regla a la misma persona.
	 */
	public const COOLDOWN_DAYS = 7;

	/**
	 * ¿Se puede usar?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'recordatorios' );
	}

	/**
	 * Engancha y programa el cron si toca.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! self::enabled() ) {
			self::unschedule();
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Quita el cron (al desactivar la licencia o el módulo).
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$scheduled = wp_next_scheduled( self::HOOK );
		if ( false !== $scheduled ) {
			wp_unschedule_event( (int) $scheduled, self::HOOK );
		}
	}

	/**
	 * Pasa por todos los cursos y manda lo que toque.
	 *
	 * @return int Avisos enviados.
	 */
	public static function run(): int {
		if ( ! self::enabled() ) {
			return 0;
		}

		$enviados = 0;

		$cursos = get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
			)
		);

		foreach ( $cursos as $course_id ) {
			$enviados += self::run_course( (int) $course_id );
		}

		return $enviados;
	}

	/**
	 * Recordatorios de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function run_course( int $course_id ): int {
		$enviados = 0;

		foreach ( Roster::students( $course_id ) as $alumno ) {
			$user_id  = (int) $alumno['user_id'];
			$progreso = (float) $alumno['progreso'];

			if ( $progreso < 100.0 && self::should_remind_inactive( $user_id, $course_id ) ) {
				if ( Notifications::reminder_inactive( $user_id, $course_id ) ) {
					self::mark_sent( $user_id, $course_id, 'inactividad' );
					++$enviados;
				}
				continue;
			}

			if ( 100.0 <= $progreso && self::should_remind_exam( $user_id, $course_id ) ) {
				if ( Notifications::reminder_exam( $user_id, $course_id ) ) {
					self::mark_sent( $user_id, $course_id, 'examen' );
					++$enviados;
				}
			}
		}

		return $enviados;
	}

	/**
	 * ¿Toca avisar de inactividad?
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function should_remind_inactive( int $user_id, int $course_id ): bool {
		if ( self::recently_sent( $user_id, $course_id, 'inactividad' ) ) {
			return false;
		}

		$ultima = self::last_activity( $user_id, $course_id );
		if ( $ultima <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = (int) current_time( 'timestamp' );

		return ( $ahora - $ultima ) >= ( self::INACTIVE_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * ¿Toca avisar de examen pendiente?
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function should_remind_exam( int $user_id, int $course_id ): bool {
		if ( self::recently_sent( $user_id, $course_id, 'examen' ) ) {
			return false;
		}

		foreach ( CPT_Examen::of_course( $course_id ) as $examen ) {
			if ( ! Attempts::has_passed( $user_id, (int) $examen->ID ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Última actividad conocida de una persona en un curso.
	 *
	 * La marca más reciente entre progreso e intentos; si no hay ninguna, la fecha de matrícula.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return int Marca de tiempo (0 si no hay datos).
	 */
	public static function last_activity( int $user_id, int $course_id ): int {
		global $wpdb;

		$tabla_progreso = Installer::progress_table();
		$tabla_intentos = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablas propias, valores preparados.
		$progreso = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(completed_at) FROM {$tabla_progreso} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);

		$intento = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(iniciado_en) FROM {$tabla_intentos} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$marcas = array_filter(
			array(
				is_string( $progreso ) ? strtotime( $progreso ) : false,
				is_string( $intento ) ? strtotime( $intento ) : false,
			)
		);

		if ( ! empty( $marcas ) ) {
			return (int) max( $marcas );
		}

		$matricula = Enrollment::active_id( $user_id, $course_id );
		if ( $matricula > 0 ) {
			return (int) get_post_time( 'U', true, $matricula );
		}

		return 0;
	}

	/**
	 * ¿Se avisó de esta regla hace poco?
	 *
	 * @param int    $user_id   Persona.
	 * @param int    $course_id Curso.
	 * @param string $regla     Regla.
	 * @return bool
	 */
	public static function recently_sent( int $user_id, int $course_id, string $regla ): bool {
		$registro = get_option( self::SENT_OPTION, array() );
		if ( ! is_array( $registro ) ) {
			return false;
		}

		$clave = $user_id . ':' . $course_id . ':' . $regla;
		if ( ! isset( $registro[ $clave ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = (int) current_time( 'timestamp' );

		return ( $ahora - (int) $registro[ $clave ] ) < ( self::COOLDOWN_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Apunta que se avisó.
	 *
	 * @param int    $user_id   Persona.
	 * @param int    $course_id Curso.
	 * @param string $regla     Regla.
	 * @return void
	 */
	private static function mark_sent( int $user_id, int $course_id, string $regla ): void {
		$registro = get_option( self::SENT_OPTION, array() );
		if ( ! is_array( $registro ) ) {
			$registro = array();
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- marca de tiempo guardada.
		$registro[ $user_id . ':' . $course_id . ':' . $regla ] = (int) current_time( 'timestamp' );

		// Tope de 500 entradas: el registro no puede crecer sin fin en una opción.
		if ( count( $registro ) > 500 ) {
			$registro = array_slice( $registro, -500, null, true );
		}

		update_option( self::SENT_OPTION, $registro, false );
	}
}
