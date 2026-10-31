<?php
/**
 * Validación de documentos fiscales (courses#40).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Documento;
use PHPUnit\Framework\TestCase;

/**
 * Un NIF con letra incorrecta no puede acabar en una factura.
 */
final class DocumentoTest extends TestCase {

	/**
	 * Documentos válidos.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function validos(): array {
		return array(
			'dni'          => array( '12345678Z' ),
			'dni_espacios' => array( '12345678-z' ),
			'nie_x'        => array( 'X0000000T' ),
			'nie_y'        => array( 'Y0000001S' ),
			'cif'          => array( 'A12345674' ),
			'cif_letra'    => array( 'B1234567D' ),
		);
	}

	/**
	 * Documentos inválidos.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function invalidos(): array {
		return array(
			'corto'        => array( '123' ),
			'letra_mal'    => array( '12345678A' ),
			'nie_mal'      => array( 'X0000000A' ),
			'cif_mal'      => array( 'A12345678' ),
			'nie_letra'    => array( 'Y0000001X' ),
			'vacio'        => array( '' ),
			'texto'        => array( 'NO TENGO' ),
			'letra_suelta' => array( 'Z' ),
		);
	}

	/**
	 * Los válidos pasan.
	 *
	 * @dataProvider validos
	 *
	 * @param string $documento Documento.
	 */
	public function test_los_documentos_validos_pasan( string $documento ): void {
		$this->assertTrue( Documento::valido( $documento ), $documento );
	}

	/**
	 * Los inválidos no pasan.
	 *
	 * @dataProvider invalidos
	 *
	 * @param string $documento Documento.
	 */
	public function test_los_documentos_invalidos_no_pasan( string $documento ): void {
		$this->assertFalse( Documento::valido( $documento ), $documento );
	}

	/**
	 * Se normaliza antes de comprobar.
	 */
	public function test_normaliza_mayusculas_y_separadores(): void {
		$this->assertSame( '12345678Z', Documento::limpiar( ' 12345678-z ' ) );
	}
}
