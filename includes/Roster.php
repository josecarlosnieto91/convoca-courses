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
	 * @return array<int, array{user_id: int, identidad: int, nombre: string, correo: string, progreso: float, estado: string, en_papelera: bool}>
	 */
	public static function students( int $course_id ): array {
		if ( $course_id <= 0 ) {
			return array();
		}

		$matriculas = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				// `any` excluye `trash`: una matrícula en la papelera desaparecía del listado y no había
				// forma de verla ni de restaurarla desde aquí (courses#43).
				'post_status'    => array( 'publish', 'pending', 'private', 'draft', 'trash' ),
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

			// Lo que está en la papelera se lista igual, con su estado real: si no, desaparece y no hay
			// desde dónde restaurarla (courses#43). Las canceladas fuera de la papelera siguen fuera.
			$en_papelera = 'trash' === get_post_status( (int) $matricula_id );

			if ( ! $en_papelera && ! in_array( $estado, self::STATES, true ) ) {
				continue;
			}

			$user_id = CPT_Matricula::user_id( (int) $matricula_id );

			// El alumnado por correo no tiene usuario de WordPress: antes se saltaba con un `continue`
			// y su matrícula no aparecía en ningún listado (courses#43). Se muestra con su identidad.
			$identidad_id = (int) get_post_meta( (int) $matricula_id, Identidad::META_MATRICULA, true );
			$identidad    = $identidad_id > 0 ? Identidad::por_id( $identidad_id ) : null;

			if ( $user_id <= 0 && null === $identidad ) {
				continue;
			}

			$user     = $user_id > 0 ? get_userdata( $user_id ) : null;
			$progreso = $identidad_id > 0
				? Progress::for_course_identidad( $identidad_id, $course_id )
				: Progress::for_course( $user_id, $course_id );

			$alumnado[] = array(
				'user_id'     => $user_id,
				'identidad'   => $identidad_id,
				'nombre'      => $user ? (string) $user->display_name : (string) ( $identidad['nombre'] ?? '' ),
				'correo'      => $user ? (string) $user->user_email : (string) ( $identidad['email'] ?? '' ),
				'progreso'    => (float) $progreso['percent'],
				'estado'      => $estado,
				'en_papelera' => $en_papelera,
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
