<?php
/**
 * El bloque de verificación nunca sale vacío (courses#29).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Certificate_QR;
use PHPUnit\Framework\TestCase;

/**
 * Sin librería de QR, el PDF tiene que llevar la URL impresa y su rótulo.
 */
final class CertificadoQrTest extends TestCase {

	/**
	 * Sin librería no hay imagen, pero sí URL y rótulo.
	 */
	public function test_sin_libreria_imprime_la_url(): void {
		$html = Certificate_QR::image( 'https://ejemplo.test/verificar/?codigo=ABC' );

		$this->assertStringNotContainsString( '<img', $html, 'no puede fingir una imagen que no existe' );
		$this->assertStringContainsString( 'Verificación', $html );
		$this->assertStringContainsString( 'https://ejemplo.test/verificar/?codigo=ABC', $html );
		$this->assertStringNotContainsString( 'QR de verificación', $html, 'no se anuncia un QR que no se pinta' );
	}

	/**
	 * Sin URL no se inventa un bloque.
	 */
	public function test_sin_url_no_hay_bloque(): void {
		$this->assertSame( '', Certificate_QR::image( '   ' ) );
	}
}
