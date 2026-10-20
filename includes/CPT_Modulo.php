<?php
/**
 * CPT: módulo (agrupación de lecciones dentro de un curso).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_modulo`.
 */
class CPT_Modulo extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_modulo';

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'             => self::labels( __( 'Módulo', 'convoca-courses' ), __( 'Módulos', 'convoca-courses' ) ),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=' . CPT_Curso::TYPE,
			'show_in_rest'       => true,
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'capability_type'    => 'post',
			'capabilities'       => self::capabilities(),
			'map_meta_cap'       => true,
			'publicly_queryable' => false,
			'has_archive'        => false,
			'hierarchical'       => false,
		);
	}

	/**
	 * Meta del módulo.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'curso_id' => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'orden'    => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return (int) $value;
				},
			),
		);
	}

	/**
	 * Curso al que pertenece el módulo.
	 *
	 * @param int $module_id Identificador del módulo.
	 * @return int
	 */
	public static function course_id( int $module_id ): int {
		return self::get_int( $module_id, 'curso_id', 0 );
	}

	/**
	 * Módulos de un curso, ordenados.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return array<int, \WP_Post>
	 */
	public static function of_course( int $course_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_key'       => self::META_PREFIX . 'curso_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- catálogo pequeño y orden estable.
				'meta_value'     => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);

		return $query->posts;
	}
}
