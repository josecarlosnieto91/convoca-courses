<?php
/**
 * Entregas de tarea: entregar, corregir y listar.
 *
 * Completa `TaskReglasTest`, que cubre las reglas puras (plazo, estado, fila y lecturas). Aquí va lo
 * que escribe: `deliver()` —que reentrega sobre la misma fila en vez de duplicarla— y `grade()`, que
 * pone nota y avisa.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Tarea;
use Convoca\Courses\Installer;
use Convoca\Courses\Task;
use PHPUnit\Framework\TestCase;

/**
 * Entrega y corrección de tareas.
 */
class TaskEntregaTest extends TestCase {

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
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Tarea.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Hierarchy.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Progress.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Questions.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Quiz.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Attempts.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Roster.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Task.php';
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
		foreach ( array( 'convoca_test_meta', 'convoca_test_post_types', 'convoca_test_posts', 'convoca_courses_opciones', 'convoca_courses_acciones' ) as $clave ) {
			$GLOBALS[ $clave ] = array();
		}

		$GLOBALS['wpdb']->filas                = array();
		$GLOBALS['wpdb']->vars                 = array();
		$GLOBALS['wpdb']->resultados           = array();
		$GLOBALS['wpdb']->inserts              = array();
		$GLOBALS['wpdb']->actualizados         = array();
		$GLOBALS['wpdb']->borrados             = array();
		$GLOBALS['wpdb']->consultas            = array();
		$GLOBALS['convoca_courses_wpdb_falla'] = array();
	}

	/**
	 * Una tarea que no existe no se puede entregar.
	 */
	public function test_una_tarea_que_no_existe_no_se_entrega(): void {
		$r = Task::deliver( 404, 9, 'Mi trabajo' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_task', $r->get_error_code() );
	}

	/**
	 * Sin matrícula no se entrega.
	 */
	public function test_sin_matricula_no_se_entrega(): void {
		$this->tarea( 5, 3 );

		$r = Task::deliver( 5, 9, 'Mi trabajo' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_task_locked', $r->get_error_code() );
	}

	/**
	 * Una entrega vacía no vale: ni texto ni adjunto.
	 */
	public function test_una_entrega_vacia_no_vale(): void {
		$this->tarea( 5, 3 );
		$this->matricula( 44, 3, 9 );

		$r = Task::deliver( 5, 9, '   ' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_task_empty', $r->get_error_code() );
	}

	/**
	 * Con solo el adjunto la entrega vale.
	 */
	public function test_con_solo_el_adjunto_la_entrega_vale(): void {
		$this->tarea( 5, 3 );
		$this->matricula( 44, 3, 9 );

		$this->assertTrue( Task::deliver( 5, 9, '', 7 ) );
		$this->assertSame( 7, $GLOBALS['wpdb']->inserts[0][1]['adjunto_id'] );
	}

	/**
	 * La primera entrega se inserta, con su estado y su fecha.
	 */
	public function test_la_primera_entrega_se_inserta(): void {
		$this->tarea( 5, 3 );
		$this->matricula( 44, 3, 9 );

		$this->assertTrue( Task::deliver( 5, 9, 'Mi trabajo' ) );

		$insertada = $GLOBALS['wpdb']->inserts[0][1];
		$this->assertSame( 5, $insertada['task_id'] );
		$this->assertSame( 3, $insertada['course_id'] );
		$this->assertSame( 9, $insertada['user_id'] );
		$this->assertSame( 44, $insertada['enrollment_id'], 'la entrega queda ligada a la matrícula' );
		$this->assertSame( 'Mi trabajo', $insertada['contenido'] );
		$this->assertSame( 'entregada', $insertada['estado'], 'sin plazo, entregada' );
	}

	/**
	 * Una entrega con el plazo pasado se guarda como fuera de plazo.
	 */
	public function test_una_entrega_tarde_se_marca(): void {
		$this->tarea( 5, 3, gmdate( 'Y-m-d', self::AHORA - ( 3 * 86400 ) ) );
		$this->matricula( 44, 3, 9 );

		Task::deliver( 5, 9, 'Mi trabajo' );

		$this->assertSame( 'fuera_de_plazo', $GLOBALS['wpdb']->inserts[0][1]['estado'] );
	}

	/**
	 * Reentregar actualiza la misma fila en vez de duplicarla.
	 */
	public function test_reentregar_actualiza_la_misma_fila(): void {
		$this->tarea( 5, 3 );
		$this->matricula( 44, 3, 9 );
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'      => '11',
				'task_id' => '5',
				'user_id' => '9',
				'estado'  => 'entregada',
			),
		);

		$this->assertTrue( Task::deliver( 5, 9, 'Trabajo corregido' ) );

		$this->assertSame( array(), $GLOBALS['wpdb']->inserts, 'no se crea otra entrega' );
		$this->assertCount( 1, $GLOBALS['wpdb']->actualizados );
		$actualizada = $GLOBALS['wpdb']->actualizados[0][1];
		$this->assertSame( 'Trabajo corregido', $actualizada['contenido'] );
		$this->assertArrayNotHasKey( 'task_id', $actualizada, 'la clave no se reescribe' );
		$this->assertArrayNotHasKey( 'user_id', $actualizada );
	}

	/**
	 * Si la base falla, la entrega devuelve error.
	 */
	public function test_si_la_base_falla_la_entrega_devuelve_error(): void {
		$this->tarea( 5, 3 );
		$this->matricula( 44, 3, 9 );
		$GLOBALS['convoca_courses_wpdb_falla']['insert'] = true;

		$r = Task::deliver( 5, 9, 'Mi trabajo' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_task_db', $r->get_error_code() );
	}

	/**
	 * Corregir una entrega que no existe devuelve error.
	 */
	public function test_corregir_una_entrega_que_no_existe(): void {
		$r = Task::grade( 999, 7.0, 'Bien', 1 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_submission', $r->get_error_code() );
	}

	/**
	 * Corregir guarda la nota acotada, el comentario y quién corrige.
	 */
	public function test_corregir_guarda_la_nota(): void {
		$this->entrega( 11, 5, 3, 9 );

		$this->assertTrue( Task::grade( 11, 150.0, 'Muy bien', 1 ) );

		$datos = $GLOBALS['wpdb']->actualizados[0][1];
		$this->assertSame( 'corregida', $datos['estado'] );
		$this->assertSame( 100.0, $datos['nota'], 'la nota se acota a la escala' );
		$this->assertSame( 'Muy bien', $datos['comentario'] );
		$this->assertSame( 1, $datos['corregida_por'] );
	}

	/**
	 * Si la corrección no se guarda, se devuelve error.
	 */
	public function test_si_la_correccion_no_se_guarda_hay_error(): void {
		$this->entrega( 11, 5, 3, 9 );
		$GLOBALS['convoca_courses_wpdb_falla']['update'] = true;

		$r = Task::grade( 11, 7.0, 'Bien', 1 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_task_db', $r->get_error_code() );
	}

	/**
	 * Las entregas de un curso se normalizan; sin curso no se consulta.
	 */
	public function test_las_entregas_de_un_curso(): void {
		$this->assertSame( array(), Task::of_course( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );

		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'id'      => '11',
					'task_id' => '5',
					'user_id' => '9',
					'nota'    => '8.5',
					'estado'  => 'corregida',
				),
			),
		);

		$entregas = Task::of_course( 3 );

		$this->assertCount( 1, $entregas );
		$this->assertSame( 11, $entregas[0]['id'] );
		$this->assertSame( 8.5, $entregas[0]['nota'] );
	}

	/**
	 * Da de alta una tarea del curso, con plazo opcional.
	 *
	 * @param int    $id     Tarea.
	 * @param int    $curso  Curso.
	 * @param string $plazo  Fecha límite ('' = sin plazo).
	 */
	private function tarea( int $id, int $curso, string $plazo = '' ): void {
		$GLOBALS['convoca_test_posts'][ $id ]                                      = (object) array(
			'ID'          => $id,
			'post_type'   => CPT_Tarea::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Tarea ' . $id,
		);
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Tarea::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;

		if ( '' !== $plazo ) {
			$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'plazo' ] = $plazo;
		}

		$GLOBALS['convoca_test_posts'][ $curso ] = (object) array(
			'ID'          => $curso,
			'post_type'   => CPT_Curso::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Curso ' . $curso,
		);
	}

	/**
	 * Da de alta una matrícula activa.
	 *
	 * @param int $id      Matrícula.
	 * @param int $curso   Curso.
	 * @param int $persona Persona.
	 */
	private function matricula( int $id, int $curso, int $persona ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
	}

	/**
	 * Encola una entrega en el doble de la base.
	 *
	 * @param int $id      Entrega.
	 * @param int $tarea   Tarea.
	 * @param int $curso   Curso.
	 * @param int $persona Persona.
	 */
	private function entrega( int $id, int $tarea, int $curso, int $persona ): void {
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'        => (string) $id,
				'task_id'   => (string) $tarea,
				'course_id' => (string) $curso,
				'user_id'   => (string) $persona,
				'estado'    => 'entregada',
			),
		);
	}
}
