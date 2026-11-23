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
			$user_id   = (int) $alumno['user_id'];
			$identidad = (int) $alumno['identidad'];

			// Una fila sin usuario ni identidad no tiene destinatario: no es un alumno al que avisar
			// (courses#50). El alumnado por identidad sí lo es y se avisa por su correo.
			if ( $user_id <= 0 && $identidad <= 0 ) {
				continue;
			}

			$progreso = (float) $alumno['progreso'];

			if ( $progreso < 100.0 && self::should_remind_inactive( $user_id, $course_id, $identidad ) ) {
				if ( Notifications::reminder_inactive( $user_id, $course_id, $identidad ) ) {
					self::mark_sent( $user_id, $course_id, 'inactividad', $identidad );
					++$enviados;
				}
				continue;
			}

			if ( 100.0 <= $progreso && self::should_remind_exam( $user_id, $course_id, $identidad ) ) {
				if ( Notifications::reminder_exam( $user_id, $course_id, $identidad ) ) {
					self::mark_sent( $user_id, $course_id, 'examen', $identidad );
					++$enviados;
				}
			}
		}

		return $enviados;
	}

	/**
	 * ¿Toca avisar de inactividad?
	 *
	 * @param int $user_id      Persona.
	 * @param int $course_id    Curso.
	 * @param int $identidad_id Identidad cuando no hay usuario de WordPress (0 si va con usuario).
	 * @return bool
	 */
	public static function should_remind_inactive( int $user_id, int $course_id, int $identidad_id = 0 ): bool {
		if ( self::recently_sent( $user_id, $course_id, 'inactividad', $identidad_id ) ) {
			return false;
		}

		$ultima = self::last_activity( $user_id, $course_id, $identidad_id );
		if ( $ultima <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = Fechas::ahora();

		return ( $ahora - $ultima ) >= ( self::INACTIVE_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * ¿Toca avisar de examen pendiente?
	 *
	 * @param int $user_id      Persona.
	 * @param int $course_id    Curso.
	 * @param int $identidad_id Identidad cuando no hay usuario de WordPress (0 si va con usuario).
	 * @return bool
	 */
	public static function should_remind_exam( int $user_id, int $course_id, int $identidad_id = 0 ): bool {
		if ( self::recently_sent( $user_id, $course_id, 'examen', $identidad_id ) ) {
			return false;
		}

		foreach ( CPT_Examen::of_course( $course_id ) as $examen ) {
			if ( ! Attempts::has_passed( $user_id, (int) $examen->ID, $identidad_id ) ) {
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
	 * @param int $user_id      Persona.
	 * @param int $course_id    Curso.
	 * @param int $identidad_id Identidad cuando no hay usuario de WordPress (0 si va con usuario).
	 * @return int Marca de tiempo (0 si no hay datos).
	 */
	public static function last_activity( int $user_id, int $course_id, int $identidad_id = 0 ): int {
		global $wpdb;

		if ( $user_id <= 0 && $identidad_id <= 0 ) {
			return 0;
		}

		$tabla_progreso = Installer::progress_table();
		$tabla_intentos = Installer::table( Installer::ATTEMPTS_TABLE );
		$dueno          = $identidad_id > 0 ? 'identidad_id' : 'user_id';
		$quien          = $identidad_id > 0 ? $identidad_id : $user_id;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablas propias, valores preparados.
		$progreso = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(completed_at) FROM {$tabla_progreso} WHERE {$dueno} = %d AND course_id = %d",
				$quien,
				$course_id
			)
		);

		$intento = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(iniciado_en) FROM {$tabla_intentos} WHERE {$dueno} = %d AND course_id = %d",
				$quien,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$marcas = array_filter(
			array(
				is_string( $progreso ) ? Fechas::a_timestamp( $progreso ) : false,
				is_string( $intento ) ? Fechas::a_timestamp( $intento ) : false,
			)
		);

		if ( ! empty( $marcas ) ) {
			return (int) max( $marcas );
		}

		$matricula = $identidad_id > 0
			? Enrollment::find_por_identidad( $identidad_id, $course_id, true )
			: Enrollment::active_id( $user_id, $course_id );

		if ( $matricula > 0 ) {
			return (int) get_post_time( 'U', true, $matricula );
		}

		return 0;
	}

	/**
	 * ¿Se avisó de esta regla hace poco?
	 *
	 * @param int    $user_id      Persona.
	 * @param int    $course_id    Curso.
	 * @param string $regla        Regla.
	 * @param int    $identidad_id Identidad cuando no hay usuario (0 si va con usuario).
	 * @return bool
	 */
	public static function recently_sent( int $user_id, int $course_id, string $regla, int $identidad_id = 0 ): bool {
		$registro = get_option( self::SENT_OPTION, array() );
		if ( ! is_array( $registro ) ) {
			return false;
		}

		$clave = self::clave( $user_id, $course_id, $regla, $identidad_id );
		if ( ! isset( $registro[ $clave ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = Fechas::ahora();

		return ( $ahora - (int) $registro[ $clave ] ) < ( self::COOLDOWN_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Apunta que se avisó.
	 *
	 * @param int    $user_id      Persona.
	 * @param int    $course_id    Curso.
	 * @param string $regla        Regla.
	 * @param int    $identidad_id Identidad cuando no hay usuario (0 si va con usuario).
	 * @return void
	 */
	private static function mark_sent( int $user_id, int $course_id, string $regla, int $identidad_id = 0 ): void {
		$registro = get_option( self::SENT_OPTION, array() );
		if ( ! is_array( $registro ) ) {
			$registro = array();
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- marca de tiempo guardada.
		$registro[ self::clave( $user_id, $course_id, $regla, $identidad_id ) ] = Fechas::ahora();

		// Tope de 500 entradas: el registro no puede crecer sin fin en una opción.
		if ( count( $registro ) > 500 ) {
			$registro = array_slice( $registro, -500, null, true );
		}

		update_option( self::SENT_OPTION, $registro, false );
	}

	/**
	 * Clave del registro de avisos: distingue usuario de identidad.
	 *
	 * El alumnado por identidad no tiene `user_id`, y usar `0` mezclaría a todas las personas sin
	 * cuenta en una sola clave: la primera que recibiera el aviso lo apagaría para las demás (courses#50).
	 *
	 * @param int    $user_id      Persona.
	 * @param int    $course_id    Curso.
	 * @param string $regla        Regla.
	 * @param int    $identidad_id Identidad cuando no hay usuario.
	 * @return string
	 */
	private static function clave( int $user_id, int $course_id, string $regla, int $identidad_id ): string {
		$quien = $user_id > 0 ? (string) $user_id : 'i' . $identidad_id;

		return $quien . ':' . $course_id . ':' . $regla;
	}
}
