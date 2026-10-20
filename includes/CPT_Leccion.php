<?php
/**
 * CPT: lección.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_leccion`.
 */
class CPT_Leccion extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_leccion';

	/**
	 * Tipos de lección del módulo.
	 */
	public const TYPES = array( 'texto', 'adjunto', 'pdf', 'video' );

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'             => self::labels( __( 'Lección', 'convoca-courses' ), __( 'Lecciones', 'convoca-courses' ) ),
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
	 * Meta de la lección.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'modulo_id'  => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'tipo'       => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					$value = (string) $value;
					return in_array( $value, CPT_Leccion::TYPES, true ) ? $value : 'texto';
				},
			),
			'orden'      => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return (int) $value;
				},
			),
			'duracion'   => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'adjunto_id' => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'video_url'  => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return esc_url_raw( (string) $value );
				},
			),
			'video_key'  => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return sanitize_text_field( (string) $value );
				},
			),
		);
	}

	/**
	 * Módulo al que pertenece la lección.
	 *
	 * @param int $lesson_id Identificador de la lección.
	 * @return int
	 */
	public static function module_id( int $lesson_id ): int {
		return self::get_int( $lesson_id, 'modulo_id', 0 );
	}

	/**
	 * Tipo de contenido de la lección.
	 *
	 * @param int $lesson_id Identificador de la lección.
	 * @return string
	 */
	public static function type( int $lesson_id ): string {
		$type = self::get_string( $lesson_id, 'tipo', 'texto' );
		return in_array( $type, self::TYPES, true ) ? $type : 'texto';
	}

	/**
	 * Lecciones de un módulo, ordenadas.
	 *
	 * @param int $module_id Identificador del módulo.
	 * @return array<int, \WP_Post>
	 */
	public static function of_module( int $module_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_key'       => self::META_PREFIX . 'modulo_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- catálogo pequeño y orden estable.
				'meta_value'     => $module_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);

		return $query->posts;
	}

	/**
	 * Todas las lecciones de un curso, en orden de módulo y de lección.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return array<int, \WP_Post>
	 */
	public static function of_course( int $course_id ): array {
		$lessons = array();
		foreach ( CPT_Modulo::of_course( $course_id ) as $module ) {
			foreach ( self::of_module( (int) $module->ID ) as $lesson ) {
				$lessons[] = $lesson;
			}
		}
		return $lessons;
	}
}
