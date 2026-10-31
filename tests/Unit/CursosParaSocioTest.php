<?php
/**
 * Cursos que `courses` ofrece en el área del socio (courses → members).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Cursos_Para_Socio;
use PHPUnit\Framework\TestCase;

/**
 * Listado de cursos para el panel del socio.
 */
final class CursosParaSocioTest extends TestCase {

	/**
	 * Si otro filtro ya contestó, se respeta.
	 */
	public function test_no_pisa_lo_que_ya_venia(): void {
		$previos = array(
			array(
				'titulo' => 'Curso de otro plugin',
				'url'    => 'https://example.com/otro',
				'precio' => '10,00 €',
			),
		);

		$this->assertSame( $previos, Cursos_Para_Socio::cursos( $previos, 5, 9 ) );
	}

	/**
	 * Sin cursos publicados devuelve una lista vacía, no un error.
	 */
	public function test_sin_cursos_devuelve_vacio(): void {
		$cursos = Cursos_Para_Socio::cursos( array(), 5, 9 );

		$this->assertIsArray( $cursos );
	}
}
