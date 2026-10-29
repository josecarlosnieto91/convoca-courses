<?php
/**
 * Progreso del alumnado: tabla propia, no postmeta.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura y escritura del progreso por lección.
 *
 * El progreso se consulta por persona y por curso muchas veces; en `postmeta` eso es un escaneo,
 * así que vive en su propia tabla con índice único (user_id, lesson_id).
 */
class Progress {

	/**
	 * Marca una lección como completada. Idempotente.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function complete( int $user_id, int $course_id, int $lesson_id ): bool {
		global $wpdb;

		if ( $user_id <= 0 || $course_id <= 0 || $lesson_id <= 0 ) {
			return false;
		}

		$table = Installer::progress_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin, sin API de WP para ella.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- el nombre de tabla es propio y no puede ir como marcador; los valores van preparados.
				"INSERT IGNORE INTO {$table} (course_id, user_id, lesson_id, completed_at) VALUES (%d, %d, %d, %s)",
				$course_id,
				$user_id,
				$lesson_id,
				current_time( 'mysql' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return false !== $result;
	}

	/**
	 * Marca una lección como completada por una identidad (alumnado sin cuenta).
	 *
	 * @param int $identidad_id Identidad.
	 * @param int $course_id    Curso.
	 * @param int $lesson_id    Lección.
	 * @return bool
	 */
	public static function complete_identidad( int $identidad_id, int $course_id, int $lesson_id ): bool {
		global $wpdb;

		if ( $identidad_id <= 0 || $course_id <= 0 || $lesson_id <= 0 ) {
			return false;
		}

		$table = Installer::progress_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin, sin API de WP para ella.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- el nombre de tabla es propio y no puede ir como marcador; los valores van preparados.
				"INSERT IGNORE INTO {$table} (course_id, user_id, identidad_id, lesson_id, completed_at) VALUES (%d, 0, %d, %d, %s)",
				$course_id,
				$identidad_id,
				$lesson_id,
				current_time( 'mysql' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return false !== $result;
	}

	/**
	 * ¿Ha completado la identidad esa lección?
	 *
	 * @param int $identidad_id Identidad.
	 * @param int $lesson_id    Lección.
	 * @return bool
	 */
	public static function is_completed_identidad( int $identidad_id, int $lesson_id ): bool {
		return in_array( $lesson_id, self::completed_lesson_ids_identidad( $identidad_id ), true );
	}

	/**
	 * Lecciones completadas por una identidad.
	 *
	 * @param int $identidad_id Identidad.
	 * @param int $course_id    Curso; 0 para todas.
	 * @return array<int, int>
	 */
	public static function completed_lesson_ids_identidad( int $identidad_id, int $course_id = 0 ): array {
		global $wpdb;

		if ( $identidad_id <= 0 ) {
			return array();
		}

		$table = Installer::progress_table();

		if ( $course_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabla propia.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT lesson_id FROM {$table} WHERE identidad_id = %d AND course_id = %d", $identidad_id, $course_id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabla propia.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT lesson_id FROM {$table} WHERE identidad_id = %d", $identidad_id ) );
		}

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Retira la marca de completada.
	 *
	 * @param int $user_id   Persona.
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function uncomplete( int $user_id, int $lesson_id ): bool {
		global $wpdb;

		$table = Installer::progress_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin, sin API de WP para ella.
		$result = $wpdb->delete(
			$table,
			array(
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			),
			array( '%d', '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return false !== $result;
	}

	/**
	 * ¿Está completada esa lección?
	 *
	 * @param int $user_id   Persona.
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function is_completed( int $user_id, int $lesson_id ): bool {
		return in_array( $lesson_id, self::completed_lesson_ids( $user_id, 0 ), true );
	}

	/**
	 * Identificadores de lecciones completadas por una persona.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso; 0 para todas.
	 * @return array<int, int>
	 */
	public static function completed_lesson_ids( int $user_id, int $course_id = 0 ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return array();
		}

		$table = Installer::progress_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla propio, ids preparados.
		if ( $course_id > 0 ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT lesson_id FROM {$table} WHERE user_id = %d AND course_id = %d",
					$user_id,
					$course_id
				)
			);
		} else {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT lesson_id FROM {$table} WHERE user_id = %d",
					$user_id
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Cuenta de lecciones completadas en un curso.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function completed_count( int $user_id, int $course_id ): int {
		global $wpdb;

		if ( $user_id <= 0 || $course_id <= 0 ) {
			return 0;
		}

		$table = Installer::progress_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla propio, ids preparados.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Progreso de una persona en un curso.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return array{done: int, total: int, percent: float}
	 */
	public static function for_course( int $user_id, int $course_id ): array {
		$total = count( CPT_Leccion::of_course( $course_id ) );
		$done  = self::completed_count( $user_id, $course_id );

		return array(
			'done'    => min( $done, $total ),
			'total'   => $total,
			'percent' => self::percent( $done, $total ),
		);
	}

	/**
	 * Porcentaje de avance, sin tocar WordPress: se puede probar a pelo.
	 *
	 * @param int $done  Lecciones completadas.
	 * @param int $total Lecciones totales.
	 * @return float
	 */
	public static function percent( int $done, int $total ): float {
		if ( $total <= 0 ) {
			return 0.0;
		}
		$done = max( 0, min( $done, $total ) );
		return round( ( $done / $total ) * 100, 1 );
	}

	/**
	 * Borra el progreso de una lección (al borrarla definitivamente).
	 *
	 * @param int $lesson_id Lección.
	 * @return void
	 */
	public static function delete_for_lesson( int $lesson_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$wpdb->delete( Installer::progress_table(), array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Borra el progreso de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return void
	 */
	public static function delete_for_course( int $course_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$wpdb->delete( Installer::progress_table(), array( 'course_id' => $course_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}
}
