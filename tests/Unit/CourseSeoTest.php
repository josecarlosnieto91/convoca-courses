<?php
/**
 * Datos estructurados del curso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Course_SEO;
use Convoca\Courses\Entidad;
use PHPUnit\Framework\TestCase;

/**
 * JSON-LD schema.org/Course.
 */
final class CourseSeoTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_posts']       = array();
	}

	/**
	 * Da de alta un curso publicado en el arnés.
	 *
	 * @param int $id Identificador.
	 * @return void
	 */
	private function curso( int $id ): void {
		$GLOBALS['convoca_test_post_types'][ $id ] = 'convoca_curso';
		$GLOBALS['convoca_test_posts'][ $id ]      = (object) array(
			'ID'           => $id,
			'post_type'    => 'convoca_curso',
			'post_status'  => 'publish',
			'post_title'   => 'Curso de prueba',
			'post_excerpt' => 'Resumen del curso.',
			'post_name'    => 'curso-de-prueba',
		);
	}

	/**
	 * Un curso inexistente no produce datos.
	 */
	public function test_sin_curso_no_hay_datos(): void {
		$this->assertSame( array(), Course_SEO::datos( 0 ) );
		$this->assertSame( array(), Course_SEO::datos( 9999 ) );
	}

	/**
	 * Un curso publicado da un `Course` con su oferta.
	 */
	public function test_curso_publicado(): void {
		$this->curso( 55 );
		$GLOBALS['convoca_test_meta'][55]['_convoca_precio'] = '25';
		$GLOBALS['convoca_test_meta'][55]['_convoca_moneda'] = 'EUR';

		$datos = Course_SEO::datos( 55 );

		$this->assertSame( 'Course', $datos['@type'] );
		$this->assertSame( 'Offer', $datos['offers']['@type'] );
		$this->assertSame( '25.00', $datos['offers']['price'] );
		$this->assertSame( 'EUR', $datos['offers']['priceCurrency'] );
	}

	/**
	 * Un curso gratuito se anuncia como gratis.
	 */
	public function test_curso_gratuito(): void {
		$this->curso( 56 );
		$GLOBALS['convoca_test_meta'][56]['_convoca_precio'] = '0';

		$datos = Course_SEO::datos( 56 );

		$this->assertSame( 'Free', $datos['offers']['category'] );
		$this->assertSame( '0', $datos['offers']['price'] );
	}

	/**
	 * El proveedor lleva el NIF de la entidad cuando está configurado.
	 */
	public function test_el_proveedor_lleva_el_nif(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'nombre' => 'Asociación de Prueba',
			'nif'    => 'G12345678',
		);
		$this->curso( 57 );

		$datos = Course_SEO::datos( 57 );

		$this->assertSame( 'Asociación de Prueba', $datos['provider']['name'] );
		$this->assertSame( 'G12345678', $datos['provider']['taxID'] );
	}
}
