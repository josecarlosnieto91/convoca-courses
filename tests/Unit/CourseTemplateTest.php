<?php
/**
 * Plantilla propia de la ficha del curso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Course_Template;
use PHPUnit\Framework\TestCase;

/**
 * La plantilla no se impone: se enciende.
 */
final class CourseTemplateTest extends TestCase {

	/**
	 * Opciones limpias.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
	}

	/**
	 * Por defecto está apagada: manda el tema.
	 */
	public function test_por_defecto_esta_apagada(): void {
		$this->assertFalse( Course_Template::activa() );
	}

	/**
	 * Con la opción puesta, está activa.
	 */
	public function test_se_activa_con_la_opcion(): void {
		$GLOBALS['convoca_courses_opciones'][ Course_Template::OPTION ] = true;

		$this->assertTrue( Course_Template::activa() );
	}

	/**
	 * Apagada, no toca la plantilla que elige WordPress.
	 */
	public function test_apagada_no_toca_la_plantilla(): void {
		$this->assertSame( 'tema/single.php', Course_Template::template( 'tema/single.php' ) );
	}

	/**
	 * La plantilla propia existe y delega el cuerpo en el shortcode.
	 */
	public function test_la_plantilla_existe_y_delega_en_el_shortcode(): void {
		$ruta = dirname( __DIR__, 2 ) . '/templates/curso-completo.php';

		$this->assertFileExists( $ruta );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		$contenido = (string) file_get_contents( $ruta );

		$this->assertStringContainsString( 'convoca_curso', $contenido );
		$this->assertStringContainsString( 'get_header', $contenido );
		$this->assertStringContainsString( 'get_footer', $contenido );
	}

	/**
	 * Un tema puede sustituirla con su propio fichero.
	 */
	public function test_el_tema_puede_sustituirla(): void {
		$this->assertContains( 'convoca-curso.php', Course_Template::OVERRIDES );
		$this->assertContains( 'single-convoca_curso.php', Course_Template::OVERRIDES );
	}
}
