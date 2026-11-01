<?php
/**
 * Restaurar una matrícula antigua (sin el meta previo) la deja visible (courses#43).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Enrollment;
use PHPUnit\Framework\TestCase;

/**
 * Restauración desde la papelera.
 */
final class RestaurarMatriculaTest extends TestCase {

	/**
	 * Escenario limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_post_status'] = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();
	}

	/**
	 * Una matrícula de antes de 0.23.6 no tiene `estado_antes_papelera`: se usa el estado actual.
	 */
	public function test_sin_meta_previo_usa_el_estado_actual(): void {
		$id = 900;

		$GLOBALS['convoca_test_post_types'][ $id ]  = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_post_status'][ $id ] = 'draft';
		$GLOBALS['convoca_test_meta'][ $id ]        = array( '_convoca_estado' => 'completada' );

		Enrollment::al_restaurar( $id );

		$this->assertSame(
			'publish',
			$GLOBALS['convoca_test_post_status'][ $id ] ?? null,
			'una matrícula completada restaurada tiene que poder encontrarla el control de acceso'
		);
	}

	/**
	 * Una cancelada no vuelve a `publish`: se respeta el estado.
	 */
	public function test_una_cancelada_no_se_publica(): void {
		$id = 901;

		$GLOBALS['convoca_test_post_types'][ $id ]  = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_post_status'][ $id ] = 'publish';
		$GLOBALS['convoca_test_meta'][ $id ]        = array( '_convoca_estado' => 'cancelada' );

		Enrollment::al_restaurar( $id );

		$this->assertSame( 'publish', $GLOBALS['convoca_test_post_status'][ $id ] );
		$this->assertSame( 'cancelada', $GLOBALS['convoca_test_meta'][ $id ]['_convoca_estado'] );
	}
}
