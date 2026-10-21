<?php
/**
 * Pruebas del cobro de la matrícula.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Course_Payment;
use PHPUnit\Framework\TestCase;

/**
 * Lo que decide si se cobra y cuánto se cobra. Si esto se equivoca, o se cobra de más o el importe
 * llega a Redsys con un céntimo de menos.
 */
class CoursePaymentTest extends TestCase {

	/**
	 * Casos de «¿hay que cobrar?».
	 *
	 * @return array<string, array{0: float, 1: bool, 2: bool}>
	 */
	public static function needs_payment_cases(): array {
		return array(
			'gratuito con gateway' => array( 0.0, true, false ),
			'gratuito sin gateway' => array( 0.0, false, false ),
			'de pago con gateway'  => array( 25.0, true, true ),
			'de pago sin gateway'  => array( 25.0, false, false ),
			'precio negativo'      => array( -5.0, true, false ),
			'precio muy bajo'      => array( 0.01, true, true ),
		);
	}

	/**
	 * Decisión de cobro.
	 *
	 * @dataProvider needs_payment_cases
	 *
	 * @param float $price     Precio.
	 * @param bool  $gateway   Gateway disponible.
	 * @param bool  $expected  Esperado.
	 * @return void
	 */
	public function test_needs_payment( float $price, bool $gateway, bool $expected ): void {
		$this->assertSame( $expected, Course_Payment::needs_payment( $price, $gateway ) );
	}

	/**
	 * Casos de redondeo a céntimos. Etiquetas sin acentos: la alineación de phpcs no es reproducible
	 * con multibyte y el runner pediría otros espacios que local.
	 *
	 * @return array<string, array{0: float, 1: int}>
	 */
	public static function amount_cases(): array {
		return array(
			'cero'                => array( 0.0, 0 ),
			'entero'              => array( 25.0, 2500 ),
			'con decimales'       => array( 19.99, 1999 ),
			'medio centimo'       => array( 0.005, 1 ),
			'otro con centimos'   => array( 120.5, 12050 ),
			'negativo se recorta' => array( -10.0, 0 ),
		);
	}

	/**
	 * Importe en céntimos.
	 *
	 * @dataProvider amount_cases
	 *
	 * @param float $price    Precio.
	 * @param int   $expected Céntimos esperados.
	 * @return void
	 */
	public function test_amount_cents( float $price, int $expected ): void {
		$this->assertSame( $expected, Course_Payment::amount_cents( $price ) );
	}

	/**
	 * El origen del pago tiene que ser el que admite el gateway.
	 *
	 * @return void
	 */
	public function test_origin_is_courses(): void {
		$this->assertSame( 'courses', Course_Payment::ORIGIN );
	}

	/**
	 * Sin WordPress ni gateway cargado no se ofrece cobro (y no se rompe nada).
	 *
	 * @return void
	 */
	public function test_gateway_is_not_available_without_core(): void {
		$this->assertFalse( Course_Payment::gateway_available() );
	}
}
