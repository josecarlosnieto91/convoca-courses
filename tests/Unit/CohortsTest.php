<?php
/**
 * Convocatorias: ventana de inscripción, ocupación y registro del tipo.
 *
 * `Cohorts` estaba al 0 % (105 statements). Lo que se prueba es lo que decide cosas: cuándo se puede
 * inscribir alguien (la ventana), cuántas plazas hay ocupadas de verdad (solo las matrículas que
 * ocupan plaza), y que el tipo de contenido se registra con lo que necesita para no romper el panel.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Cohorts;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\Pro;
use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de las convocatorias.
 */
class CohortsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_cc_cpt']                 = array();
		$GLOBALS['_cc_post_meta']           = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_courses_filtros'] = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Cohorts.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_cc_cpt']                 = array();
		$GLOBALS['_cc_post_meta']           = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_courses_filtros'] = array();
	}

	/**
	 * Sin fechas la ventana está siempre abierta.
	 */
	public function test_sin_fechas_la_ventana_esta_abierta(): void {
		$this->assertTrue( Cohorts::window_open( '', '', 1_700_000_000 ) );
	}

	/**
	 * Antes de la apertura no se puede inscribir.
	 */
	public function test_antes_de_la_apertura_no_se_puede(): void {
		$this->assertFalse( Cohorts::window_open( '2026-10-10', '', strtotime( '2026-10-09 12:00:00' ) ) );
		$this->assertTrue( Cohorts::window_open( '2026-10-10', '', strtotime( '2026-10-10 00:00:00' ) ), 'el día de apertura ya se puede' );
	}

	/**
	 * Pasado el cierre no se puede inscribir.
	 */
	public function test_pasado_el_cierre_no_se_puede(): void {
		$this->assertTrue( Cohorts::window_open( '', '2026-10-20', strtotime( '2026-10-20 23:59:59' ) ), 'el último segundo del cierre todavía vale' );
		$this->assertFalse( Cohorts::window_open( '', '2026-10-20', strtotime( '2026-10-21 00:00:00' ) ) );
	}

	/**
	 * Con apertura y cierre, dentro del plazo se puede.
	 */
	public function test_dentro_del_plazo_se_puede(): void {
		$this->assertTrue( Cohorts::window_open( '2026-10-10', '2026-10-20', strtotime( '2026-10-15 09:00:00' ) ) );
	}

	/**
	 * Las convocatorias dependen del PRO.
	 */
	public function test_las_convocatorias_dependen_del_pro(): void {
		$this->assertFalse( Pro::enabled( 'convocatorias' ), 'sin licencia están apagadas' );

		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$this->assertTrue( Pro::enabled( 'convocatorias' ) );
		$this->assertFalse( Pro::enabled( 'inventada' ), 'una función que no existe no se enciende' );
	}

	/**
	 * Sin PRO no se registra el tipo: el sitio no ve convocatorias.
	 */
	public function test_sin_pro_no_se_registra_el_tipo(): void {
		Cohorts::register();

		$this->assertArrayNotHasKey( Cohorts::TYPE, $GLOBALS['_cc_cpt'] );
	}

	/**
	 * Con PRO, el tipo se registra con lo que necesita el panel.
	 */
	public function test_con_pro_el_tipo_se_registra_como_toca(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		Cohorts::register();

		$args = $GLOBALS['_cc_cpt'][ Cohorts::TYPE ];

		$this->assertFalse( $args['public'], 'una convocatoria no es una página pública' );
		$this->assertTrue( $args['show_ui'] );
		$this->assertSame( 'edit.php?post_type=' . CPT_Curso::TYPE, $args['show_in_menu'], 'vive dentro de Cursos' );
		$this->assertFalse( $args['rewrite'], 'sin URL propia: no hay enlaces que se rompan' );
		$this->assertTrue( $args['map_meta_cap'] );
		$this->assertSame( CPT_Base::CAP_EDIT_POST, $args['capabilities']['edit_post'], 'las de entrada van por su capacidad' );
		$this->assertSame( 'convoca_manage_courses', $args['capabilities']['edit_posts'] );
		$this->assertStringContainsString( 'onvocatoria', $args['labels']['singular_name'] );
	}

	/**
	 * Con PRO, las cuatro metas quedan registradas y protegidas.
	 */
	public function test_con_pro_las_metas_quedan_registradas(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		Cohorts::register();

		$metas = $GLOBALS['_cc_post_meta'][ Cohorts::TYPE ];

		foreach ( Cohorts::META_KEYS as $clave ) {
			$nombre = CPT_Base::META_PREFIX . $clave;
			$this->assertArrayHasKey( $nombre, $metas, 'la meta ' . $clave . ' se registra con su prefijo' );
			$this->assertFalse( $metas[ $nombre ]['show_in_rest'], 'el curso y las plazas no se tocan por la API' );
			$this->assertArrayHasKey( 'sanitize_callback', $metas[ $nombre ], 'cada meta dice cómo se limpia' );
		}
	}

	/**
	 * Solo las matrículas que ocupan plaza cuentan como ocupadas.
	 */
	public function test_solo_las_matriculas_que_ocupan_plaza_cuentan(): void {
		$this->matricula( 1, 'activa' );
		$this->matricula( 2, 'pendiente' );
		$this->matricula( 3, 'cancelada' );
		$this->matricula( 4, 'completada' );

		$this->assertSame( 3, Cohorts::occupied( 7 ), 'la cancelada libera su plaza; pendiente, activa y completada la ocupan' );
	}

	/**
	 * **Un estado que el módulo no reconoce NO ocupa plaza.**
	 *
	 * `CPT_Matricula::status()` cae a `pendiente` cuando el valor guardado no está en `STATUSES`, y `pendiente`
	 * cuenta como ocupante. Con datos heredados —una matrícula con `baja` de una importación o de una versión
	 * anterior— eso dejaba una plaza bloqueada por alguien que ya no está, sin que nadie pudiera verlo: en un curso
	 * con sobreventa no hay forma de saber por qué falta sitio (courses#3).
	 *
	 * Ahora se decide sobre el estado **guardado**: lo que no se reconoce no ocupa y deja un aviso en el registro.
	 */
	public function test_un_estado_inventado_no_ocupa_plaza(): void {
		$this->matricula( 5, 'baja' );

		$this->assertSame( 0, Cohorts::occupied( 7 ), 'un estado heredado no puede quedarse con la plaza' );
	}

	/**
	 * Y con un estado conocido la cuenta sigue igual: la plaza se cuenta, no se regala.
	 */
	public function test_los_estados_conocidos_siguen_ocupando(): void {
		$this->matricula( 1, 'pendiente' );
		$this->matricula( 2, 'activa' );
		$this->matricula( 3, 'completada' );
		$this->matricula( 4, 'cancelada' );

		$this->assertSame( 3, Cohorts::occupied( 7 ), 'pendiente, activa y completada ocupan; cancelada no' );
	}

	/**
	 * Un estado vacío (la meta no está) tampoco ocupa: no hay nada que reconocer.
	 */
	public function test_un_estado_vacio_no_ocupa_plaza(): void {
		$this->matricula( 5, '' );

		$this->assertSame( 0, Cohorts::occupied( 7 ) );
	}

	/**
	 * Sin matrículas no hay plazas ocupadas.
	 */
	public function test_sin_matriculas_no_hay_ocupacion(): void {
		$this->assertSame( 0, Cohorts::occupied( 7 ) );
	}

	/**
	 * Una convocatoria sin matrículas de otra convocatoria no se cuenta.
	 */
	public function test_cada_convocatoria_cuenta_lo_suyo(): void {
		$this->matricula( 1, 'activa', 7 );
		$this->matricula( 2, 'activa', 8 );

		$this->assertSame( 1, Cohorts::occupied( 7 ) );
		$this->assertSame( 1, Cohorts::occupied( 8 ) );
	}

	/**
	 * Da de alta una matrícula de la convocatoria indicada.
	 *
	 * @param int    $id      Identificador.
	 * @param string $estado  Estado de la matrícula.
	 * @param int    $cohorte Convocatoria (7 por defecto).
	 */
	private function matricula( int $id, string $estado, int $cohorte = 7 ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]                                        = \Convoca\Courses\CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]          = $estado;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'convocatoria_id' ] = (string) $cohorte;
	}
}
