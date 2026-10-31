<?php
/**
 * Papelera y restauración de una matrícula (courses#43).
 *
 * Al mover una matrícula a la papelera se marca `cancelada` para que no dé acceso; al devolverla
 * hay que recuperar el estado anterior, o el curso pide matricularse otra vez aunque la matrícula
 * siga ahí.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Enrollment;
use PHPUnit\Framework\TestCase;

/**
 * Ciclo papelera → restaurar.
 */
final class PapeleraMatriculaTest extends TestCase {

	/**
	 * Escenario limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_post_types'] = array( 500 => CPT_Matricula::TYPE );
	}

	/**
	 * Ir a la papelera cancela y guarda el estado anterior.
	 */
	public function test_al_papelera_guarda_el_estado(): void {
		$GLOBALS['convoca_test_meta'][500]['_convoca_estado'] = 'completada';

		Enrollment::al_papelera( 500 );

		$this->assertSame( 'cancelada', $GLOBALS['convoca_test_meta'][500]['_convoca_estado'] );
		$this->assertSame( 'completada', $GLOBALS['convoca_test_meta'][500]['_convoca_estado_antes_papelera'] );
	}

	/**
	 * Restaurar devuelve el estado anterior y limpia la marca.
	 */
	public function test_al_restaurar_devuelve_el_estado(): void {
		$GLOBALS['convoca_test_meta'][500]['_convoca_estado'] = 'completada';

		Enrollment::al_papelera( 500 );
		Enrollment::al_restaurar( 500 );

		$this->assertSame( 'completada', $GLOBALS['convoca_test_meta'][500]['_convoca_estado'] );
		$this->assertArrayNotHasKey( '_convoca_estado_antes_papelera', $GLOBALS['convoca_test_meta'][500] );
		// WordPress puede devolverla en `draft`: una matrícula activa tiene que quedar publicada o el
		// control de acceso no la encuentra.
		$this->assertSame( 'publish', $GLOBALS['convoca_test_post_status'][500] );
	}

	/**
	 * Una matrícula que ya estaba cancelada no se «revive» al restaurar.
	 */
	public function test_cancelada_no_se_toca(): void {
		$GLOBALS['convoca_test_meta'][500]['_convoca_estado'] = 'cancelada';

		Enrollment::al_papelera( 500 );
		Enrollment::al_restaurar( 500 );

		$this->assertSame( 'cancelada', $GLOBALS['convoca_test_meta'][500]['_convoca_estado'] );
	}

	/**
	 * Sin estado previo guardado, restaurar no rompe nada.
	 */
	public function test_restaurar_sin_previo_no_falla(): void {
		$GLOBALS['convoca_test_meta'][500]['_convoca_estado'] = 'activa';

		Enrollment::al_restaurar( 500 );

		$this->assertSame( 'activa', $GLOBALS['convoca_test_meta'][500]['_convoca_estado'] );
	}
}
