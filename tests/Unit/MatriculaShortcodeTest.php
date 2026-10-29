<?php
/**
 * Shortcode de matrícula sin cuenta.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Identidad;
use Convoca\Courses\Matricula_Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Formulario de matrícula por identidad.
 */
final class MatriculaShortcodeTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();
		unset( $_COOKIE[ Identidad::COOKIE ] );

		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de pruebas.
		$GLOBALS['wpdb'] = new WpdbDoble();
	}

	/**
	 * Restaura el estado.
	 */
	protected function tearDown(): void {
		if ( null === $this->wpdb_previo ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura.
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Base de datos previa.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Sin curso no pinta nada.
	 */
	public function test_sin_curso_no_pinta_nada(): void {
		$this->assertSame( '', Matricula_Identidad::render( array( 'course' => '0' ) ) );
	}

	/**
	 * Con curso pide correo y nombre.
	 */
	public function test_pide_correo_y_nombre(): void {
		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="nombre"', $html );
		$this->assertStringContainsString( 'value="123"', $html );
	}

	/**
	 * El formulario exige aceptar las condiciones.
	 */
	public function test_exige_aceptar_las_condiciones(): void {
		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringContainsString( 'name="consentimiento"', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( '14 días naturales', $html );
	}

	/**
	 * La renuncia al desistimiento no se ofrece si la instalación no la activa.
	 */
	public function test_sin_renuncia_activa_no_se_ofrece(): void {
		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringNotContainsString( 'name="renuncia"', $html );
	}

	/**
	 * Si la instalación la activa **y el curso es 100 % digital**, se ofrece como casilla aparte.
	 */
	public function test_con_renuncia_activa_se_ofrece(): void {
		$GLOBALS['convoca_courses_opciones'][ \Convoca\Courses\Condiciones::OPTION_RENUNCIA ] = true;
		$GLOBALS['convoca_test_meta'][123]['_convoca_cien_digital']                           = '1';

		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringContainsString( 'name="renuncia"', $html );
		$this->assertStringContainsString( 'pierdo el derecho de desistimiento', $html );
	}

	/**
	 * El texto de la casilla se puede cambiar por instalación.
	 */
	public function test_texto_de_la_casilla_configurable(): void {
		$GLOBALS['convoca_courses_opciones'][ \Convoca\Courses\Condiciones::OPTION_CONSENTIMIENTO ] = 'Acepto las condiciones de Mi Asociación.';

		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringContainsString( 'Acepto las condiciones de Mi Asociación.', $html );
	}

	/**
	 * El aviso de matrícula hecha se pinta.
	 */
	public function test_aviso_de_matricula_hecha(): void {
		$_GET['convoca_matricula'] = 'ok';

		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		unset( $_GET['convoca_matricula'] );

		$this->assertStringContainsString( 'Matrícula hecha', $html );
	}

	/**
	 * El aviso de error se pinta.
	 */
	public function test_aviso_de_error(): void {
		$_GET['convoca_matricula'] = 'error';

		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		unset( $_GET['convoca_matricula'] );

		$this->assertStringContainsString( 'No se ha podido', $html );
	}
}
