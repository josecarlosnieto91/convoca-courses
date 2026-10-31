<?php
/**
 * La ficha no repite el título ni la entradilla del tema (courses#37).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;

/**
 * El tema pinta el H1 y el contenido de la página; el shortcode solo añade lo funcional.
 */
final class FichaSinDuplicadoTest extends TestCase {

	/**
	 * Plantilla de la ficha.
	 *
	 * @return string
	 */
	private function plantilla(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/curso.php' );
	}

	/**
	 * No hay un encabezado propio que compita con el del tema.
	 */
	public function test_no_pinta_un_encabezado_propio(): void {
		$this->assertStringNotContainsString( '<h1', $this->plantilla() );
		$this->assertStringNotContainsString( '<h2', $this->plantilla() );
	}

	/**
	 * El título y el contenido se reservan para cuando no hay tema que los pinte.
	 */
	public function test_el_titulo_y_el_contenido_son_condicionales(): void {
		$plantilla = $this->plantilla();

		$this->assertStringContainsString( 'is_singular()', $plantilla );
		$this->assertStringContainsString( 'convoca-courses__title', $plantilla );
	}
}
