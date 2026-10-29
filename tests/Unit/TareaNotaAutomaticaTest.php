<?php
/**
 * Tareas tipo test: la nota sale del examen, sin corrección manual.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Gradebook;
use PHPUnit\Framework\TestCase;

/**
 * Nota automática de la tarea.
 */
final class TareaNotaAutomaticaTest extends TestCase {

	/**
	 * Una tarea con examen usa la nota del examen y no la corrección manual.
	 */
	public function test_manda_la_nota_del_examen(): void {
		$nota = Gradebook::resolve_nota( 'tarea', 10, 77, array( 77 => 85.0 ), array( 10 => 40.0 ) );

		$this->assertSame( 85.0, $nota );
	}

	/**
	 * Si el examen no se ha hecho, se usa la corrección manual.
	 */
	public function test_sin_intento_cae_en_la_correccion_manual(): void {
		$nota = Gradebook::resolve_nota( 'tarea', 10, 77, array(), array( 10 => 40.0 ) );

		$this->assertSame( 40.0, $nota );
	}

	/**
	 * Tarea sin examen: se comporta como siempre.
	 */
	public function test_tarea_sin_examen_sigue_igual(): void {
		$nota = Gradebook::resolve_nota( 'tarea', 10, 0, array( 77 => 85.0 ), array( 10 => 40.0 ) );

		$this->assertSame( 40.0, $nota );
	}

	/**
	 * Sin examen ni corrección, la pieza no tiene nota.
	 */
	public function test_sin_nada_no_hay_nota(): void {
		$this->assertNull( Gradebook::resolve_nota( 'tarea', 10, 0, array(), array() ) );
		$this->assertNull( Gradebook::resolve_nota( 'tarea', 10, 77, array(), array() ) );
	}

	/**
	 * Los exámenes siguen resolviéndose por sí mismos.
	 */
	public function test_los_examenes_no_cambian(): void {
		$this->assertSame( 60.0, Gradebook::resolve_nota( 'examen', 5, 0, array( 5 => 60.0 ), array( 5 => 10.0 ) ) );
		$this->assertNull( Gradebook::resolve_nota( 'examen', 5, 0, array(), array( 5 => 10.0 ) ) );
	}

	/**
	 * La tarea que se corrige con examen escribe su meta.
	 */
	public function test_la_tarea_declara_su_examen(): void {
		$GLOBALS['convoca_test_meta']                           = array();
		$GLOBALS['convoca_test_post_types']                     = array();
		$GLOBALS['convoca_test_post_types'][10]                 = 'convoca_tarea';
		$GLOBALS['convoca_test_meta'][10]['_convoca_examen_id'] = 77;

		$this->assertSame( 77, \Convoca\Courses\CPT_Tarea::exam_id( 10 ) );
	}
}
