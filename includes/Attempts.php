<?php
/**
 * Intentos de examen: abrir, enviar y corregir en servidor.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intentos de un examen.
 *
 * El tiempo y los intentos se validan aquí, en el servidor: la interfaz solo enseña lo que estas
 * funciones permiten. Enviar tarde no se puede forzar desde el navegador.
 */
class Attempts {

	/**
	 * Estados de un intento.
	 */
	public const ESTADOS = array( 'abierto', 'enviado', 'expirado' );

	/**
	 * ¿Se ha pasado el tiempo máximo?
	 *
	 * Función pura: recibe marcas de tiempo, no lee el reloj.
	 *
	 * @param int $iniciado Marca de tiempo de inicio.
	 * @param int $minutos  Minutos permitidos (0 = sin límite).
	 * @param int $ahora    Momento del envío.
	 * @return bool
	 */
	public static function expired( int $iniciado, int $minutos, int $ahora ): bool {
		if ( $minutos <= 0 || $iniciado <= 0 ) {
			return false;
		}

		return $ahora > $iniciado + ( $minutos * MINUTE_IN_SECONDS );
	}

	/**
	 * Intentos que quedan. -1 significa sin límite.
	 *
	 * Función pura.
	 *
	 * @param int $permitidos Intentos configurados (0 = sin límite).
	 * @param int $usados     Intentos ya abiertos.
	 * @return int
	 */
	public static function remaining( int $permitidos, int $usados ): int {
		if ( $permitidos <= 0 ) {
			return -1;
		}

		return max( 0, $permitidos - max( 0, $usados ) );
	}

	/**
	 * Abre un intento, o devuelve el que ya está abierto.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return int|\WP_Error Identificador del intento o error.
	 */
	public static function open( int $user_id, int $exam_id ) {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return new \WP_Error( 'convoca_courses_no_user', __( 'Hay que iniciar sesión para hacer el examen.', 'convoca-courses' ) );
		}

		if ( ! Hierarchy::is_type( $exam_id, CPT_Examen::TYPE ) ) {
			return new \WP_Error( 'convoca_courses_no_exam', __( 'El examen no existe.', 'convoca-courses' ) );
		}

		$course_id = CPT_Examen::course_id( $exam_id );
		if ( $course_id <= 0 ) {
			return new \WP_Error( 'convoca_courses_exam_orphan', __( 'El examen no cuelga de ningún curso.', 'convoca-courses' ) );
		}

		if ( ! Access::can_take_exam( $user_id, $course_id, CPT_Examen::module_id( $exam_id ) ) ) {
			return new \WP_Error( 'convoca_courses_exam_locked', __( 'Todavía no puedes abrir este examen.', 'convoca-courses' ) );
		}

		$abierto = self::current( $user_id, $exam_id );
		if ( null !== $abierto ) {
			return (int) $abierto['id'];
		}

		$usados     = self::count_for( $user_id, $exam_id );
		$permitidos = CPT_Examen::max_attempts( $exam_id );
		if ( self::remaining( $permitidos, $usados ) === 0 ) {
			return new \WP_Error( 'convoca_courses_no_attempts', __( 'Has agotado los intentos de este examen.', 'convoca-courses' ) );
		}

		$preguntas = self::candidates( $exam_id, $course_id );
		if ( empty( $preguntas ) ) {
			return new \WP_Error( 'convoca_courses_exam_empty', __( 'El examen no tiene preguntas todavía.', 'convoca-courses' ) );
		}

		$elegidas = Quiz::pick(
			$preguntas,
			CPT_Examen::questions_per_attempt( $exam_id ),
			CPT_Examen::is_random( $exam_id )
		);

		$table      = Installer::table( Installer::ATTEMPTS_TABLE );
		$enrollment = Enrollment::active_id( $user_id, $course_id );
		$numero     = $usados + 1;

		// El índice único (exam_id, user_id, intento) es la red anti-carrera: si dos peticiones
		// simultáneas intentan el mismo número, una falla y se reintenta con el siguiente.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$ok = $wpdb->insert(
			$table,
			array(
				'exam_id'       => $exam_id,
				'course_id'     => $course_id,
				'user_id'       => $user_id,
				'enrollment_id' => (int) $enrollment,
				'intento'       => $numero,
				'preguntas'     => (string) wp_json_encode( array_values( $elegidas ) ),
				'iniciado_en'   => (string) current_time( 'mysql' ),
				'estado'        => 'abierto',
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( false === $ok ) {
			$existente = self::current( $user_id, $exam_id );
			if ( null !== $existente ) {
				return (int) $existente['id'];
			}
			return new \WP_Error( 'convoca_courses_attempt_db', __( 'No se ha podido abrir el intento.', 'convoca-courses' ) );
		}

		$attempt_id = (int) $wpdb->insert_id;

		/**
		 * Intento de examen abierto.
		 *
		 * @param int $attempt_id Intento.
		 * @param int $exam_id    Examen.
		 * @param int $user_id    Persona.
		 */
		do_action( 'convoca_courses_attempt_opened', $attempt_id, $exam_id, $user_id );

		return $attempt_id;
	}

