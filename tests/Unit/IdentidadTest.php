<?php
/**
 * Identidad propia del alumnado: correo, cookie y sesión.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Sin usuario de WordPress.
 */
final class IdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		unset( $_COOKIE[ Identidad::COOKIE ] );
	}

	/**
	 * El correo se normaliza a minúsculas y sin espacios.
	 */
	public function test_normaliza_el_correo(): void {
		$this->assertSame( 'ana@ejemplo.test', Identidad::normalizar_email( '  ANA@Ejemplo.test ' ) );
	}

	/**
	 * Un correo inválido no pasa.
	 */
	public function test_correo_invalido(): void {
		$this->assertSame( '', Identidad::normalizar_email( 'no-es-un-correo' ) );
		$this->assertSame( '', Identidad::normalizar_email( '' ) );
	}

	/**
	 * Sin cookie no hay sesión.
	 */
	public function test_sin_cookie_no_hay_sesion(): void {
		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * Una cookie bien firmada devuelve la identidad.
	 */
	public function test_cookie_valida_devuelve_la_identidad(): void {
		$expira                       = time() + 3600;
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 7, Identidad::actual() );
	}

	/**
	 * Una cookie manipulada no vale.
	 */
	public function test_cookie_manipulada_no_vale(): void {
		$expira                       = time() + 3600;
		$_COOKIE[ Identidad::COOKIE ] = '8|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * Una cookie caducada no vale.
	 */
	public function test_cookie_caducada_no_vale(): void {
		$expira                       = time() - 10;
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * La firma cambia con la identidad y con la caducidad.
	 */
	public function test_la_firma_depende_de_identidad_y_caducidad(): void {
		$expira = time() + 3600;

		$this->assertNotSame( Identidad::firma_cookie( 1, $expira ), Identidad::firma_cookie( 2, $expira ) );
		$this->assertNotSame( Identidad::firma_cookie( 1, $expira ), Identidad::firma_cookie( 1, $expira + 1 ) );
	}
}
