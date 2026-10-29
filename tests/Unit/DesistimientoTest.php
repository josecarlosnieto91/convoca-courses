<?php
/**
 * Desistimiento sin cuenta.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Condiciones;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\Desistimiento;
use PHPUnit\Framework\TestCase;

/**
 * Enlace firmado, corte de acceso y prueba archivada.
 */
final class DesistimientoTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta']     = array();
		$GLOBALS['convoca_test_opciones'] = array();
		$_GET                             = array();
		$_POST                            = array();
	}

	/**
	 * Un token recién firmado se acepta.
	 */
	public function test_token_valido(): void {
		$this->assertTrue( Desistimiento::verificar( 42, Desistimiento::token( 42 ) ) );
	}

	/**
	 * El token de una matrícula no vale para otra.
	 */
	public function test_token_de_otra_matricula_no_vale(): void {
		$this->assertFalse( Desistimiento::verificar( 43, Desistimiento::token( 42 ) ) );
	}

	/**
	 * Un token manipulado se rechaza.
	 */
	public function test_token_manipulado_se_rechaza(): void {
		$token = Desistimiento::token( 42 );
		$roto  = substr( $token, 0, -1 ) . ( 'a' === substr( $token, -1 ) ? 'b' : 'a' );

		$this->assertFalse( Desistimiento::verificar( 42, $roto ) );
	}

	/**
	 * Un token caducado se rechaza.
	 */
	public function test_token_caducado_se_rechaza(): void {
		$expira   = time() - 100;
		$caducado = $expira . '.' . hash_hmac( 'sha256', '42|' . $expira, wp_salt( 'auth' ) );

		$this->assertFalse( Desistimiento::verificar( 42, $caducado ) );
	}

	/**
	 * Una matrícula sin token no da URL.
	 */
	public function test_sin_matricula_no_hay_url(): void {
		$this->assertSame( '', Desistimiento::url( 0 ) );
	}

	/**
	 * Una matrícula cancelada ya no admite desistimiento.
	 */
	public function test_una_cancelada_no_admite_desistimiento(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'cancelada';

		$this->assertFalse( Desistimiento::disponible( 42 ) );
	}

	/**
	 * Quien renunció al desistimiento no puede ejercerlo.
	 */
	public function test_la_renuncia_cierra_el_desistimiento(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';
		$GLOBALS['convoca_test_meta'][42][ Condiciones::META_RENUNCIA ]       = '1';

		$this->assertFalse( Desistimiento::disponible( 42 ) );
	}

	/**
	 * Una matrícula activa sí admite desistimiento.
	 */
	public function test_una_activa_admite_desistimiento(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';

		$this->assertTrue( Desistimiento::disponible( 42 ) );
	}

	/**
	 * Solicitar archiva la prueba y **corta el acceso**.
	 */
	public function test_solicitar_corta_el_acceso(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';

		$mensaje = Desistimiento::solicitar( 42, Desistimiento::token( 42 ) );

		$this->assertStringContainsString( 'registrado', $mensaje );
		$this->assertSame( 'cancelada', $GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] );
		$this->assertSame( 'solicitado', $GLOBALS['convoca_test_meta'][42][ Desistimiento::META_ESTADO ] );

		$prueba = $GLOBALS['convoca_test_meta'][42][ Desistimiento::META_PRUEBA ];
		$this->assertIsArray( $prueba );
		$this->assertArrayHasKey( 'fecha', $prueba );
		$this->assertArrayHasKey( 'ip', $prueba );
		$this->assertArrayHasKey( 'navegador', $prueba );
		$this->assertArrayHasKey( 'texto', $prueba );
	}

	/**
	 * Sin token válido no se toca la matrícula.
	 */
	public function test_sin_token_no_se_cancela(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';

		Desistimiento::solicitar( 42, 'basura' );

		$this->assertSame( 'activa', $GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] );
	}

	/**
	 * El shortcode no pinta el botón con un token inválido.
	 */
	public function test_el_shortcode_rechaza_token_invalido(): void {
		$_GET = array(
			Desistimiento::PARAM       => '42',
			Desistimiento::PARAM_TOKEN => 'basura',
		);

		$html = Desistimiento::render();

		$this->assertStringContainsString( 'no es válido', $html );
		$this->assertStringNotContainsString( 'Desistir del curso', $html );
	}

	/**
	 * El shortcode pinta el botón con un token válido.
	 */
	public function test_el_shortcode_pinta_el_boton(): void {
		$GLOBALS['convoca_test_meta'][42][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';
		$_GET = array(
			Desistimiento::PARAM       => '42',
			Desistimiento::PARAM_TOKEN => rawurlencode( Desistimiento::token( 42 ) ),
		);

		$html = Desistimiento::render();

		$this->assertStringContainsString( 'Desistir del curso', $html );
		$this->assertStringContainsString( 'pierdes el acceso', $html );
	}
}
