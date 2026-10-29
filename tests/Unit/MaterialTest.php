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
	 * La URL del endpoint declara sus variables.
	 */
	public function test_query_vars_del_endpoint(): void {
		$vars = Material::query_vars( array( 'p' ) );

		$this->assertContains( 'convoca_material_adjunto', $vars );
		$this->assertContains( 'convoca_material_leccion', $vars );
	}

	/**
	 * La regla del endpoint se registra y las reglas se regeneran una vez por versión.
	 */
	public function test_regla_y_regeneracion(): void {
		$GLOBALS['convoca_test_rewrites']    = array();
		$GLOBALS['convoca_test_flush']       = 0;
		$GLOBALS['convoca_courses_opciones'] = array();

		Material::register_rewrite();
		$this->assertNotEmpty( $GLOBALS['convoca_test_rewrites'] );
		$this->assertStringContainsString( 'convoca-material', $GLOBALS['convoca_test_rewrites'][0][0] );

		Material::maybe_flush();
		$this->assertSame( 1, (int) $GLOBALS['convoca_test_flush'] );

		Material::maybe_flush();
		$this->assertSame( 1, (int) $GLOBALS['convoca_test_flush'], 'con la misma versión no se repite' );
	}

	/**
	 * Mover el adjunto a la carpeta privada es idempotente.
	 */
	public function test_mover_a_privado_mueve_el_fichero(): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- la prueba necesita un directorio y un fichero de verdad.
		$base = sys_get_temp_dir() . '/convoca-mat-test-' . random_int( 1000, 9999 );
		$mes  = $base . '/2026/10';
		mkdir( $mes, 0777, true );
		file_put_contents( $mes . '/tema.pdf', 'PDF' );

		$GLOBALS['convoca_test_uploads']     = array(
			'basedir' => $base,
			'baseurl' => 'http://ejemplo.test/uploads',
		);
		$GLOBALS['convoca_test_attached'][7] = $mes . '/tema.pdf';

		$this->assertTrue( Material::mover_a_privado( 7 ), 'el fichero se mueve' );
		$this->assertSame( $base . '/convoca-privado/tema.pdf', $GLOBALS['convoca_test_attached'][7] );
		$this->assertFileDoesNotExist( $mes . '/tema.pdf' );

		$this->assertTrue( Material::mover_a_privado( 7 ), 'volver a llamarlo no falla' );

		unlink( $base . '/convoca-privado/tema.pdf' );
		rmdir( $base . '/convoca-privado' );
		rmdir( $mes );
		rmdir( $base . '/2026' );
		rmdir( $base );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Sin fichero no hay nada que mover.
	 */
	public function test_mover_sin_fichero_devuelve_falso(): void {
		$GLOBALS['convoca_test_attached'][8] = '/no/existe.pdf';

		$this->assertFalse( Material::mover_a_privado( 8 ) );
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
