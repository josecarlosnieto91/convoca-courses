<?php
/**
 * Pantalla de tareas: los datos que se le pasan a la plantilla.
 *
 * `Task_View` estaba al 0 % (32 statements) y es lo que decide qué ve el alumnado en la pantalla de
 * tareas: qué se pide, qué ha entregado, si puede entregar y qué adjunto se enlaza.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Tarea;
use Convoca\Courses\Handlers;
use Convoca\Courses\Task_View;
use PHPUnit\Framework\TestCase;

/**
 * Datos de la pantalla de tareas.
 */
class TaskViewDatosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Leccion.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Tarea.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Handlers.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Task.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Task_View.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		$_GET = array();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		$GLOBALS['wpdb']->vars              = array();
		$GLOBALS['wpdb']->filas             = array();
		$GLOBALS['wpdb']->resultados        = array();
		$GLOBALS['wpdb']->consultas         = array();
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_titulos']    = array();
		$GLOBALS['convoca_courses_titulos'] = array();
		$GLOBALS['convoca_test_caps']       = array();
		$GLOBALS['convoca_test_adjuntos']   = array();
		$GLOBALS['convoca_test_filtros']    = array();
	}

	/**
	 * Sin curso no hay tareas ni permisos, y los datos salen igual.
	 */
	public function test_sin_curso_los_datos_salen_con_lo_minimo(): void {
		$datos = Task_View::data( 0, 9 );

		$this->assertSame( array(), $datos['tasks'] );
		$this->assertSame( 0, $datos['course_id'] );
		$this->assertSame( '', $datos['title'], 'sin curso no hay título que enseñar' );
		$this->assertTrue( $datos['logged_in'] );
		$this->assertFalse( $datos['enrolled'] );
		$this->assertFalse( $datos['can_submit'], 'sin curso no se entrega nada' );
	}

	/**
	 * Sin sesión no se consulta la entrega.
	 */
	public function test_sin_sesion_no_se_busca_entrega(): void {
		$this->tarea( 21, 3, array() );

		$datos = Task_View::data( 3, 0 );

		$this->assertFalse( $datos['logged_in'] );
		$this->assertCount( 1, $datos['tasks'], 'la tarea se lista aunque no haya sesión' );
		$this->assertNull( $datos['tasks'][0]['submission'] );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin persona no se consulta la entrega' );
	}

	/**
	 * Cada tarea lleva lo que la plantilla necesita para pintarla.
	 */
	public function test_cada_tarea_lleva_sus_datos(): void {
		$this->tarea(
			21,
			3,
			array(
				'plazo'   => '2026-11-30',
				'peso'    => '2.5',
				'adjunto' => '1',
			)
		);

		$datos = Task_View::data( 3, 9 );
		$tarea = $datos['tasks'][0];

		$this->assertSame( 21, $tarea['id'] );
		$this->assertSame( 'Tarea 21', $tarea['title'] );
		$this->assertSame( '2026-11-30', $tarea['deadline'] );
		$this->assertSame( 2.5, $tarea['weight'] );
		$this->assertTrue( $tarea['allows_file'] );
		$this->assertSame( '', $tarea['attachment'], 'sin adjunto no hay enlace' );
		$this->assertNotSame( '', (string) $tarea['nonce'], 'la entrega lleva su nonce' );
	}

	/**
	 * Una entrega con adjunto trae el enlace del fichero; sin adjunto no se inventa.
	 */
	public function test_la_entrega_con_adjunto_trae_su_enlace(): void {
		$this->tarea( 21, 3, array() );
		$GLOBALS['convoca_test_adjuntos'][55] = 'https://example.test/entrega.pdf';
		$GLOBALS['wpdb']->filas               = array(
			array(
				'id'         => '7',
				'task_id'    => '21',
				'user_id'    => '9',
				'texto'      => 'Mi entrega',
				'adjunto_id' => '55',
				'nota'       => '',
			),
		);
		$GLOBALS['wpdb']->resultados          = array();

		$datos = Task_View::data( 3, 9 );

		$this->assertNotNull( $datos['tasks'][0]['submission'], 'la entrega se encuentra' );
		$this->assertSame( 'https://example.test/entrega.pdf', $datos['tasks'][0]['attachment'] );

		$GLOBALS['wpdb']->filas = array(
			array(
				'id'         => '8',
				'task_id'    => '21',
				'user_id'    => '9',
				'texto'      => 'Sin fichero',
				'adjunto_id' => '0',
				'nota'       => '',
			),
		);

		$datos = Task_View::data( 3, 9 );

		$this->assertSame( '', $datos['tasks'][0]['attachment'], 'sin adjunto no hay enlace que pintar' );
	}

	/**
	 * La matrícula activa habilita la entrega; sin ella no.
	 */
	public function test_la_matricula_decide_si_se_puede_entregar(): void {
		$this->tarea( 21, 3, array() );

		$sin = Task_View::data( 3, 9 );
		$this->assertFalse( $sin['enrolled'] );
		$this->assertFalse( $sin['can_submit'] );

		$this->matricula_activa( 44, 3, 9 );

		$con = Task_View::data( 3, 9 );
		$this->assertTrue( $con['enrolled'] );
		$this->assertTrue( $con['can_submit'], 'matriculada y en plazo: puede entregar' );
	}

	/**
	 * Quien gestiona entrega sin estar matriculado.
	 */
	public function test_quien_gestiona_puede_entregar_sin_matricula(): void {
		$this->tarea( 21, 3, array() );
		$GLOBALS['convoca_test_caps']['convoca_manage_courses'] = true;

		$datos = Task_View::data( 3, 9 );

		$this->assertTrue( $datos['manage'] );
		$this->assertTrue( $datos['can_submit'] );
		$this->assertFalse( $datos['enrolled'], 'gestionar no es estar matriculada' );
	}

	/**
	 * El título del curso se pinta cuando hay curso.
	 */
	public function test_el_titulo_del_curso_se_pinta(): void {
		$this->tarea( 21, 3, array() );
		$GLOBALS['convoca_courses_titulos'][3] = 'Curso de prueba';

		$datos = Task_View::data( 3, 9 );

		$this->assertSame( 'Curso de prueba', $datos['title'] );
	}

	/**
	 * El aviso de la URL se traduce a su mensaje; sin aviso no hay nada.
	 */
	public function test_el_aviso_de_la_url_se_traduce(): void {
		$this->tarea( 21, 3, array() );

		$this->assertSame( '', Task_View::data( 3, 9 )['notice'] );

		$_GET['convoca_msg'] = 'task_late';

		$this->assertSame( Handlers::message( 'task_late' ), Task_View::data( 3, 9 )['notice'] );
	}

	/**
	 * Da de alta una matrícula activa.
	 *
	 * @param int $id      Matrícula.
	 * @param int $curso   Curso.
	 * @param int $persona Persona.
	 */
	private function matricula_activa( int $id, int $curso, int $persona ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
	}

	/**
	 * Da de alta una tarea de un curso.
	 *
	 * @param int                  $id    Tarea.
	 * @param int                  $curso Curso.
	 * @param array<string,string> $meta  Configuración extra.
	 */
	private function tarea( int $id, int $curso, array $meta ): void {
		$GLOBALS['convoca_test_posts'][ $id ]                                      = (object) array(
			'ID'           => $id,
			'post_type'    => CPT_Tarea::TYPE,
			'post_status'  => 'publish',
			'post_title'   => 'Tarea ' . $id,
			'post_content' => 'Enunciado de la tarea ' . $id,
		);
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Tarea::TYPE;
		$GLOBALS['convoca_test_titulos'][ $id ]                                    = 'Tarea ' . $id;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . $clave ] = $valor;
		}

		$GLOBALS['convoca_test_posts'][ $curso ] = (object) array(
			'ID'          => $curso,
			'post_type'   => CPT_Curso::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Curso ' . $curso,
		);
	}
}
