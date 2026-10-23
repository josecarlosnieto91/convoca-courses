<?php
/**
 * Permisos del curso: quién entra, quién está caducado y quién puede examinarse.
 *
 * `Access` estaba al 22,5 % (69 statements sin cubrir) y es la puerta de todo: si esto se equivoca,
 * entra quien no debe o se queda fuera quien sí debe. Las funciones puras van primero (el cálculo del
 * porcentaje y la caducidad) y después las que deciden con datos.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de acceso al curso.
 */
class AccessPermisosTest extends TestCase {

	/**
	 * Momento fijo de las pruebas.
	 *
	 * @var int
	 */
	private const AHORA = 1_761_220_800;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$GLOBALS['convoca_courses_ahora'] = self::AHORA;
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Leccion.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Progress.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_caps']       = array();
		$GLOBALS['_cc_posts']               = array();
		$GLOBALS['wpdb']->vars              = array();
	}

	/**
	 * Sin plazo ni fecha de inicio no hay caducidad.
	 */
	public function test_sin_plazo_no_caduca(): void {
		$this->assertFalse( Access::access_expired( 100, 0, self::AHORA ) );
		$this->assertFalse( Access::access_expired( 0, 30, self::AHORA ) );
	}

	/**
	 * La caducidad salta justo al cumplirse el plazo.
	 */
	public function test_la_caducidad_salta_al_cumplirse_el_plazo(): void {
		$inicio = self::AHORA - ( 30 * 86400 );

		$this->assertTrue( Access::access_expired( $inicio, 30, self::AHORA ), 'el día 30 ya está caducado' );
		$this->assertFalse( Access::access_expired( $inicio + 1, 30, self::AHORA ), 'un segundo antes, no' );
	}

	/**
	 * El porcentaje exigido: el configurado, o el 100 % de su ámbito.
	 */
	public function test_el_porcentaje_exigido(): void {
		$this->assertSame( 50.0, Access::required_percent( 'porcentaje', 50 ) );
		$this->assertSame( 100.0, Access::required_percent( 'porcentaje', 250 ), 'no se puede exigir más del 100 %' );
		$this->assertSame( 0.0, Access::required_percent( 'porcentaje', -5 ), 'ni menos de cero' );
		$this->assertSame( 100.0, Access::required_percent( 'todas', 10 ), 'en modo todas se exige el curso entero' );
		$this->assertSame( 100.0, Access::required_percent( 'modulo', 10 ), 'en modo módulo, sus lecciones' );
	}

	/**
	 * El progreso se compara con el exigido, con margen para los decimales.
	 */
	public function test_el_progreso_se_compara_con_el_exigido(): void {
		$this->assertTrue( Access::progress_satisfied( 'porcentaje', 50, 50.0 ) );
		$this->assertTrue( Access::progress_satisfied( 'porcentaje', 50, 49.9999 ), 'los decimales del reparto no bloquean' );
		$this->assertFalse( Access::progress_satisfied( 'porcentaje', 50, 49.0 ) );
		$this->assertFalse( Access::progress_satisfied( 'todas', 0, 99.9 ) );
	}

	/**
	 * Quien lleva el curso entra y puede examinarse sin más.
	 */
	public function test_quien_gestiona_el_curso_entra_siempre(): void {
		$GLOBALS['convoca_test_caps']['convoca_manage_courses'] = true;

		$this->assertTrue( Access::can_manage() );
		$this->assertTrue( Access::can_take_exam( 9, 3 ) );
		$this->assertTrue( Access::can_submit_task( 9, 3 ) );
	}

	/**
	 * Quien no está matriculado no puede examinarse ni entregar tareas.
	 */
	public function test_sin_matricula_no_se_puede(): void {
		$this->assertFalse( Access::can_manage() );
		$this->assertFalse( Access::is_enrolled( 9, 3 ) );
		$this->assertFalse( Access::can_take_exam( 9, 3 ) );
		$this->assertFalse( Access::can_submit_task( 9, 3 ) );
	}

	/**
	 * Matriculado y con el acceso en vigor: puede entregar tareas.
	 */
	public function test_matriculado_y_en_vigor_puede_entregar_tareas(): void {
		$this->matricula( 44, 3, 9, 'activa' );

		$this->assertTrue( Access::is_enrolled( 9, 3 ) );
		$this->assertTrue( Access::can_submit_task( 9, 3 ) );
	}

	/**
	 * Con el curso terminado no se puede entregar nada más.
	 */
	public function test_con_el_curso_terminado_no_se_entrega(): void {
		$this->matricula( 44, 3, 9, 'activa' );
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_fin' ] = gmdate( 'Y-m-d', self::AHORA - ( 5 * 86400 ) );

		$this->assertFalse( Access::can_submit_task( 9, 3 ) );
		$this->assertFalse( Access::can_take_exam( 9, 3 ) );
	}

	/**
	 * Con el examen pedido sin progreso mínimo, la matrícula y la caducidad son lo único que decide.
	 *
	 * Se baja el listón a 0 % a propósito: así el progreso no puede tapar los otros dos permisos, que
	 * es justo lo que hacía que las mutaciones de esas guardas no se notaran.
	 */
	public function test_sin_liston_de_progreso_deciden_matricula_y_caducidad(): void {
		$this->curso( 3 );
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'acceso_examen' ]            = 'porcentaje';
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'acceso_examen_porcentaje' ] = '0';

		$this->assertFalse( Access::can_take_exam( 9, 3 ), 'sin matrícula no se puede' );

		$this->matricula( 44, 3, 9, 'activa' );
		$this->assertTrue( Access::can_take_exam( 9, 3 ), 'matriculada y en vigor sí' );

		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_fin' ] = gmdate( 'Y-m-d', self::AHORA - 86400 );
		$this->assertFalse( Access::can_take_exam( 9, 3 ), 'con el curso terminado no, aunque esté matriculada' );
	}

	/**
	 * Sin matrícula no se puede decir que el curso esté caducado para esta persona.
	 *
	 * El curso tiene la fecha de fin pasada a propósito: sin la guarda de la matrícula, cualquiera que
	 * pasara por aquí saldría «caducado» sin haber estado nunca matriculado.
	 */
	public function test_sin_matricula_no_hay_caducidad(): void {
		$this->curso( 3 );
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_fin' ] = gmdate( 'Y-m-d', self::AHORA - 86400 );

		$this->assertFalse( Access::is_expired( 9, 3 ) );

		$this->matricula( 44, 3, 9, 'activa' );
		$this->assertTrue( Access::is_expired( 9, 3 ), 'con matrícula y el curso terminado, sí' );
	}

	/**
	 * Un curso sin lecciones exige el 100 % del módulo, y sin lecciones es cero.
	 */
	public function test_un_modulo_sin_lecciones_no_bloquea(): void {
		$this->assertSame( 100.0, Access::scope_percent( 9, 3, 99 ), 'un módulo sin lecciones no puede bloquear' );
	}

	/**
	 * La ventana de matrícula depende de las fechas del curso.
	 */
	public function test_la_ventana_de_matricula(): void {
		$this->curso( 3 );
		$this->assertTrue( Access::is_enrollment_open( 3 ), 'sin fechas, siempre abierta' );

		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_cierre_matricula' ] = gmdate( 'Y-m-d', self::AHORA - ( 2 * 86400 ) );

		$this->assertFalse( Access::is_enrollment_open( 3 ), 'con el cierre pasado, cerrada' );
	}

	/**
	 * Un curso que aún no ha abierto la matrícula no la tiene abierta.
	 */
	public function test_una_matricula_que_aun_no_ha_abierto(): void {
		$this->curso( 3 );
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_apertura_matricula' ] = gmdate( 'Y-m-d', self::AHORA + ( 10 * 86400 ) );

		$this->assertFalse( Access::is_enrollment_open( 3 ) );
	}

	/**
	 * Da de alta un curso.
	 *
	 * @param int $id Curso.
	 */
	private function curso( int $id ): void {
		$GLOBALS['convoca_test_posts'][ $id ]      = (object) array(
			'ID'          => $id,
			'post_type'   => CPT_Curso::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Curso ' . $id,
		);
		$GLOBALS['convoca_test_post_types'][ $id ] = CPT_Curso::TYPE;
	}

	/**
	 * Da de alta una matrícula.
	 *
	 * @param int    $id      Matrícula.
	 * @param int    $curso   Curso.
	 * @param int    $persona Persona.
	 * @param string $estado  Estado.
	 */
	private function matricula( int $id, int $curso, int $persona, string $estado ): void {
		$this->curso( $curso );

		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = $estado;
	}
}
