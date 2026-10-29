<?php
/**
 * Progreso por identidad: el alumnado sin cuenta también avanza.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Progress;
use PHPUnit\Framework\TestCase;

/**
 * Lecciones completadas por identidad.
 */
final class ProgresoIdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
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
	 * Sin identidad no se marca nada.
	 */
	public function test_sin_identidad_no_marca(): void {
		$this->assertFalse( Progress::complete_identidad( 0, 10, 20 ) );
		$this->assertFalse( Progress::complete_identidad( 5, 0, 20 ) );
		$this->assertFalse( Progress::complete_identidad( 5, 10, 0 ) );
	}

	/**
	 * Completar y consultar la lección.
	 */
	public function test_completa_y_consulta(): void {
		$this->assertTrue( Progress::complete_identidad( 7, 10, 20 ) );
		$this->assertTrue( Progress::is_completed_identidad( 7, 20 ) );
		$this->assertFalse( Progress::is_completed_identidad( 7, 21 ) );
	}

	/**
	 * Cada identidad tiene su propio progreso.
	 */
	public function test_cada_identidad_va_a_lo_suyo(): void {
		Progress::complete_identidad( 7, 10, 20 );
		Progress::complete_identidad( 8, 10, 21 );

		$this->assertSame( array( 20 ), Progress::completed_lesson_ids_identidad( 7, 10 ) );
		$this->assertSame( array( 21 ), Progress::completed_lesson_ids_identidad( 8, 10 ) );
	}

	/**
	 * No se duplica la misma lección para la misma identidad.
	 */
	public function test_no_duplica(): void {
		Progress::complete_identidad( 7, 10, 20 );
		Progress::complete_identidad( 7, 10, 20 );

		$this->assertCount( 1, Progress::completed_lesson_ids_identidad( 7, 10 ) );
	}

	/**
	 * Sin curso se piden todas.
	 */
	public function test_sin_curso_devuelve_todas(): void {
		Progress::complete_identidad( 7, 10, 20 );
		Progress::complete_identidad( 7, 11, 22 );

		$this->assertCount( 2, Progress::completed_lesson_ids_identidad( 7 ) );
	}

	/**
	 * Sin identidad no hay lecciones.
	 */
	public function test_sin_identidad_no_hay_lecciones(): void {
		$this->assertSame( array(), Progress::completed_lesson_ids_identidad( 0 ) );
		$this->assertFalse( Progress::is_completed_identidad( 0, 20 ) );
	}
}
