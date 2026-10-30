<?php
/**
 * Embudo de matrícula.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Embudo;
use PHPUnit\Framework\TestCase;

/**
 * Dos hitos, contados por curso.
 */
final class EmbudoTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
	}

	/**
	 * Registrar suma uno.
	 */
	public function test_registrar_suma(): void {
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'pago' );

		$datos = Embudo::datos( 3441 );

		$this->assertSame( 2, $datos['matricula'] );
		$this->assertSame( 1, $datos['pago'] );
	}

	/**
	 * Cada curso lleva su cuenta.
	 */
	public function test_cada_curso_su_cuenta(): void {
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3442, 'matricula' );
		Embudo::registrar( 3442, 'matricula' );

		$this->assertSame( 1, Embudo::datos( 3441 )['matricula'] );
		$this->assertSame( 2, Embudo::datos( 3442 )['matricula'] );
	}

	/**
	 * Un hito que no existe no se guarda.
	 */
	public function test_un_hito_inventado_no_cuenta(): void {
		Embudo::registrar( 3441, 'inventado' );

		$this->assertSame( array(), Embudo::datos( 3441 ) );
	}

	/**
	 * La tasa es el porcentaje sobre las matrículas.
	 */
	public function test_la_tasa(): void {
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'pago' );

		$this->assertSame( 25.0, Embudo::tasa( 3441, 'pago' ) );
	}

	/**
	 * El resumen cuenta los estados leyendo las matrículas, no un contador aparte.
	 */
	public function test_el_resumen_cuenta_los_estados(): void {
		$GLOBALS['convoca_test_post_types'][801]                               = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][801][ CPT_Base::META_PREFIX . 'curso' ]  = '3441';
		$GLOBALS['convoca_test_meta'][801][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';

		$GLOBALS['convoca_test_post_types'][802]                               = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][802][ CPT_Base::META_PREFIX . 'curso' ]  = '3441';
		$GLOBALS['convoca_test_meta'][802][ CPT_Base::META_PREFIX . 'estado' ] = 'completada';

		$GLOBALS['convoca_test_post_types'][803]                               = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][803][ CPT_Base::META_PREFIX . 'curso' ]  = '3441';
		$GLOBALS['convoca_test_meta'][803][ CPT_Base::META_PREFIX . 'estado' ] = 'cancelada';

		// De otro curso: no debe contar.
		$GLOBALS['convoca_test_post_types'][804]                               = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][804][ CPT_Base::META_PREFIX . 'curso' ]  = '9999';
		$GLOBALS['convoca_test_meta'][804][ CPT_Base::META_PREFIX . 'estado' ] = 'activa';

		Embudo::registrar( 3441, 'matricula' );
		Embudo::registrar( 3441, 'pago' );

		$resumen = Embudo::resumen( 3441 );

		$this->assertSame( 1, $resumen['matricula'] );
		$this->assertSame( 1, $resumen['pago'] );
		$this->assertSame( 100.0, (float) $resumen['tasa_pago'] );
		$this->assertSame( 1, $resumen['activas'] );
		$this->assertSame( 1, $resumen['completadas'] );
		$this->assertSame( 1, $resumen['canceladas'] );
	}

	/**
	 * Sin matrículas no hay tasa que calcular.
	 */
	public function test_sin_matriculas_la_tasa_es_cero(): void {
		$this->assertSame( 0.0, Embudo::tasa( 3441, 'pago' ) );
	}
}
