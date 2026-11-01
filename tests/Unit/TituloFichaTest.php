<?php
/**
 * La ficha del curso no repite el título (courses#37).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Certificate;
use PHPUnit\Framework\TestCase;

/**
 * Título del índice de lecciones.
 */
final class TituloFichaTest extends TestCase {

	/**
	 * En la ficha el título lo pinta el tema: el índice no añade un segundo encabezado.
	 */
	public function test_en_la_ficha_no_se_pinta_el_titulo(): void {
		$html = Certificate::render_template(
			'lecciones.php',
			array(
				'course_id'      => 7,
				'title'          => 'Curso básico de formación ambiental',
				'mostrar_titulo' => false,
				'enrolled'       => false,
				'progress'       => array(
					'done'    => 0,
					'total'   => 0,
					'percent' => 0,
				),
				'modules'        => array(),
			),
			false
		);

		$this->assertStringNotContainsString( 'convoca-courses__subtitle', $html, 'la ficha ya tiene un h1: otro encabezado con el mismo texto confunde' );
	}

	/**
	 * Fuera de la ficha (shortcode suelto) el título sí hace falta.
	 */
	public function test_fuera_de_la_ficha_si_se_pinta(): void {
		$html = Certificate::render_template(
			'lecciones.php',
			array(
				'course_id'      => 7,
				'title'          => 'Curso básico de formación ambiental',
				'mostrar_titulo' => true,
				'enrolled'       => false,
				'progress'       => array(
					'done'    => 0,
					'total'   => 0,
					'percent' => 0,
				),
				'modules'        => array(),
			),
			false
		);

		$this->assertStringContainsString( 'convoca-courses__subtitle', $html );
	}
}
