<?php
/**
 * Banco de preguntas: validación y persistencia.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preguntas de un examen o del banco del curso.
 *
 * La validación es pura (se prueba sin WordPress); el acceso a la tabla vive en los métodos de
 * escritura, que sí son de WordPress.
 */
class Questions {

	/**
	 * Tipos admitidos.
	 */
	public const TIPOS = array( 'unica', 'multiple', 'verdadero_falso' );

	/**
	 * Opciones canónicas de verdadero/falso.
	 */
	public const OPCIONES_VF = array( 'Verdadero', 'Falso' );

	/**
	 * Valida y normaliza una pregunta.
	 *
	 * Devuelve la pregunta lista para guardar, o la lista de errores por campo. Nunca lanza: quien
	 * llama decide qué hacer con los errores.
	 *
	 * @param array<string, mixed> $data Datos recibidos.
	 * @return array{ok: bool, errores: array<int, string>, pregunta: array<string, mixed>}
	 */
	public static function validate( array $data ): array {
		$errores = array();

		$tipo = isset( $data['tipo'] ) ? strtolower( trim( (string) $data['tipo'] ) ) : '';
		if ( ! in_array( $tipo, self::TIPOS, true ) ) {
			$errores[] = __( 'El tipo de pregunta no es válido.', 'convoca-courses' );
			$tipo      = 'unica';
		}

		$enunciado = isset( $data['enunciado'] ) ? trim( (string) $data['enunciado'] ) : '';
		if ( '' === $enunciado ) {
			$errores[] = __( 'La pregunta necesita un enunciado.', 'convoca-courses' );
		}

		$opciones = self::clean_list( isset( $data['opciones'] ) ? $data['opciones'] : array() );
		if ( 'verdadero_falso' === $tipo ) {
			$opciones = self::OPCIONES_VF;
		}

		if ( count( $opciones ) < 2 ) {
			$errores[] = __( 'La pregunta necesita al menos dos opciones distintas.', 'convoca-courses' );
		}

		$correctas = self::clean_keys( isset( $data['correctas'] ) ? $data['correctas'] : array() );
		$validas   = array();
		foreach ( $correctas as $indice ) {
			if ( (int) $indice >= 0 && (int) $indice < count( $opciones ) ) {
				$validas[] = (string) (int) $indice;
			}
		}
		$validas = array_values( array_unique( $validas ) );
		sort( $validas );

		if ( empty( $validas ) ) {
			$errores[] = __( 'Hay que marcar cuál es la respuesta correcta.', 'convoca-courses' );
		}

		if ( in_array( $tipo, array( 'unica', 'verdadero_falso' ), true ) && count( $validas ) > 1 ) {
			$errores[] = __( 'Una pregunta de respuesta única solo puede tener una correcta.', 'convoca-courses' );
		}

		if ( 'multiple' === $tipo && count( $validas ) < 2 ) {
			$errores[] = __( 'Una pregunta de respuesta múltiple necesita al menos dos correctas.', 'convoca-courses' );
		}

		$puntos = isset( $data['puntos'] ) ? round( max( 0.0, (float) $data['puntos'] ), 2 ) : 1.0;
		if ( $puntos <= 0.0 ) {
			$puntos = 1.0;
		}

		return array(
			'ok'       => empty( $errores ),
			'errores'  => $errores,
			'pregunta' => array(
				'id'          => isset( $data['id'] ) ? (int) $data['id'] : 0,
				'tipo'        => $tipo,
				'enunciado'   => $enunciado,
				'opciones'    => $opciones,
				'correctas'   => $validas,
				'puntos'      => $puntos,
				'explicacion' => isset( $data['explicacion'] ) ? trim( (string) $data['explicacion'] ) : '',
				'orden'       => isset( $data['orden'] ) ? (int) $data['orden'] : 0,
			),
		);
	}

	/**
	 * Limpia una lista de textos: sin vacíos y sin repetidos.
	 *
	 * @param mixed $values Valores recibidos.
	 * @return array<int, string>
	 */
	public static function clean_list( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$limpios = array();
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$texto = trim( (string) $value );
			if ( '' === $texto || in_array( $texto, $limpios, true ) ) {
				continue;
			}
			$limpios[] = $texto;
		}