	/**
	 * Corrige y cierra un intento.
	 *
	 * Enviar fuera de plazo no corrige: el intento queda `expirado` y con cero.
	 *
	 * @param int                      $attempt_id Intento.
	 * @param int                      $user_id    Persona que envía.
	 * @param array<int|string, mixed> $respuestas Respuestas indexadas por pregunta.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function submit( int $attempt_id, int $user_id, array $respuestas ) {
		global $wpdb;

		$attempt = self::get( $attempt_id );
		if ( null === $attempt ) {
			return new \WP_Error( 'convoca_courses_no_attempt', __( 'El intento no existe.', 'convoca-courses' ) );
		}

		if ( (int) $attempt['user_id'] !== $user_id ) {
			return new \WP_Error( 'convoca_courses_attempt_owner', __( 'Este intento no es tuyo.', 'convoca-courses' ) );
		}

		if ( 'abierto' !== $attempt['estado'] ) {
			return new \WP_Error( 'convoca_courses_attempt_closed', __( 'Este intento ya está cerrado.', 'convoca-courses' ) );
		}

		$exam_id   = (int) $attempt['exam_id'];
		$course_id = (int) $attempt['course_id'];
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora  = (int) current_time( 'timestamp' );
		$inicio = (int) strtotime( $attempt['iniciado_en'] );

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		if ( self::expired( $inicio, CPT_Examen::time_limit( $exam_id ), $ahora ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
			$wpdb->update(
				$table,
				array(
					'estado'       => 'expirado',
					'terminado_en' => (string) current_time( 'mysql' ),
					'puntuacion'   => 0.0,
					'aprobado'     => 0,
				),
				array( 'id' => $attempt_id ),
				array( '%s', '%s', '%f', '%d' ),
				array( '%d' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery

			return array(
				'estado'     => 'expirado',
				'puntuacion' => 0.0,
				'aprobado'   => false,
				'detalle'    => array(),
				'recibido'   => false,
			);
		}

		$preguntas = array();
		foreach ( self::question_ids( $attempt ) as $question_id ) {
			$pregunta = Questions::get( $question_id );
			if ( null !== $pregunta ) {
				$preguntas[] = $pregunta;
			}
		}

		$resultado = Quiz::grade( $preguntas, $respuestas, CPT_Examen::min_score( $exam_id ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		foreach ( $resultado['detalle'] as $linea ) {
			$wpdb->insert(
				Installer::table( Installer::ANSWERS_TABLE ),
				array(
					'attempt_id'       => $attempt_id,
					'question_id'      => (int) $linea['question_id'],
					'respuesta'        => (string) wp_json_encode( $linea['respuesta'] ),
					'acertada'         => $linea['correcta'] ? 1 : 0,
					'puntos_obtenidos' => (float) $linea['puntos'],
					'respondida_en'    => (string) current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%f', '%s' )
			);
		}

		$wpdb->update(
			$table,
			array(
				'estado'       => 'enviado',
				'terminado_en' => (string) current_time( 'mysql' ),
				'puntuacion'   => (float) $resultado['puntuacion'],
				'aprobado'     => $resultado['aprobado'] ? 1 : 0,
			),
			array( 'id' => $attempt_id ),
			array( '%s', '%s', '%f', '%d' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$resultado['estado']   = 'enviado';
		$resultado['recibido'] = true;

		/**
		 * Intento corregido.
		 *
		 * @param int                  $attempt_id Intento.
		 * @param int                  $course_id  Curso.
		 * @param int                  $user_id    Persona.
		 * @param array<string, mixed> $resultado  Resultado de la corrección.
		 */
		do_action( 'convoca_courses_exam_submitted', $attempt_id, $course_id, $user_id, $resultado );

