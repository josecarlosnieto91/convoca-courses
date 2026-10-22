<?php
/**
 * Pruebas de las reglas puras de los intentos.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Attempts;
use PHPUnit\Framework\TestCase;

/**
 * Tiempo máximo e intentos disponibles: lo que no se puede saltar por URL.
 *
 * @covers \Convoca\Courses\Attempts
 */
final class AttemptsTest extends TestCase {

	/**
	 * Sin límite de tiempo, nunca caduca.
	 *
	 * @return void
	 */
	public function test_sin_tiempo_no_caduca(): void {
		$this->assertFalse( Attempts::expired( 1000, 0, 999999 ) );
	}

	/**
	 * Dentro del plazo, no caduca.
	 *
	 * @return void
	 */
	public function test_dentro_del_plazo(): void {
		$this->assertFalse( Attempts::expired( 1000, 30, 1000 + ( 29 * 60 ) ) );
	}

	/**
	 * Pasado el plazo, caduca.
	 *
	 * @return void
	 */
	public function test_pasado_el_plazo_caduca(): void {
		$this->assertTrue( Attempts::expired( 1000, 30, 1000 + ( 31 * 60 ) ) );
	}

	/**
	 * Sin límite de intentos quedan infinitos (-1).
	 *
	 * @return void
	 */
	public function test_sin_limite_de_intentos(): void {
		$this->assertSame( -1, Attempts::remaining( 0, 7 ) );
	}

	/**
	 * Con límite, se descuentan los usados.
	 *
	 * @return void
	 */
	public function test_descuenta_los_usados(): void {
		$this->assertSame( 2, Attempts::remaining( 3, 1 ) );
	}

	/**
	 * Agotados los intentos, cero y nunca negativo.
	 *
	 * @return void
	 */
	public function test_agotado_nunca_es_negativo(): void {
		$this->assertSame( 0, Attempts::remaining( 2, 5 ) );
	}
}
