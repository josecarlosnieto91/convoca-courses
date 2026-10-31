<?php
/**
 * El certificado tiene que caber en una página (courses#34).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Dompdf parte el documento en tres hojas si la hoja o el marco se pasan de alto. Es un test de
 * contrato sobre la plantilla: la maquetación final se mide con pdfinfo en el despliegue.
 */
final class CertificadoPaginaTest extends TestCase {

	/**
	 * Plantilla.
	 *
	 * @return string
	 */
	private function plantilla(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/certificado.php' );
	}

	/**
	 * La hoja mide la página y nada más.
	 */
	public function test_la_hoja_no_se_pasa_de_la_pagina(): void {
		$css = $this->plantilla();

		$this->assertStringContainsString( 'height: 210mm', $css, 'A4 horizontal son 210 mm de alto' );
		$this->assertStringContainsString( 'size: A4 landscape', $css );
		$this->assertStringNotContainsString( 'height: 100%', $css, 'Dompdf no lo estira y descuadra el cálculo' );
		$this->assertStringNotContainsString( '@font-face {', $css, 'las fuentes se registran desde PHP' );
	}

	/**
	 * El marco no lleva alto propio: fue lo que partió el PDF en tres páginas.
	 */
	public function test_el_marco_no_lleva_alto_propio(): void {
		$css = $this->plantilla();

		$this->assertMatchesRegularExpression(
			'/\.frame\s*\{[^}]*\}/s',
			$css,
			'falta la regla del marco'
		);

		preg_match( '/\.frame\s*\{([^}]*)\}/s', $css, $regla );

		$this->assertStringNotContainsString(
			'height:',
			(string) ( $regla[1] ?? '' ),
			'el alto del marco desborda la hoja y añade páginas en blanco'
		);
	}

	/**
	 * Las tipografías del tema van empaquetadas y se registran en Dompdf desde PHP.
	 */
	public function test_las_fuentes_son_del_tema_y_van_empaquetadas(): void {
		$css     = $this->plantilla();
		$fuentes = dirname( __DIR__, 2 ) . '/assets/fonts';

		foreach ( array( 'Outfit-SemiBold.ttf', 'Outfit-Bold.ttf', 'Lato-Regular.ttf', 'Lato-Bold.ttf', 'Lato-Black.ttf' ) as $fichero ) {
			$this->assertFileExists( $fuentes . '/' . $fichero );
		}

		$this->assertStringContainsString( "'Outfit'", $css );
		$this->assertStringContainsString( "'Lato'", $css );
		$this->assertStringNotContainsString( 'DejaVu', $css, 'la fuente por defecto de Dompdf no es la del tema' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.
		$codigo = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Certificate.php' );
		$this->assertStringContainsString( 'registerFont', $codigo, 'sin registrar la TTF, Dompdf vuelve a Helvetica' );
	}
}