		return $resultado;
	}

	/**
	 * Preguntas candidatas de un examen: las suyas y las del banco del curso.
	 *
	 * @param int $exam_id   Examen.
	 * @param int $course_id Curso.
	 * @return array<int, int>
	 */
	public static function candidates( int $exam_id, int $course_id ): array {
		$ids = array();

		foreach ( Questions::all( $exam_id ) as $pregunta ) {
			$ids[] = (int) $pregunta['id'];
		}

		foreach ( Questions::all( 0 ) as $pregunta ) {
			if ( (int) $pregunta['course_id'] === $course_id ) {
				$ids[] = (int) $pregunta['id'];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Intento abierto de una persona en un examen.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return array<string, mixed>|null
	 */
	public static function current( int $user_id, int $exam_id ): ?array {
		global $wpdb;

		if ( $user_id <= 0 || $exam_id <= 0 ) {
			return null;
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND exam_id = %d AND estado = 'abierto' ORDER BY id DESC LIMIT 1",
				$user_id,
				$exam_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? self::decode( $row ) : null;
	}

	/**
	 * Un intento por identificador.
	 *
	 * @param int $attempt_id Intento.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $attempt_id ): ?array {
		global $wpdb;

		if ( $attempt_id <= 0 ) {
			return null;
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valor preparado.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$attempt_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? self::decode( $row ) : null;
	}

	/**
	 * Intentos abiertos por una persona en un examen, enviados o no.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function count_for( int $user_id, int $exam_id ): int {
		global $wpdb;

		if ( $user_id <= 0 || $exam_id <= 0 ) {
			return 0;
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND exam_id = %d",
				$user_id,
				$exam_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Mejor nota de una persona en un examen.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return float
	 */
	public static function best_score( int $user_id, int $exam_id ): float {
		global $wpdb;

		if ( $user_id <= 0 || $exam_id <= 0 ) {
			return 0.0;
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$score = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(puntuacion) FROM {$table} WHERE user_id = %d AND exam_id = %d AND estado = 'enviado'",
				$user_id,
				$exam_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return null === $score ? 0.0 : round( (float) $score, 2 );
	}

	/**
	 * Mejor nota por examen de una persona en un curso.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return array<int, float> Nota indexada por identificador de examen.
	 */
	public static function best_by_exam( int $user_id, int $course_id ): array {
		global $wpdb;

		if ( $user_id <= 0 || $course_id <= 0 ) {
			return array();
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT exam_id, MAX(puntuacion) AS nota FROM {$table} WHERE user_id = %d AND course_id = %d AND estado = 'enviado' GROUP BY exam_id",
				$user_id,
				$course_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$salida = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$salida[ (int) $row['exam_id'] ] = round( (float) $row['nota'], 2 );
			}
		}

		return $salida;
	}

	/**
	 * ¿Ha aprobado esta persona el examen en algún intento?
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return bool
	 */
	public static function has_passed( int $user_id, int $exam_id ): bool {
		global $wpdb;

		if ( $user_id <= 0 || $exam_id <= 0 ) {
			return false;
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND exam_id = %d AND aprobado = 1",
				$user_id,
				$exam_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $found > 0;
	}

	/**
	 * Preguntas guardadas en un intento.
	 *
	 * @param array<string, mixed> $attempt Intento decodificado.
	 * @return array<int, int>
	 */
	public static function question_ids( array $attempt ): array {
		$ids = isset( $attempt['preguntas'] ) ? $attempt['preguntas'] : array();

		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : array();
	}

	/**
	 * Normaliza una fila de intento.
	 *
	 * @param array<string, mixed> $row Fila.
	 * @return array<string, mixed>
	 */
	public static function decode( array $row ): array {
		$preguntas = json_decode( (string) ( $row['preguntas'] ?? '[]' ), true );

		return array(
			'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'exam_id'       => isset( $row['exam_id'] ) ? (int) $row['exam_id'] : 0,
			'course_id'     => isset( $row['course_id'] ) ? (int) $row['course_id'] : 0,
			'user_id'       => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
			'enrollment_id' => isset( $row['enrollment_id'] ) ? (int) $row['enrollment_id'] : 0,
			'intento'       => isset( $row['intento'] ) ? (int) $row['intento'] : 1,
			'preguntas'     => is_array( $preguntas ) ? array_values( array_map( 'intval', $preguntas ) ) : array(),
			'iniciado_en'   => isset( $row['iniciado_en'] ) ? (string) $row['iniciado_en'] : '',
			'terminado_en'  => isset( $row['terminado_en'] ) ? (string) $row['terminado_en'] : '',
			'puntuacion'    => isset( $row['puntuacion'] ) ? round( (float) $row['puntuacion'], 2 ) : 0.0,
			'aprobado'      => ! empty( $row['aprobado'] ),
			'estado'        => isset( $row['estado'] ) ? (string) $row['estado'] : 'abierto',
		);
	}
}
