<?php
/**
 * Pruebas del código de certificado.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Certificate;
use PHPUnit\Framework\TestCase;

/**
 * El código se guarda normalizado y la búsqueda normaliza lo que escribe la persona: si las dos
 * formas no coinciden, un certificado emitido se vuelve irrecuperable (defecto real detectado en la
 * primera prueba contra WordPress).
 */
class CertificateCodeTest extends TestCase {

	/**
	 * Casos de normalización.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function normalize_cases(): array {
		return array(
			'ya canónico'   => array( 'CC157176G74PTRQB', 'CC157176G74PTRQB' ),
			'con guiones'    => array( 'CC-157-176-G74PTRQB', 'CC157176G74PTRQB' ),
			'en minúsculas' => array( 'cc157176g74ptrqb', 'CC157176G74PTRQB' ),
			'con espacios'   => array( ' CC 157 176 ', 'CC157176' ),
			'con basura'     => array( 'CC/157.176#G74', 'CC157176G74' ),
			'vacío'         => array( '', '' ),
		);
	}

	/**
	 * La forma canónica.
	 *
	 * @dataProvider normalize_cases
	 *
	 * @param string $input    Entrada.
	 * @param string $expected Esperado.
	 * @return void
	 */
	public function test_normalize( string $input, string $expected ): void {
		$this->assertSame( $expected, Certificate::normalize( $input ) );
	}

	/**
	 * Un certificado guardado se encuentra escriba la persona el código como lo escriba.
	 *
	 * Este es el defecto que se coló en la primera prueba contra WordPress: el código se guardaba
	 * normalizado (sin guiones) y se buscaba con guiones, así que no aparecía nunca.
	 *
	 * @return void
	 */
	public function test_lookup_finds_the_stored_certificate(): void {
		$GLOBALS['convoca_test_post_types'] = array( 176 => 'convoca_matricula' );
		$GLOBALS['convoca_test_meta']       = array(
			176 => array( '_convoca_cert_codigo' => 'CC157176G74PTRQB' ),
		);

		$this->assertSame( 176, Certificate::find_by_code( 'CC157176G74PTRQB' ) );
		$this->assertSame( 176, Certificate::find_by_code( 'cc-157-176-g74ptrqb' ) );
		$this->assertSame( 176, Certificate::find_by_code( ' CC 157 176 G74PTRQB ' ) );
		$this->assertSame( 0, Certificate::find_by_code( 'CC-INVENTADO' ) );
	}
}
