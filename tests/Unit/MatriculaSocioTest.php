<?php
/**
 * Matriculación sin volver a pedir los datos al socio (courses ↔ members).
 *
 * Lo que se prueba aquí es el contrato de seguridad, no el HTML: cuando hay sesión de socio con
 * ficha, el correo y el nombre salen de la ficha y **se descarta lo que venga en el POST**. Sin
 * eso, cualquiera podría matricular a otra persona cambiando el campo del formulario.
 *
 * El arnés unitario no ejecuta callbacks: los filtros se fuerzan con
 * `$GLOBALS['convoca_courses_filtros']`, que es como el resto de la suite enciende el PRO.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Matricula_Identidad;
use Convoca\Courses\Members_Bridge;
use PHPUnit\Framework\TestCase;

/**
 * Datos del socio en la matrícula.
 */
final class MatriculaSocioTest extends TestCase {

	/**
	 * Limpia el escenario.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_filtros'] = array();
	}

	/**
	 * Sin `members` que conteste, se usan los datos del formulario.
	 */
	public function test_sin_socio_usa_los_datos_del_formulario(): void {
		$datos = Matricula_Identidad::datos_confirmados(
			array(
				'email'  => 'ana@example.com',
				'nombre' => 'Ana',
			),
			7
		);

		$this->assertSame( 'ana@example.com', $datos['email'] );
		$this->assertSame( 'Ana', $datos['nombre'] );
		$this->assertFalse( $datos['socio'] );
	}

	/**
	 * Con socio, mandan los datos de la ficha y se ignora lo enviado.
	 */
	public function test_con_socio_mandan_los_datos_de_la_ficha(): void {
		$GLOBALS['convoca_courses_filtros'][ Members_Bridge::FILTER_DATOS ] = array(
			'email'  => 'socia@example.com',
			'nombre' => 'Socia Activa',
		);

		$datos = Matricula_Identidad::datos_confirmados(
			array(
				'email'  => 'otra-persona@example.com',
				'nombre' => 'Otra Persona',
			),
			9
		);

		$this->assertSame( 'socia@example.com', $datos['email'] );
		$this->assertSame( 'Socia Activa', $datos['nombre'] );
		$this->assertTrue( $datos['socio'] );
	}

	/**
	 * Sin correo en la ficha, no hay datos de socio y se vuelve al formulario.
	 */
	public function test_sin_correo_no_hay_datos_de_socio(): void {
		$GLOBALS['convoca_courses_filtros'][ Members_Bridge::FILTER_DATOS ] = array(
			'email'  => '',
			'nombre' => 'Sin Correo',
		);

		$this->assertSame( array(), Members_Bridge::datos_socio( 9 ) );
		$this->assertFalse( Members_Bridge::tiene_datos( 9 ) );
	}

	/**
	 * Sin usuario no se pregunta al puente.
	 */
	public function test_sin_usuario_no_hay_datos(): void {
		$GLOBALS['convoca_courses_filtros'][ Members_Bridge::FILTER_DATOS ] = array(
			'email'  => 'socia@example.com',
			'nombre' => 'Socia Activa',
		);

		$this->assertSame( array(), Members_Bridge::datos_socio( 0 ) );
	}

	/**
	 * El formulario del socio no vuelve a pedir correo ni nombre.
	 */
	public function test_el_formulario_no_pide_datos_al_socio(): void {
		$GLOBALS['convoca_courses_user_id']                                 = 9;
		$GLOBALS['convoca_courses_filtros'][ Members_Bridge::FILTER_DATOS ] = array(
			'email'  => 'socia@example.com',
			'nombre' => 'Socia Activa',
		);

		$html = Matricula_Identidad::render( array( 'course' => '3441' ) );

		$this->assertStringContainsString( 'Te matriculas como', $html );
		$this->assertStringContainsString( 'Socia Activa', $html );
		$this->assertStringNotContainsString( 'name="email"', $html );
		$this->assertStringNotContainsString( 'name="nombre"', $html );
	}

	/**
	 * Sin socio, el formulario sigue pidiendo correo y nombre.
	 */
	public function test_sin_socio_el_formulario_pide_los_datos(): void {
		$GLOBALS['convoca_courses_user_id'] = 0;

		$html = Matricula_Identidad::render( array( 'course' => '3441' ) );

		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="nombre"', $html );
		$this->assertStringNotContainsString( 'Te matriculas como', $html );
	}

	/**
	 * El puente limpia lo que le llegue: un correo inválido no pasa.
	 */
	public function test_el_puente_sanea_el_correo(): void {
		$GLOBALS['convoca_courses_filtros'][ Members_Bridge::FILTER_DATOS ] = array(
			'email'  => '  SOCIA@Example.COM ',
			'nombre' => '  Socia   Activa ',
			'dni'    => ' 12345678z ',
		);

		$datos = Members_Bridge::datos_socio( 9 );

		$this->assertSame( 'socia@example.com', $datos['email'] );
		// `sanitize_text_field` recorta los extremos pero no colapsa los espacios internos.
		$this->assertSame( 'Socia   Activa', $datos['nombre'] );
		$this->assertSame( '12345678Z', $datos['dni'] );
	}
}
