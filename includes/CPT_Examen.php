<?php
/**
 * CPT: examen.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_examen`.
 *
 * El examen cuelga de un curso y, opcionalmente, de uno de sus módulos. Las preguntas no son entradas:
 * viven en la tabla del banco, porque un examen puede tener cientos y no necesitan URL propia.
 */
class CPT_Examen extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_examen';

	/**
	 * Modos de retroalimentación.
	 */
	public const RETROALIMENTACION = array( 'tras_enviar', 'al_cerrar', 'nunca' );

	/**
	 * Meta keys del examen, sin prefijo.
	 */
	public const META_KEYS = array(
		'curso_id',
		'modulo_id',
		'num_preguntas',
		'aleatorio',
		'intentos',
		'nota_minima',
		'tiempo_maximo',
		'retroalimentacion',
		'peso',
	);

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'              => self::labels( __( 'Examen', 'convoca-courses' ), __( 'Exámenes', 'convoca-courses' ) ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . CPT_Curso::TYPE,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
			'capability_type'     => 'post',
			'capabilities'        => self::capabilities(),
			'map_meta_cap'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
		);
	}

	/**
	 * Meta del examen.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'curso_id'          => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'modulo_id'         => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'num_preguntas'     => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'aleatorio'         => array(
				// Texto `1`/`0`, igual que el desbloqueo secuencial: con booleano, un `0` se guarda
				// como cadena vacía y no se distingue de «sin configurar».
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return $value ? '1' : '0';
				},
			),
			'intentos'          => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'nota_minima'       => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return CPT_Curso::normalize_score( (float) $value );
				},
			),
			'tiempo_maximo'     => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'retroalimentacion' => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return self::normalize_feedback( (string) $value );
				},
			),
			'peso'              => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return round( max( 0.0, min( 100.0, (float) $value ) ), 2 );
				},
			),
		);
	}

	/**
	 * Curso del examen.
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function course_id( int $exam_id ): int {
		return self::get_int( $exam_id, 'curso_id', 0 );
	}

	/**
	 * Módulo del examen (0 = todo el curso).
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function module_id( int $exam_id ): int {
		return self::get_int( $exam_id, 'modulo_id', 0 );
	}

	/**
	 * Preguntas por intento (0 = todas las del examen).
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function questions_per_attempt( int $exam_id ): int {
		return max( 0, self::get_int( $exam_id, 'num_preguntas', 0 ) );
	}

	/**
	 * Si las preguntas se barajan y se eligen al azar.
	 *
	 * @param int $exam_id Examen.
	 * @return bool
	 */
	public static function is_random( int $exam_id ): bool {
		return self::get_bool( $exam_id, 'aleatorio', true );
	}

	/**
	 * Intentos permitidos (0 = sin límite).
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function max_attempts( int $exam_id ): int {
		return max( 0, self::get_int( $exam_id, 'intentos', 0 ) );
	}

	/**
	 * Nota mínima del examen. Si no se configura, manda la del curso.
	 *
	 * @param int $exam_id Examen.
	 * @return float
	 */
	public static function min_score( int $exam_id ): float {
		$propia = self::get_float( $exam_id, 'nota_minima', 0.0 );
		if ( $propia > 0.0 ) {
			return CPT_Curso::normalize_score( $propia );
		}

		$course_id = self::course_id( $exam_id );

		return $course_id > 0 ? CPT_Curso::min_score( $course_id ) : 50.0;
	}

	/**
	 * Tiempo máximo en minutos (0 = sin límite).
	 *
	 * @param int $exam_id Examen.
	 * @return int
	 */
	public static function time_limit( int $exam_id ): int {
		return max( 0, self::get_int( $exam_id, 'tiempo_maximo', 0 ) );
	}

	/**
	 * Cuándo se enseña la corrección.
	 *
	 * @param int $exam_id Examen.
	 * @return string
	 */
	public static function feedback( int $exam_id ): string {
		return self::normalize_feedback( self::get_string( $exam_id, 'retroalimentacion', 'tras_enviar' ) );
	}

	/**
	 * Peso del examen en la nota final (0-100).
	 *
	 * @param int $exam_id Examen.
	 * @return float
	 */
	public static function weight( int $exam_id ): float {
		return round( max( 0.0, min( 100.0, self::get_float( $exam_id, 'peso', 0.0 ) ) ), 2 );
	}

	/**
	 * Modo de retroalimentación válido; si no se reconoce, tras enviar.
	 *
	 * Función pura.
	 *
	 * @param string $value Modo.
	 * @return string
	 */
	public static function normalize_feedback( string $value ): string {
		$value = strtolower( trim( $value ) );

		return in_array( $value, self::RETROALIMENTACION, true ) ? $value : 'tras_enviar';
	}

	/**
	 * Exámenes de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, \WP_Post>
	 */
	public static function of_course( int $course_id ): array {
		if ( $course_id <= 0 ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- conjunto pequeño, una consulta por pantalla.
					array(
						'key'   => self::META_PREFIX . 'curso_id',
						'value' => $course_id,
					),
				),
			)
		);

		return $found;
	}

	/**
	 * Exámenes de un módulo.
	 *
	 * @param int $module_id Módulo.
	 * @return array<int, \WP_Post>
	 */
	public static function of_module( int $module_id ): array {
		if ( $module_id <= 0 ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- conjunto pequeño.
					array(
						'key'   => self::META_PREFIX . 'modulo_id',
						'value' => $module_id,
					),
				),
			)
		);

		return $found;
	}
}
