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

		// 207 y no 210: el alto exacto de la página, con un borde o padding de más, parte el PDF.
		$this->assertStringContainsString( 'height: 207mm', $css, 'la hoja deja 3 mm de aire sobre los 210 de A4' );
		$this->assertStringContainsString( 'size: A4 landscape', $css );
		$this->assertStringNotContainsString( 'height: 100%', $css, 'Dompdf no lo estira y descuadra el cálculo' );
		$this->assertStringNotContainsString( '@font-face {', $css, 'las fuentes se registran desde PHP' );
	}

	/**
	 * El marco no lleva alto propio: fue lo que partió el PDF en tres páginas.
	 */
	public function test_la_hoja_mide_lo_que_la_pagina(): void {
		$css = $this->plantilla();

		$this->assertMatchesRegularExpression(
			'/\.sheet\s*\{[^}]*width:\s*297mm/s',
			$css,
			'la hoja no ocupa el ancho de la página'
		);

		preg_match( '/\.sheet\s*\{([^}]*)\}/s', $css, $regla );

		// 207 y no 210: con el alto exacto, un borde o el padding de más parte el PDF en varias hojas.
		$this->assertStringContainsString(
			'height: 207mm',
			(string) ( $regla[1] ?? '' ),
			'la hoja no ocupa el alto de la página'
		);

		// Un `height: 100%` dentro de la hoja fue lo que partía el PDF en tres páginas (courses#34).
		$this->assertStringNotContainsString( 'height: 100%', $css );
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
