<?php
/**
 * Pruebas de la jerarquía curso → módulo → lección.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Hierarchy;
use PHPUnit\Framework\TestCase;

/**
 * Una lección sin módulo y un módulo sin curso son datos huérfanos: el editor tiene que detectarlo.
 */
class HierarchyTest extends TestCase {

	/**
	 * Prepara el mapa de tipos que dobla a get_post_type().
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_post_types'] = array(
			10 => CPT_Curso::TYPE,
			20 => CPT_Modulo::TYPE,
			30 => CPT_Leccion::TYPE,
			40 => CPT_Matricula::TYPE,
		);
		$GLOBALS['convoca_test_meta'] = array();
	}

	/**
	 * Cada identificador reconoce su tipo y solo el suyo.
	 *
	 * @return void
	 */
	public function test_types_are_recognised(): void {
		$this->assertTrue( Hierarchy::is_course( 10 ) );
		$this->assertFalse( Hierarchy::is_course( 20 ) );
		$this->assertTrue( Hierarchy::is_module( 20 ) );
		$this->assertFalse( Hierarchy::is_module( 30 ) );
		$this->assertTrue( Hierarchy::is_lesson( 30 ) );
		$this->assertFalse( Hierarchy::is_lesson( 10 ) );
	}

	/**
	 * Un identificador inexistente no es nada.
	 *
	 * @return void
	 */
	public function test_missing_post_is_nothing(): void {
		$this->assertFalse( Hierarchy::is_course( 0 ) );
		$this->assertFalse( Hierarchy::is_course( 999 ) );
		$this->assertFalse( Hierarchy::is_lesson( -5 ) );
	}

	/**
	 * Validación de módulo: tiene que colgar de un curso de verdad.
	 *
	 * @return void
	 */
	public function test_module_needs_a_real_course(): void {
		$this->assertTrue( Hierarchy::valid_module_course( 20, 10 ) );
		$this->assertFalse( Hierarchy::valid_module_course( 20, 20 ) );
		$this->assertFalse( Hierarchy::valid_module_course( 20, 0 ) );
	}

	/**
	 * Validación de lección: tiene que colgar de un módulo de verdad.
	 *
	 * @return void
	 */
	public function test_lesson_needs_a_real_module(): void {
		$this->assertTrue( Hierarchy::valid_lesson_module( 30, 20 ) );
		$this->assertFalse( Hierarchy::valid_lesson_module( 30, 10 ) );
		$this->assertFalse( Hierarchy::valid_lesson_module( 30, 0 ) );
	}

	/**
	 * Lección sin módulo: el error lo dice.
	 *
	 * @return void
	 */
	public function test_lesson_without_module_reports_it(): void {
		$errors = Hierarchy::lesson_errors( 30 );

		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'módulo', $errors[0] );
	}

	/**
	 * Módulo sin curso: el error también lo dice.
	 *
	 * @return void
	 */
	public function test_module_without_course_reports_it(): void {
		$GLOBALS['convoca_test_meta'][30]['_convoca_modulo_id'] = 20;

		$errors = Hierarchy::lesson_errors( 30 );

		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'curso', $errors[0] );
	}

	/**
	 * Cadena completa: no hay errores.
	 *
	 * @return void
	 */
	public function test_complete_chain_has_no_errors(): void {
		$GLOBALS['convoca_test_meta'][30]['_convoca_modulo_id'] = 20;
		$GLOBALS['convoca_test_meta'][20]['_convoca_curso_id']  = 10;

		$this->assertSame( array(), Hierarchy::lesson_errors( 30 ) );
		$this->assertSame( 10, Hierarchy::course_of_lesson( 30 ) );
		$this->assertSame( 10, Hierarchy::course_of_module( 20 ) );
	}

	/**
	 * Cadena rota: el curso de la lección es 0, no un curso cualquiera.
	 *
	 * @return void
	 */
	public function test_broken_chain_returns_zero(): void {
		$this->assertSame( 0, Hierarchy::course_of_lesson( 30 ) );
	}
}
