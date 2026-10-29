<?php
/**
 * Matrículas: alta, baja y control de plazas.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matrícula de una persona en un curso.
 *
 * La matrícula es del módulo: no se delega en otros plugins del ecosistema.
 */
class Enrollment {

	/**
	 * Estados que ocupan plaza.
	 */
	public const OCCUPYING = array( 'pendiente', 'activa', 'completada' );

	/**
	 * Busca la matrícula de una persona en un curso.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return int Identificador de la matrícula, 0 si no existe.
	 */
	public static function find( int $user_id, int $course_id ): int {
		if ( $user_id <= 0 || $course_id <= 0 ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => CPT_Matricula::TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- dos claves indexadas por matrícula, conjunto pequeño.
					'relation' => 'AND',
					array(
						'key'   => CPT_Base::META_PREFIX . 'curso_id',
						'value' => $course_id,
					),
					array(
						'key'   => CPT_Base::META_PREFIX . 'user_id',
						'value' => $user_id,
					),
				),
			)
		);

		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/**
	 * Matrículas que ocupan plaza en un curso.
	 *
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function occupied_seats( int $course_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- idem.
					'relation' => 'AND',
					array(
						'key'   => CPT_Base::META_PREFIX . 'curso_id',
						'value' => $course_id,
					),
					array(
						'key'     => CPT_Base::META_PREFIX . 'estado',
						'value'   => self::OCCUPYING,
						'compare' => 'IN',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Plazas libres. -1 significa sin límite.
	 *
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function free_seats( int $course_id ): int {
		$total = CPT_Curso::seats( $course_id );
		if ( $total <= 0 ) {
			return -1;
		}
		return max( 0, $total - self::occupied_seats( $course_id ) );
	}

	/**
	 * Da de alta una matrícula.
	 *
	 * Con plazas limitadas se toma un lock del núcleo y se vuelve a contar dentro del lock: dos
	 * peticiones simultáneas no pueden quedarse con la última plaza.
	 *
	 * @param int                 $user_id   Persona.
	 * @param int                 $course_id Curso.
	 * @param array<string,mixed> $args      Opciones: estado, pago, importe, referencia_pago.
	 * @return int|\WP_Error Identificador de la matrícula o error.
	 */
	public static function enroll( int $user_id, int $course_id, array $args = array() ) {
		if ( $user_id <= 0 ) {
			return new \WP_Error( 'convoca_courses_no_user', __( 'Hay que iniciar sesión para matricularse.', 'convoca-courses' ) );
		}

		$course = get_post( $course_id );
		if ( ! $course || CPT_Curso::TYPE !== $course->post_type || 'publish' !== $course->post_status ) {
			return new \WP_Error( 'convoca_courses_no_course', __( 'El curso no existe o no está publicado.', 'convoca-courses' ) );
		}

		$existing = self::find( $user_id, $course_id );
		if ( $existing > 0 ) {
			$status = CPT_Matricula::status( $existing );
			if ( 'cancelada' === $status ) {
				self::set_status( $existing, 'activa' );
				return $existing;
			}
			return new \WP_Error( 'convoca_courses_already', __( 'Ya estás matriculado en este curso.', 'convoca-courses' ) );
		}

		// Convocatorias (PRO): si el curso las tiene, la matrícula exige una abierta con plazas. La
		// comprobación va aquí, en el servidor: el formulario solo enseña lo que esta función permite.
		$cohort_id = 0;
		if ( Cohorts::enabled() && ! empty( Cohorts::of_course( $course_id ) ) ) {
			$cohort_id = Cohorts::open_for( $course_id );
			if ( 0 === $cohort_id ) {
				return new \WP_Error( 'convoca_courses_no_cohort', __( 'No hay ninguna convocatoria abierta con plazas para este curso.', 'convoca-courses' ) );
			}
		}

		$is_limited = CPT_Curso::seats( $course_id ) > 0;
		$lock_key   = 'convoca_courses_seats_' . $course_id;
		$locked     = false;

		if ( $is_limited ) {
			$locked = class_exists( '\Convoca\Core\Utils' ) && \Convoca\Core\Utils::acquire_lock( $lock_key, 10 );
			if ( ! $locked ) {
				return new \WP_Error( 'convoca_courses_busy', __( 'No se ha podido reservar la plaza. Inténtalo de nuevo en unos segundos.', 'convoca-courses' ) );
			}
			if ( self::free_seats( $course_id ) <= 0 ) {
				\Convoca\Core\Utils::release_lock( $lock_key );
				return new \WP_Error( 'convoca_courses_full', __( 'No quedan plazas en este curso.', 'convoca-courses' ) );
			}
		}

		$user  = get_userdata( $user_id );
		$title = sprintf(
			/* translators: 1: course title, 2: user display name. */
			__( '%1$s — %2$s', 'convoca-courses' ),
			$course->post_title,
			$user ? $user->display_name : (string) $user_id
		);

		$enrollment_id = wp_insert_post(
			array(
				'post_type'   => CPT_Matricula::TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $enrollment_id ) ) {
			if ( $locked ) {
				\Convoca\Core\Utils::release_lock( $lock_key );
			}
			return $enrollment_id;
		}

		$enrollment_id = (int) $enrollment_id;

		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'curso_id', $course_id );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'user_id', $user_id );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'estado', isset( $args['estado'] ) ? (string) $args['estado'] : 'activa' );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'pago', isset( $args['pago'] ) ? (string) $args['pago'] : 'ninguno' );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'importe', isset( $args['importe'] ) ? (float) $args['importe'] : CPT_Curso::price( $course_id ) );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'moneda', CPT_Curso::currency( $course_id ) );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'referencia_pago', isset( $args['referencia_pago'] ) ? (string) $args['referencia_pago'] : '' );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'motivo_precio', isset( $args['motivo_precio'] ) ? (string) $args['motivo_precio'] : 'general' );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'convocatoria_id', $cohort_id );

		// Si el correo coincide con una ficha de socio, la matrícula queda vinculada a ella.
		Socio::vincular_matricula( (int) $enrollment_id, $user_id );

		if ( $locked ) {
			\Convoca\Core\Utils::release_lock( $lock_key );
		}

		/**
		 * Matrícula creada.
		 *
		 * @param int $enrollment_id Identificador de la matrícula.
		 * @param int $course_id     Curso.
		 * @param int $user_id       Persona.
		 */
		do_action( 'convoca_courses_enrolled', $enrollment_id, $course_id, $user_id );

		return $enrollment_id;
	}

	/**
	 * Cancela una matrícula.
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return bool
	 */
	public static function cancel( int $enrollment_id ): bool {
		if ( CPT_Matricula::TYPE !== get_post_type( $enrollment_id ) ) {
			return false;
		}
		$done = self::set_status( $enrollment_id, 'cancelada' );
		if ( $done ) {
			/**
			 * Matrícula cancelada.
			 *
			 * @param int $enrollment_id Identificador de la matrícula.
			 */
			do_action( 'convoca_courses_enrollment_cancelled', $enrollment_id );
		}
		return $done;
	}

	/**
	 * Cambia el estado de una matrícula.
	 *
	 * @param int    $enrollment_id Identificador de la matrícula.
	 * @param string $status        Nuevo estado.
	 * @return bool
	 */
	public static function set_status( int $enrollment_id, string $status ): bool {
		if ( ! in_array( $status, CPT_Matricula::STATUSES, true ) ) {
			return false;
		}
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'estado', $status );

		if ( 'completada' === $status ) {
			Certificate::maybe_issue( $enrollment_id );
		}

		return true;
	}

	/**
	 * Matrícula activa (o completada) de una persona en un curso.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function active_id( int $user_id, int $course_id ): int {
		$enrollment_id = self::find( $user_id, $course_id );
		if ( 0 === $enrollment_id ) {
			return 0;
		}
		$status = CPT_Matricula::status( $enrollment_id );
		return in_array( $status, array( 'activa', 'completada' ), true ) ? $enrollment_id : 0;
	}
}
