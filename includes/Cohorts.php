<?php
/**
 * Convocatorias de un curso (PRO).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convocatorias: varias fechas y plazas para el mismo curso.
 *
 * Es PRO porque es lo que necesita una organización que abre el mismo curso varias veces al año. Sin
 * licencia no existen: la matrícula se rige por las fechas del curso, como en la versión gratuita.
 * La puerta se comprueba **en el servidor**, en `Enrollment::enroll()`.
 */
class Cohorts {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_convocatoria';

	/**
	 * Meta keys de la convocatoria, sin prefijo.
	 */
	public const META_KEYS = array( 'curso_id', 'apertura', 'cierre', 'plazas' );

	/**
	 * ¿Están activas las convocatorias?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'convocatorias' );
	}

	/**
	 * Registra el tipo de contenido.
	 *
	 * Solo se registra con licencia: sin PRO no aparece ni en el panel.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! self::enabled() ) {
			return;
		}

		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Convocatorias', 'convoca-courses' ),
					'singular_name' => __( 'Convocatoria', 'convoca-courses' ),
					'add_new_item'  => __( 'Añadir convocatoria', 'convoca-courses' ),
					'edit_item'     => __( 'Editar convocatoria', 'convoca-courses' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . CPT_Curso::TYPE,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					// Las tres por entrada, con nombre propio: mapearlas a capacidades globales
					// ('read', 'convoca_manage_courses') las mete en $post_type_meta_caps y deja
					// el panel del sitio sin acceso. Ver CPT_Base::bridge_meta_caps().
					'edit_post'           => CPT_Base::CAP_EDIT_POST,
					'read_post'           => CPT_Base::CAP_READ_POST,
					'delete_post'         => CPT_Base::CAP_DELETE_POST,
					'edit_posts'          => 'convoca_manage_courses',
					'edit_others_posts'   => 'convoca_manage_courses',
					'publish_posts'       => 'convoca_manage_courses',
					'read_private_posts'  => 'convoca_manage_courses',
					'delete_posts'        => 'convoca_manage_courses',
					'delete_others_posts' => 'convoca_manage_courses',
				),
				'map_meta_cap'    => true,
				'rewrite'         => false,
			)
		);

		foreach ( self::META_KEYS as $clave ) {
			register_post_meta(
				self::TYPE,
				CPT_Base::META_PREFIX . $clave,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => static function ( $value ) use ( $clave ) {
						if ( 'apertura' === $clave || 'cierre' === $clave ) {
							return CPT_Curso::normalize_date( (string) $value );
						}

						return (string) max( 0, (int) $value );
					},
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Convocatorias de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, \WP_Post>
	 */
	public static function of_course( int $course_id ): array {
		if ( ! self::enabled() || $course_id <= 0 ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- conjunto pequeño.
					array(
						'key'   => CPT_Base::META_PREFIX . 'curso_id',
						'value' => $course_id,
					),
				),
			)
		);
	}

	/**
	 * ¿La ventana de matrícula de la convocatoria está abierta?
	 *
	 * Función pura.
	 *
	 * @param string $apertura Fecha `Y-m-d` ('' = sin límite).
	 * @param string $cierre   Fecha `Y-m-d` ('' = sin límite).
	 * @param int    $ahora    Marca de tiempo.
	 * @return bool
	 */
	public static function window_open( string $apertura, string $cierre, int $ahora ): bool {
		$desde = '' !== $apertura ? (int) strtotime( $apertura . ' 00:00:00' ) : 0;
		$hasta = '' !== $cierre ? (int) strtotime( $cierre . ' 23:59:59' ) : 0;

		return Access::enrollment_window_open( $desde, $hasta, $ahora );
	}

	/**
	 * Convocatoria abierta con plazas libres, la primera por fecha de apertura.
	 *
	 * @param int $course_id Curso.
	 * @return int Identificador de la convocatoria, 0 si no hay ninguna válida.
	 */
	public static function open_for( int $course_id ): int {
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparación de marcas de tiempo.
		$ahora = (int) current_time( 'timestamp' );

		$candidatas = array();

		foreach ( self::of_course( $course_id ) as $convocatoria ) {
			$id       = (int) $convocatoria->ID;
			$apertura = CPT_Base::get_string( $id, 'apertura', '' );
			$cierre   = CPT_Base::get_string( $id, 'cierre', '' );

			if ( ! self::window_open( $apertura, $cierre, $ahora ) ) {
				continue;
			}

			$plazas = max( 0, CPT_Base::get_int( $id, 'plazas', 0 ) );
			if ( $plazas > 0 && self::occupied( $id ) >= $plazas ) {
				continue;
			}

			$candidatas[ $id ] = '' === $apertura ? '9999-12-31' : $apertura;
		}

		if ( empty( $candidatas ) ) {
			return 0;
		}

		asort( $candidatas );

		return (int) array_key_first( $candidatas );
	}

	/**
	 * Plazas ocupadas en una convocatoria.
	 *
	 * @param int $cohort_id Convocatoria.
	 * @return int
	 */
	public static function occupied( int $cohort_id ): int {
		$matriculas = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- conjunto de una convocatoria.
					array(
						'key'   => CPT_Base::META_PREFIX . 'convocatoria_id',
						'value' => $cohort_id,
					),
				),
			)
		);

		$ocupadas = 0;
		foreach ( $matriculas as $matricula_id ) {
			if ( in_array( CPT_Matricula::status( (int) $matricula_id ), Enrollment::OCCUPYING, true ) ) {
				++$ocupadas;
			}
		}

		return $ocupadas;
	}
}
