<?php
/**
 * Pruebas del cálculo de progreso.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Progress;
use PHPUnit\Framework\TestCase;

/**
 * El porcentaje es lo que se le enseña al alumnado y lo que decide si hay certificado: se prueba a pelo.
 */
class ProgressTest extends TestCase {

	/**
	 * Casos de porcentaje.
	 *
	 * @return array<string, array{0: int, 1: int, 2: float}>
	 */
	public function percent_cases(): array {
		return array(
			'sin lecciones'          => array( 0, 0, 0.0 ),
			'ninguna completada'     => array( 0, 12, 0.0 ),
			'la mitad exacta'        => array( 6, 12, 50.0 ),
			'todas completadas'      => array( 12, 12, 100.0 ),
			'un tercio redondeado'   => array( 1, 3, 33.3 ),
			'más completadas que hay' => array( 99, 12, 100.0 ),
			'negativas se recortan'  => array( -3, 10, 0.0 ),
		);
	}

	/**
	 * Calcula el porcentaje esperado.
	 *
	 * @dataProvider percent_cases
	 *
	 * @param int   $done     Completadas.
	 * @param int   $total    Totales.
	 * @param float $expected Esperado.
	 * @return void
	 */
	public function test_percent( int $done, int $total, float $expected ): void {
		$this->assertSame( $expected, Progress::percent( $done, $total ) );
	}

	/**
	 * Al 100 % se emite certificado: el porcentaje tiene que llegar a 100 exacto, no a 99,9.
	 *
	 * @return void
	 */
	public function test_full_progress_is_exactly_one_hundred(): void {
		$this->assertSame( 100.0, Progress::percent( 37, 37 ) );
	}

	/**
	 * El progreso nunca puede pasar del total aunque haya filas huérfanas.
	 *
	 * @return void
	 */
	public function test_never_above_one_hundred(): void {
		$this->assertLessThanOrEqual( 100.0, Progress::percent( 500, 10 ) );
	}
}
