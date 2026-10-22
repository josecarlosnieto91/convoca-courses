<?php
/**
 * Entrega de tareas y su corrección.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entregas del alumnado.
 *
 * El estado de una entrega se decide con dos datos (fecha de entrega y plazo), así que la regla es
 * pura y se prueba sin WordPress; la escritura va a la tabla de entregas.
 */
class Task {

	/**
	 * Estados posibles de una entrega.
	 */
	public const STATUSES = array( 'entregada', 'corregida', 'fuera_de_plazo' );

	/**
	 * ¿La entrega llega tarde?
	 *
	 * Función pura. Sin plazo, nunca es tarde.
	 *
	 * @param int $entregada Marca de tiempo de la entrega.
	 * @param int $plazo     Marca de tiempo del plazo (0 = sin plazo).
	 * @return bool
	 */
	public static function is_late( int $entregada, int $plazo ): bool {
		if ( $plazo <= 0 ) {
			return false;
		}

		return $entregada > $plazo;
	}

	/**
	 * Estado que le toca a una entrega.
	 *
	 * Función pura.
	 *
	 * @param int $entregada Marca de tiempo de la entrega.
	 * @param int $plazo     Marca de tiempo del plazo (0 = sin plazo).
	 * @return string
	 */
	public static function status_for( int $entregada, int $plazo ): string {
		return self::is_late( $entregada, $plazo ) ? 'fuera_de_plazo' : 'entregada';
	}

