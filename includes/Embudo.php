<?php
/**
 * Embudo de matrícula, medido en la base del propio sitio.
 *
 * Dos hitos, sin cookies de terceros ni JavaScript: matrícula enviada y pago completado. Sirve para saber
 * cuántos de los que se matriculan acaban pagando, sin depender de una analítica externa.
 *
 * No cuenta accesos ni cursos terminados con un contador: para eso habría que saber **quién** llega, y un
 * contador que suma en cada lección miente (la misma persona cinco veces). Esos dos datos se consultan de
 * `Progress` y del registro de certificados cuando se pide el informe.
 *
 * El dato es por curso y se guarda en una opción: son dos contadores, no una tabla.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contadores del embudo.
 */
class Embudo {

	/**
	 * Opción donde viven los contadores.
	 */
	public const OPTION = 'convoca_courses_embudo';

	/**
	 * Hitos, en orden.
	 */
	public const HITOS = array( 'matricula', 'pago' );

	/**
	 * Suma uno al hito de un curso.
	 *
	 * @param int    $curso_id Curso.
	 * @param string $hito     Hito de `HITOS`.
	 * @return void
	 */
	public static function registrar( int $curso_id, string $hito ): void {
		if ( $curso_id <= 0 || ! in_array( $hito, self::HITOS, true ) ) {
			return;
		}

		$datos          = self::datos( $curso_id );
		$datos[ $hito ] = (int) ( $datos[ $hito ] ?? 0 ) + 1;

		$todo = get_option( self::OPTION, array() );
		$todo = is_array( $todo ) ? $todo : array();

		$todo[ $curso_id ] = $datos;

		update_option( self::OPTION, $todo, false );
	}

	/**
	 * Contadores de un curso.
	 *
	 * @param int $curso_id Curso.
	 * @return array<string,int>
	 */
	public static function datos( int $curso_id ): array {
		$todo = get_option( self::OPTION, array() );

		if ( ! is_array( $todo ) || ! isset( $todo[ $curso_id ] ) || ! is_array( $todo[ $curso_id ] ) ) {
			return array();
		}

		return array_map( 'intval', $todo[ $curso_id ] );
	}

	/**
	 * Cuántas matrículas de un curso están en un estado.
	 *
	 * Los estados se leen de las matrículas, no de un contador: así no hay un número que se pueda
	 * desincronizar de la realidad.
	 *
	 * @param int    $curso_id Curso.
	 * @param string $estado   `activa`, `completada` o `cancelada`.
	 * @return int
	 */
	public static function matriculas_en_estado( int $curso_id, string $estado ): int {
		if ( $curso_id <= 0 ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => array( 'publish', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- el informe es de administración.
				'meta_query'     => array(
					array(
						'key'   => CPT_Base::META_PREFIX . 'curso_id',
						'value' => $curso_id,
					),
					array(
						'key'   => CPT_Base::META_PREFIX . 'estado',
						'value' => $estado,
					),
				),
			)
		);

		return count( $ids );
	}

	/**
	 * Resumen del embudo de un curso.
	 *
	 * @param int $curso_id Curso.
	 * @return array<string,int|float>
	 */
	public static function resumen( int $curso_id ): array {
		$datos = self::datos( $curso_id );

		return array(
			'matricula'   => (int) ( $datos['matricula'] ?? 0 ),
			'pago'        => (int) ( $datos['pago'] ?? 0 ),
			'tasa_pago'   => self::tasa( $curso_id, 'pago' ),
			'activas'     => self::matriculas_en_estado( $curso_id, 'activa' ),
			'completadas' => self::matriculas_en_estado( $curso_id, 'completada' ),
			'canceladas'  => self::matriculas_en_estado( $curso_id, 'cancelada' ),
		);
	}

	/**
	 * Porcentaje de un hito sobre las matrículas.
	 *
	 * @param int    $curso_id Curso.
	 * @param string $hito     Hito.
	 * @return float 0-100; 0 si no hay matrículas.
	 */
	public static function tasa( int $curso_id, string $hito ): float {
		$datos = self::datos( $curso_id );
		$base  = (int) ( $datos['matricula'] ?? 0 );

		if ( $base <= 0 ) {
			return 0.0;
		}

		return round( 100 * (int) ( $datos[ $hito ] ?? 0 ) / $base, 1 );
	}
}
