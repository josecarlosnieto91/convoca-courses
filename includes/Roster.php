<?php
/**
 * Alumnado de un curso: quién está, cómo va y cómo se le escribe.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listado de alumnado matriculado.
 *
 * Se lee de las matrículas del propio módulo (no de otros plugins): el curso es de este módulo y su
 * alumnado también.
 */
class Roster {

	/**
	 * Estados que cuentan como alumnado del curso.
	 */
	public const STATES = array( 'activa', 'completada' );

	/**
	 * Alumnado de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array{user_id: int, nombre: string, correo: string, progreso: float, estado: string}>
	 */
	public static function students( int $course_id ): array {
		if ( $course_id <= 0 ) {
			return array();
		}

		$matriculas = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- conjunto de un curso, una consulta por pantalla.
					array(
						'key'   => CPT_Base::META_PREFIX . 'curso_id',
						'value' => $course_id,
					),
				),
			)
		);

		$alumnado = array();

		foreach ( $matriculas as $matricula_id ) {
			$estado = CPT_Matricula::status( (int) $matricula_id );
			if ( ! in_array( $estado, self::STATES, true ) ) {
				continue;
			}

			$user_id = CPT_Matricula::user_id( (int) $matricula_id );
			if ( $user_id <= 0 ) {
				continue;
			}

			$user     = get_userdata( $user_id );
			$progreso = Progress::for_course( $user_id, $course_id );

			$alumnado[] = array(
				'user_id'  => $user_id,
				'nombre'   => $user ? (string) $user->display_name : '#' . $user_id,
				'correo'   => $user ? (string) $user->user_email : '',
				'progreso' => (float) $progreso['percent'],
				'estado'   => $estado,
			);
		}

		return $alumnado;
	}

	/**
	 * Cuántas personas hay matriculadas.
	 *
	 * @param int $course_id Curso.
	 * @return int
	 */
	public static function count( int $course_id ): int {
		return count( self::students( $course_id ) );
	}

	/**
	 * Correos del alumnado con dirección válida.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, string>
	 */
	public static function emails( int $course_id ): array {
		$correos = array();

		foreach ( self::students( $course_id ) as $alumno ) {
			if ( '' !== $alumno['correo'] && is_email( $alumno['correo'] ) ) {
				$correos[] = $alumno['correo'];
			}
		}

		return array_values( array_unique( $correos ) );
	}
}
