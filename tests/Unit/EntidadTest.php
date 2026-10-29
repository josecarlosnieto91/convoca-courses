<?php
/**
 * Datos de la entidad y condiciones de venta.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Condiciones;
use Convoca\Courses\Entidad;
use PHPUnit\Framework\TestCase;

/**
 * Cada organización informa sus datos en Ajustes.
 */
final class EntidadTest extends TestCase {

	/**
	 * Opciones limpias.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
	}

	/**
	 * Sin datos, faltan los tres mínimos.
	 */
	public function test_sin_datos_faltan_los_tres_minimos(): void {
		$this->assertFalse( Entidad::completa() );
		$this->assertContains( 'nombre', Entidad::faltan() );
		$this->assertContains( 'nif', Entidad::faltan() );
		$this->assertContains( 'email_desistimiento', Entidad::faltan() );
	}

	/**
	 * Con nombre, NIF y correo ya se puede publicar.
	 */
	public function test_con_los_tres_minimos_se_puede_publicar(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre'              => 'Asociación de prueba',
			'nif'                 => 'G12345678',
			'email_desistimiento' => 'bajas@ejemplo.test',
		);

		$this->assertTrue( Entidad::completa() );
		$this->assertSame( array(), Entidad::faltan() );
	}

	/**
	 * El saneado normaliza el NIF y limpia el correo.
	 */
	public function test_saneado(): void {
		$datos = Entidad::sanitize(
			array(
				'nombre'              => '  Asociación  ',
				'nif'                 => ' g12345678 ',
				'email_desistimiento' => ' BAJAS@Ejemplo.test ',
				'direccion'           => 'Calle 1',
				'telefono'            => '600000000',
			)
		);

		$this->assertSame( 'Asociación', $datos['nombre'] );
		$this->assertSame( 'G12345678', $datos['nif'] );
		$this->assertSame( 'bajas@ejemplo.test', $datos['email_desistimiento'] );
	}

	/**
	 * Los datos de una organización no se mezclan con los de otra.
	 */
	public function test_los_datos_son_de_cada_instalacion(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre' => 'Entidad A',
			'nif'    => 'A00000000',
		);

		$this->assertSame( 'Entidad A', Entidad::datos()['nombre'] );
	}

	/**
	 * La sustitución deja el texto con los datos puestos.
	 */
	public function test_sustituye_los_marcadores(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre'              => 'Entidad A',
			'nif'                 => 'A00000000',
			'email_desistimiento' => 'bajas@a.test',
		);

		$texto = Entidad::sustituir( 'Vende {{nombre}}, NIF {{nif}}, bajas en {{email_desistimiento}}.' );

		$this->assertSame( 'Vende Entidad A, NIF A00000000, bajas en bajas@a.test.', $texto );
	}

	/**
	 * Sin correo configurado se avisa en vez de dejar un hueco.
	 */
	public function test_sin_correo_lo_dice(): void {
		$texto = Entidad::sustituir( 'Bajas: {{email_desistimiento}}' );

		$this->assertStringNotContainsString( '{{email_desistimiento}}', $texto );
	}

	/**
	 * La plantilla real no deja marcadores sin sustituir y lleva los datos.
	 */
	public function test_la_plantilla_se_sustituye_entera(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre'              => 'Entidad A',
			'nif'                 => 'A00000000',
			'email_desistimiento' => 'bajas@a.test',
		);

		$texto = Condiciones::texto();

		$this->assertStringContainsString( 'Entidad A', $texto );
		$this->assertStringContainsString( 'bajas@a.test', $texto );
		$this->assertStringNotContainsString( '{{', $texto );
		$this->assertStringContainsString( '14 días naturales', $texto );
	}

	/**
	 * Un texto propio sustituye al de fábrica.
	 */
	public function test_el_texto_propio_sustituye_al_de_fabrica(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ]     = array(
			'nombre'              => 'Entidad A',
			'nif'                 => 'A00000000',
			'email_desistimiento' => 'bajas@a.test',
		);
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION ] = 'Condiciones propias de {{nombre}}, NIF {{nif}}.';

		$texto = Condiciones::texto();

		$this->assertSame( 'Condiciones propias de Entidad A, NIF A00000000.', $texto );
		$this->assertStringNotContainsString( '14 días naturales', $texto );
	}

	/**
	 * Sin texto propio se usa el de fábrica.
	 */
	public function test_sin_texto_propio_usa_el_de_fabrica(): void {
		$this->assertStringContainsString( '14 días naturales', Condiciones::texto() );
	}

	/**
	 * Con datos, el shortcode publica el texto como HTML.
	 */
	public function test_con_datos_el_shortcode_publica(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre'              => 'Entidad A',
			'nif'                 => 'A00000000',
			'email_desistimiento' => 'bajas@a.test',
		);

		$html = Condiciones::render();

		$this->assertStringContainsString( 'convoca-condiciones', $html );
		$this->assertStringContainsString( 'Entidad A', $html );
		$this->assertStringContainsString( '<p>', $html );
	}

	/**
	 * Sin datos, el shortcode calla al visitante y avisa al administrador.
	 */
	public function test_sin_datos_no_se_publica(): void {
		$this->assertSame( '', Condiciones::render() );
	}
}
