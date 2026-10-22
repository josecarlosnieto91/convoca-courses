<?php
/**
 * Pruebas del precio por condición de socio.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Pricing;
use PHPUnit\Framework\TestCase;

/**
 * Prioridad de precios: gratis > precio socio > descuento > general.
 *
 * @covers \Convoca\Courses\Pricing
 */
final class PricingTest extends TestCase {

	/**
	 * Quien no es socio paga el precio general, aunque el curso tenga de todo.
	 *
	 * @return void
	 */
	public function test_no_socio_paga_general(): void {
		$resultado = Pricing::effective( 100.0, 50.0, 30.0, true, false );

		$this->assertSame( 100.0, $resultado['precio'] );
		$this->assertSame( 'general', $resultado['motivo'] );
	}

	/**
	 * Socio con gratis: cero euros.
	 *
	 * @return void
	 */
	public function test_socio_gratis(): void {
		$resultado = Pricing::effective( 100.0, 50.0, 30.0, true, true );

		$this->assertSame( 0.0, $resultado['precio'] );
		$this->assertSame( 'socio_gratis', $resultado['motivo'] );
	}

	/**
	 * Socio con precio especial: manda sobre el descuento.
	 *
	 * @return void
	 */
	public function test_socio_con_precio_especial(): void {
		$resultado = Pricing::effective( 100.0, 40.0, 30.0, false, true );

		$this->assertSame( 40.0, $resultado['precio'] );
		$this->assertSame( 'socio_precio', $resultado['motivo'] );
	}

	/**
	 * Socio con descuento porcentual.
	 *
	 * @return void
	 */
	public function test_socio_con_descuento(): void {
		$resultado = Pricing::effective( 100.0, null, 25.0, false, true );

		$this->assertSame( 75.0, $resultado['precio'] );
		$this->assertSame( 'socio_descuento', $resultado['motivo'] );
	}

	/**
	 * Socio sin nada configurado: precio general (no se inventa un descuento).
	 *
	 * @return void
	 */
	public function test_socio_sin_ventaja(): void {
		$resultado = Pricing::effective( 100.0, null, 0.0, false, true );

		$this->assertSame( 100.0, $resultado['precio'] );
		$this->assertSame( 'general', $resultado['motivo'] );
	}

	/**
	 * El descuento se acota y el redondeo es de céntimos.
	 *
	 * @return void
	 */
	public function test_descuento_acotado_y_redondeado(): void {
		$resultado = Pricing::effective( 19.99, null, 150.0, false, true );

		$this->assertSame( 0.0, $resultado['precio'] );

		$otro = Pricing::effective( 19.99, null, 33.0, false, true );
		$this->assertSame( 13.39, $otro['precio'] );
	}

	/**
	 * Un precio de socio negativo no se cuela como cobro.
	 *
	 * @return void
	 */
	public function test_precio_socio_negativo_se_acota(): void {
		$resultado = Pricing::effective( 100.0, -5.0, 0.0, false, true );

		$this->assertSame( 0.0, $resultado['precio'] );
		$this->assertSame( 'socio_precio', $resultado['motivo'] );
	}

	/**
	 * Las etiquetas existen para todos los motivos.
	 *
	 * @return void
	 */
	public function test_etiquetas(): void {
		foreach ( Pricing::MOTIVOS as $motivo ) {
			$this->assertNotSame( '', Pricing::label( $motivo ) );
		}
	}
}