		return $limpios;
	}

	/**
	 * Limpia una lista de índices.
	 *
	 * @param mixed $values Valores recibidos.
	 * @return array<int, string>
	 */
	public static function clean_keys( $values ): array {
		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		$limpios = array();
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$texto = trim( (string) $value );
			if ( '' === $texto ) {
				continue;
			}
			$limpios[] = $texto;
		}

		return $limpios;
	}

	/**
	 * Preguntas de un examen, en orden.
	 *
	 * @param int $exam_id Examen (0 = banco del curso).
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( int $exam_id ): array {
		global $wpdb;

		$table = Installer::table( Installer::QUESTIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, id preparado.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE exam_id = %d ORDER BY orden ASC, id ASC",
				$exam_id
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
	 * Preguntas de un curso (banco compartido o todas sus preguntas).
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function of_course( int $course_id ): array {
		global $wpdb;

		$table = Installer::table( Installer::QUESTIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, id preparado.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE course_id = %d ORDER BY orden ASC, id ASC",
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
	 * Una pregunta concreta.
	 *
	 * @param int $id Identificador.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		$table = Installer::table( Installer::QUESTIONS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, id preparado.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? self::decode( $row ) : null;
	}

	/**
	 * Cuántas preguntas tiene un examen.
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function count_for_exam( int $exam_id ): int {
		return count( self::all( $exam_id ) );
	}

	/**
	 * Da de alta una pregunta.
	 *
	 * @param array<string, mixed> $data Datos: course_id, exam_id, tipo, enunciado, opciones, correctas, puntos, explicacion, orden.
	 * @return int|\WP_Error Identificador de la pregunta o error.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$check = self::validate( $data );
		if ( ! $check['ok'] ) {
			return new \WP_Error( 'convoca_courses_question_invalid', implode( ' ', $check['errores'] ) );
		}

		$pregunta = $check['pregunta'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin, sin API de WP para ella.
		$ok = $wpdb->insert(
			Installer::table( Installer::QUESTIONS_TABLE ),
			array(
				'course_id'   => isset( $data['course_id'] ) ? (int) $data['course_id'] : 0,
				'exam_id'     => isset( $data['exam_id'] ) ? (int) $data['exam_id'] : 0,
				'tipo'        => (string) $pregunta['tipo'],
				'enunciado'   => (string) $pregunta['enunciado'],
				'opciones'    => (string) wp_json_encode( $pregunta['opciones'] ),
				'correctas'   => (string) wp_json_encode( $pregunta['correctas'] ),
				'puntos'      => (float) $pregunta['puntos'],
				'explicacion' => (string) $pregunta['explicacion'],
				'orden'       => (int) $pregunta['orden'],
				'creado_en'   => (string) current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( false === $ok ) {
			return new \WP_Error( 'convoca_courses_question_db', __( 'No se ha podido guardar la pregunta.', 'convoca-courses' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Modifica una pregunta existente.
	 *
	 * @param int                  $id   Identificador.
	 * @param array<string, mixed> $data Datos nuevos.
	 * @return bool|\WP_Error
	 */
	public static function update( int $id, array $data ) {
		global $wpdb;

		if ( $id <= 0 ) {
			return new \WP_Error( 'convoca_courses_question_missing', __( 'La pregunta no existe.', 'convoca-courses' ) );
		}

		$data['id'] = $id;
		$check      = self::validate( $data );
		if ( ! $check['ok'] ) {
			return new \WP_Error( 'convoca_courses_question_invalid', implode( ' ', $check['errores'] ) );
		}

		$pregunta = $check['pregunta'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$ok = $wpdb->update(
			Installer::table( Installer::QUESTIONS_TABLE ),
			array(
				'tipo'        => (string) $pregunta['tipo'],
				'enunciado'   => (string) $pregunta['enunciado'],
				'opciones'    => (string) wp_json_encode( $pregunta['opciones'] ),
				'correctas'   => (string) wp_json_encode( $pregunta['correctas'] ),
				'puntos'      => (float) $pregunta['puntos'],
				'explicacion' => (string) $pregunta['explicacion'],
				'orden'       => (int) $pregunta['orden'],
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%f', '%s', '%d' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return false !== $ok;
	}

	/**
	 * Borra una pregunta.
	 *
	 * @param int $id Identificador.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$ok = $wpdb->delete( Installer::table( Installer::QUESTIONS_TABLE ), array( 'id' => $id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return false !== $ok;
	}

	/**
	 * Borra todas las preguntas de un examen (al borrar el examen).
	 *
	 * @param int $exam_id Examen.
	 * @return void
	 */
	public static function delete_for_exam( int $exam_id ): void {
		global $wpdb;

		if ( $exam_id <= 0 ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabla propia del plugin.
		$wpdb->delete( Installer::table( Installer::QUESTIONS_TABLE ), array( 'exam_id' => $exam_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Normaliza una fila de la tabla al formato que consume la corrección.
	 *
	 * @param array<string, mixed> $row Fila.
	 * @return array<string, mixed>
	 */
	public static function decode( array $row ): array {
		$opciones  = json_decode( (string) ( $row['opciones'] ?? '[]' ), true );
		$correctas = json_decode( (string) ( $row['correctas'] ?? '[]' ), true );

		return array(
			'id'          => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'course_id'   => isset( $row['course_id'] ) ? (int) $row['course_id'] : 0,
			'exam_id'     => isset( $row['exam_id'] ) ? (int) $row['exam_id'] : 0,
			'tipo'        => isset( $row['tipo'] ) ? (string) $row['tipo'] : 'unica',
			'enunciado'   => isset( $row['enunciado'] ) ? (string) $row['enunciado'] : '',
			'opciones'    => is_array( $opciones ) ? array_values( $opciones ) : array(),
			'correctas'   => is_array( $correctas ) ? array_values( array_map( 'strval', $correctas ) ) : array(),
			'puntos'      => isset( $row['puntos'] ) ? (float) $row['puntos'] : 1.0,
			'explicacion' => isset( $row['explicacion'] ) ? (string) $row['explicacion'] : '',
			'orden'       => isset( $row['orden'] ) ? (int) $row['orden'] : 0,
		);
	}
}
