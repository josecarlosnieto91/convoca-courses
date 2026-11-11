<?php
/**
 * Documento FSE compartido por lecciones y ficha de curso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Documento_FSE;
use PHPUnit\Framework\TestCase;

/**
 * Cubre el documento FSE y la decisión de rama.
 */
final class DocumentoFSETest extends TestCase {

	/**
	 * Tema FSE previo.
	 *
	 * @var bool
	 */
	private bool $fse = false;

	/**
	 * Prepara el tema.
	 */
	protected function setUp(): void {
		$this->fse                              = (bool) ( $GLOBALS['convoca_test_block_theme'] ?? false );
		$GLOBALS['convoca_test_block_theme']    = true;
		$GLOBALS['convoca_courses_titulos'][99] = 'Curso 99';
	}

	/**
	 * Restaura el tema.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_block_theme'] = $this->fse;
		unset( $GLOBALS['convoca_courses_titulos'][99] );
	}

	/**
	 * Un tema FSE pinta cabecera, cuerpo, pie y cierra el documento.
	 */
	public function test_render_pinta_el_documento_completo(): void {
		ob_start();
		Documento_FSE::render(
			99,
			static function (): void {
				echo '<p>cuerpo</p>';
			},
			array( 'convoca-courses--curso' )
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<title>Curso 99</title>', $html );
		$this->assertStringContainsString( '<header class="site-header">Cabecera</header>', $html );
		$this->assertStringContainsString( '<p>cuerpo</p>', $html );
		$this->assertStringContainsString( '<footer>Pie</footer>', $html );
		$this->assertStringContainsString( 'convoca-courses--curso', $html );
		$this->assertStringNotContainsString( 'home blog', $html );
	}

	/**
	 * La comprobación de tema responde al tema activo.
	 */
	public function test_es_fse_sigue_al_tema(): void {
		$this->assertTrue( Documento_FSE::es_fse() );

		$GLOBALS['convoca_test_block_theme'] = false;

		$this->assertFalse( Documento_FSE::es_fse() );
	}
}
