<?php
/**
 * Material del curso: enlace por endpoint y decisión de privacidad.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Material;
use PHPUnit\Framework\TestCase;

/**
 * Protección del material.
 */
final class MaterialTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_courses_opciones'] = array();
	}

	/**
	 * El enlace no puede ser la URL pelada del fichero.
	 */
	public function test_el_enlace_va_por_el_endpoint(): void {
		$url = Material::url( 77, 12 );

		$this->assertStringContainsString( 'convoca-material/77/12', $url );
		$this->assertStringNotContainsString( 'wp-content/uploads', $url );
	}

	/**
	 * Sin adjunto no hay enlace.
	 */
	public function test_sin_adjunto_no_hay_enlace(): void {
		$this->assertSame( '', Material::url( 0, 12 ) );
	}

	/**
	 * El curso puede declarar su material público aunque se venda.
	 */
	public function test_material_publico_forzado(): void {
		$GLOBALS['convoca_test_meta'][40]['_convoca_material_publico'] = '1';

		$this->assertFalse( Material::privado( 40 ) );
	}

	/**
	 * Y privado aunque sea gratuito.
	 */
	public function test_material_privado_forzado(): void {
		$GLOBALS['convoca_test_meta'][41]['_convoca_material_publico'] = '0';

		$this->assertTrue( Material::privado( 41 ) );
	}

	/**
	 * Sin curso no se puede decidir: se protege.
	 */
	public function test_sin_curso_se_protege(): void {
		$this->assertTrue( Material::privado( 0 ) );
	}

	/**
	 * La plantilla no puede volver a enlazar el fichero directamente.
	 */
	public function test_la_leccion_no_usa_la_url_del_fichero(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- código del propio plugin.
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Shortcodes.php' );

		$this->assertStringNotContainsString( 'wp_get_attachment_url( $attachment )', $fuente );
		$this->assertStringContainsString( 'Material::url(', $fuente );
	}
}
