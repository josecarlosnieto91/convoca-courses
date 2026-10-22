<?php
/**
 * CPT: tarea con entrega.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_tarea`.
 *
 * La tarea es el enunciado; las entregas del alumnado viven en la tabla `submissions`, porque son
 * datos de personas (nota y comentario) que no tienen por qué estar en `postmeta`.
 */
class CPT_Tarea extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_tarea';

	/**
	 * Meta keys de la tarea, sin prefijo.
	 */
	public const META_KEYS = array(
		'curso_id',
		'modulo_id',
		'plazo',
		'peso',
		'adjunto',
	);

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'              => self::labels( __( 'Tarea', 'convoca-courses' ), __( 'Tareas', 'convoca-courses' ) ),
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
	 * Meta de la tarea.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'curso_id'  => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'modulo_id' => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'plazo'     => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return CPT_Curso::normalize_date( (string) $value );
				},
			),
			'peso'      => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return round( max( 0.0, min( 100.0, (float) $value ) ), 2 );
				},
			),
			'adjunto'   => array(
				// Texto `1`/`0` por el mismo motivo que el resto del módulo: un `0` booleano se
				// guarda como cadena vacía y deja de distinguirse de «sin configurar».
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return $value ? '1' : '0';
				},
			),
		);
	}

	/**
	 * Curso de la tarea.
	 *
	 * @param int $task_id Tarea.
	 * @return int
	 */
	public static function course_id( int $task_id ): int {
		return self::get_int( $task_id, 'curso_id', 0 );
	}

	/**
	 * Módulo de la tarea (0 = del curso entero).
	 *
	 * @param int $task_id Tarea.
	 * @return int
	 */
	public static function module_id( int $task_id ): int {
		return self::get_int( $task_id, 'modulo_id', 0 );
	}

	/**
	 * Plazo de entrega (`Y-m-d`), vacío si no hay.
	 *
	 * @param int $task_id Tarea.
	 * @return string
	 */
	public static function deadline( int $task_id ): string {
		return self::get_string( $task_id, 'plazo', '' );
	}

	/**
	 * Marca de tiempo del final del plazo (23:59:59 del día). 0 = sin plazo.
	 *
	 * @param int $task_id Tarea.
	 * @return int
	 */
	public static function deadline_ts( int $task_id ): int {
		$plazo = self::deadline( $task_id );
		if ( '' === $plazo ) {
			return 0;
		}

		$ts = strtotime( $plazo . ' 23:59:59' );

		return false === $ts ? 0 : $ts;
	}

	/**
	 * Peso de la tarea en la nota final (0-100).
	 *
	 * @param int $task_id Tarea.
	 * @return float
	 */
	public static function weight( int $task_id ): float {
		return round( max( 0.0, min( 100.0, self::get_float( $task_id, 'peso', 0.0 ) ) ), 2 );
	}

	/**
	 * Si la entrega admite fichero.
	 *
	 * @param int $task_id Tarea.
	 * @return bool
	 */
	public static function allows_file( int $task_id ): bool {
		return self::get_bool( $task_id, 'adjunto', true );
	}

	/**
	 * Tareas de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, \WP_Post>
	 */
	public static function of_course( int $course_id ): array {
		if ( $course_id <= 0 ) {
			return array();
		}

		return get_posts(
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
	}
}