	/**
	 * Entrega de una persona en una tarea.
	 *
	 * @param int $task_id Tarea.
	 * @param int $user_id Persona.
	 * @return array<string, mixed>|null
	 */
	public static function submission( int $task_id, int $user_id ): ?array {
		global $wpdb;

		if ( $task_id <= 0 || $user_id <= 0 ) {
			return null;
		}

		$table = Installer::table( Installer::SUBMISSIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE task_id = %d AND user_id = %d",
				$task_id,
				$user_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? self::decode( $row ) : null;
	}

	/**
	 * Entrega o reentrega una tarea.
	 *
	 * Reentregar reabre la entrega y **borra la nota anterior**: lo que se corrige es lo último que
	 * hay, no una mezcla de dos versiones.
	 *
	 * @param int    $task_id     Tarea.
	 * @param int    $user_id     Persona.
	 * @param string $texto       Texto de la entrega.
	 * @param int    $adjunto_id  Adjunto de la biblioteca (0 = sin fichero).
	 * @return true|\WP_Error
	 */
	public static function deliver( int $task_id, int $user_id, string $texto, int $adjunto_id = 0 ) {
		global $wpdb;

		if ( ! Hierarchy::is_type( $task_id, CPT_Tarea::TYPE ) ) {
			return new \WP_Error( 'convoca_courses_no_task', __( 'La tarea no existe.', 'convoca-courses' ) );
		}

		$course_id = CPT_Tarea::course_id( $task_id );
		if ( $course_id <= 0 || ! Access::can_submit_task( $user_id, $course_id ) ) {
			return new \WP_Error( 'convoca_courses_task_locked', __( 'No puedes entregar esta tarea.', 'convoca-courses' ) );
		}

		if ( '' === trim( $texto ) && $adjunto_id <= 0 ) {
			return new \WP_Error( 'convoca_courses_task_empty', __( 'La entrega está vacía: escribe algo o adjunta un fichero.', 'convoca-courses' ) );
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora  = (int) current_time( 'timestamp' );
		$estado = self::status_for( $ahora, CPT_Tarea::deadline_ts( $task_id ) );
		$table  = Installer::table( Installer::SUBMISSIONS_TABLE );
		$existe = self::submission( $task_id, $user_id );

		$fila = self::row(
			array(
				'task_id'       => $task_id,
				'course_id'     => $course_id,
				'user_id'       => $user_id,
				'enrollment_id' => Enrollment::active_id( $user_id, $course_id ),
				'contenido'     => $texto,
				'adjunto_id'    => max( 0, $adjunto_id ),
				'entregada_en'  => (string) current_time( 'mysql' ),
				'estado'        => $estado,
			)
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		if ( null === $existe ) {
			$ok = $wpdb->insert( $table, $fila['datos'], $fila['formatos'] );
		} else {
			$para_actualizar = $fila['datos'];
			$formatos        = $fila['formatos'];
			unset( $para_actualizar['task_id'], $para_actualizar['user_id'] );
			unset( $formatos[0], $formatos[2] );
			$formatos = array_values( $formatos );

			$ok = $wpdb->update(
				$table,
				$para_actualizar,
				array(
					'task_id' => $task_id,
					'user_id' => $user_id,
				),
				$formatos,
				array( '%d', '%d' )
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( false === $ok ) {
			return new \WP_Error( 'convoca_courses_task_db', __( 'No se ha podido guardar la entrega.', 'convoca-courses' ) );
		}

		/**
		 * Tarea entregada.
		 *
		 * @param int    $task_id Tarea.
		 * @param int    $user_id Persona.
		 * @param string $estado  Estado resultante.
		 */
		do_action( 'convoca_courses_task_submitted', $task_id, $user_id, $estado );

		return true;
	}

	/**
	 * Construye la fila de una entrega con sus formatos.
	 *
	 * Función pura y con prueba propia, porque el orden de `$wpdb->insert( $tabla, $datos, $formatos )`
	 * es posicional: si los formatos se descuadran, el texto entra como número y el estado como cero.
	 * Medido en la demo el 22-10-2026: la entrega se guardó con `contenido = 0` y `estado = 0`, y no
	 * había ninguna prueba que lo viera porque `$wpdb` no está en las unitarias.
	 *
	 * @param array<string, mixed> $datos Datos de la entrega (sin la corrección, que nace vacía).
	 * @return array{datos: array<string, mixed>, formatos: array<int, string>}
	 */
	public static function row( array $datos ): array {
		$columnas = array(
			'task_id'       => '%d',
			'course_id'     => '%d',
			'user_id'       => '%d',
			'enrollment_id' => '%d',
			'contenido'     => '%s',
			'adjunto_id'    => '%d',
			'entregada_en'  => '%s',
			'estado'        => '%s',
			'nota'          => '%f',
			'comentario'    => '%s',
			'corregida_por' => '%d',
			'corregida_en'  => '%s',
		);

		$defecto = array(
			'contenido'     => '',
			'adjunto_id'    => 0,
			'entregada_en'  => '0000-00-00 00:00:00',
			'estado'        => 'entregada',
			'nota'          => 0.0,
			'comentario'    => '',
			'corregida_por' => 0,
			'corregida_en'  => '0000-00-00 00:00:00',
		);

		$fila     = array();
		$formatos = array();

		foreach ( $columnas as $columna => $formato ) {
			$valor = array_key_exists( $columna, $datos ) ? $datos[ $columna ] : ( $defecto[ $columna ] ?? '' );

			switch ( $formato ) {
				case '%d':
					$fila[ $columna ] = (int) $valor;
					break;
				case '%f':
					$fila[ $columna ] = (float) $valor;
					break;
				default:
					$fila[ $columna ] = (string) $valor;
					break;
			}

			$formatos[] = $formato;
		}

		return array(
			'datos'    => $fila,
			'formatos' => $formatos,
		);
	}

	/**
	 * Corrige una entrega y avisa al alumno.
	 *
	 * @param int    $submission_id Entrega.
	 * @param float  $nota          Nota 0-100.
	 * @param string $comentario    Comentario para el alumno.
	 * @param int    $por_user      Quién corrige.
	 * @return true|\WP_Error
	 */
	public static function grade( int $submission_id, float $nota, string $comentario, int $por_user ) {
		global $wpdb;

		$entrega = self::get( $submission_id );
		if ( null === $entrega ) {
			return new \WP_Error( 'convoca_courses_no_submission', __( 'La entrega no existe.', 'convoca-courses' ) );
		}

		$nota = CPT_Curso::normalize_score( $nota );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$ok = $wpdb->update(
			Installer::table( Installer::SUBMISSIONS_TABLE ),
			array(
				'estado'        => 'corregida',
				'nota'          => $nota,
				'comentario'    => $comentario,
				'corregida_por' => $por_user,
				'corregida_en'  => (string) current_time( 'mysql' ),
			),
			array( 'id' => $submission_id ),
			array( '%s', '%f', '%s', '%d', '%s' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( false === $ok ) {
			return new \WP_Error( 'convoca_courses_task_db', __( 'No se ha podido guardar la corrección.', 'convoca-courses' ) );
		}

		$task_id   = (int) $entrega['task_id'];
		$course_id = (int) $entrega['course_id'];

		Notifications::task_graded( (int) $entrega['user_id'], $task_id, $course_id, $nota, $comentario );

		/**
		 * Tarea corregida.
		 *
		 * @param int   $submission_id Entrega.
		 * @param float $nota          Nota puesta.
		 * @param int   $por_user      Quién corrige.
		 */
		do_action( 'convoca_courses_task_graded', $submission_id, $nota, $por_user );

		return true;
	}

	/**
	 * Una entrega por identificador.
	 *
	 * @param int $submission_id Entrega.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $submission_id ): ?array {
		global $wpdb;

		if ( $submission_id <= 0 ) {
			return null;
		}

		$table = Installer::table( Installer::SUBMISSIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valor preparado.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$submission_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? self::decode( $row ) : null;
	}

	/**
	 * Entregas de un curso, con alumno y tarea.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function of_course( int $course_id ): array {
		global $wpdb;

		if ( $course_id <= 0 ) {
			return array();
		}

		$table = Installer::table( Installer::SUBMISSIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valor preparado.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE course_id = %d ORDER BY entregada_en DESC",
				$course_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'decode' ), $rows );
	}

	/**
	 * Notas de tarea de una persona en un curso, indexadas por tarea.
	 *
	 * Solo cuentan las corregidas: una entrega sin nota no es un cero, es una pieza que falta.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return array<int, float>
	 */
	public static function grades_by_task( int $user_id, int $course_id ): array {
		global $wpdb;

		if ( $user_id <= 0 || $course_id <= 0 ) {
			return array();
		}

		$table = Installer::table( Installer::SUBMISSIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task_id, nota FROM {$table} WHERE user_id = %d AND course_id = %d AND estado = 'corregida'",
				$user_id,
				$course_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$salida = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$salida[ (int) $row['task_id'] ] = round( (float) $row['nota'], 2 );
			}
		}

		return $salida;
	}

	/**
	 * Normaliza una fila de entrega.
	 *
	 * @param array<string, mixed> $row Fila.
	 * @return array<string, mixed>
	 */
	public static function decode( array $row ): array {
		return array(
			'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'task_id'       => isset( $row['task_id'] ) ? (int) $row['task_id'] : 0,
			'course_id'     => isset( $row['course_id'] ) ? (int) $row['course_id'] : 0,
			'user_id'       => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
			'enrollment_id' => isset( $row['enrollment_id'] ) ? (int) $row['enrollment_id'] : 0,
			'contenido'     => isset( $row['contenido'] ) ? (string) $row['contenido'] : '',
			'adjunto_id'    => isset( $row['adjunto_id'] ) ? (int) $row['adjunto_id'] : 0,
			'entregada_en'  => isset( $row['entregada_en'] ) ? (string) $row['entregada_en'] : '',
			'estado'        => isset( $row['estado'] ) ? (string) $row['estado'] : 'entregada',
			'nota'          => isset( $row['nota'] ) ? round( (float) $row['nota'], 2 ) : 0.0,
			'comentario'    => isset( $row['comentario'] ) ? (string) $row['comentario'] : '',
			'corregida_por' => isset( $row['corregida_por'] ) ? (int) $row['corregida_por'] : 0,
			'corregida_en'  => isset( $row['corregida_en'] ) ? (string) $row['corregida_en'] : '',
		);
	}

	/**
	 * Borra las entregas de una tarea (al borrarla definitivamente).
	 *
	 * @param int $task_id Tarea.
	 * @return void
	 */
	public static function delete_for_task( int $task_id ): void {
		global $wpdb;

		if ( $task_id <= 0 ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$wpdb->delete( Installer::table( Installer::SUBMISSIONS_TABLE ), array( 'task_id' => $task_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}
}
