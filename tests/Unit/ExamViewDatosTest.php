<?php
/**
 * Pantalla del examen: los datos que se le pasan a la plantilla.
 *
 * `Exam_View` estaba al 0 % (81 statements) y es lo que decide qué ve quien está examinándose: si
 * puede presentarse, cuántos intentos le quedan, si está caducado, qué preguntas tiene delante y qué
 * resultado se le enseña.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Attempts;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\Exam_View;
use Convoca\Courses\Handlers;
use Convoca\Courses\Installer;
use Convoca\Courses\Questions;
use PHPUnit\Framework\TestCase;

/**
 * Datos de la pantalla del examen.
 */
class ExamViewDatosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Leccion.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Questions.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Quiz.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Attempts.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Roster.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Handlers.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Exam_View.php';
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
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_campos']     = array();
		$GLOBALS['convoca_courses_titulos'] = array();
	}

	/**
	 * Sin examen configurado no hay curso ni permiso, pero los datos salen igual.
	 */
	public function test_sin_examen_los_datos_salen_con_lo_minimo(): void {
		$datos = Exam_View::data( 7, 9 );

		$this->assertSame( 7, $datos['exam_id'] );
		$this->assertSame( 0, $datos['course_id'] );
		$this->assertFalse( $datos['enrolled'] );
		$this->assertTrue( $datos['logged_in'] );
		$this->assertFalse( $datos['can_take'], 'sin curso no hay examen que presentar' );
		$this->assertFalse( $datos['expired'] );
		$this->assertSame( '', $datos['course_url'], 'sin curso no hay enlace al curso' );
		$this->assertNull( $datos['open'] );
		$this->assertSame( array(), $datos['questions'] );
	}

	/**
	 * Sin sesión no hay nada que presentar.
	 */
	public function test_sin_sesion_no_se_puede_presentar(): void {
		$this->examen( 7, 3 );

		$datos = Exam_View::data( 7, 0 );

		$this->assertFalse( $datos['logged_in'] );
		$this->assertFalse( $datos['can_take'] );
		$this->assertFalse( $datos['expired'], 'sin sesión no se puede decir que esté caducado' );
	}

	/**
	 * El historial de intentos vacío no consulta si faltan datos.
	 */
	public function test_el_historial_sin_persona_o_sin_examen_esta_vacio(): void {
		$this->assertSame( array(), Exam_View::attempts_data( 0, 7 ) );
		$this->assertSame( array(), Exam_View::attempts_data( 9, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );
	}

	/**
	 * El historial devuelve los intentos cerrados normalizados.
	 */
	public function test_el_historial_devuelve_los_intentos_cerrados(): void {
		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'id'         => '11',
					'exam_id'    => '7',
					'user_id'    => '9',
					'estado'     => 'enviado',
					'puntuacion' => '7.5',
				),
			),
		);

		$historial = Exam_View::attempts_data( 9, 7 );

		$this->assertCount( 1, $historial );
		$this->assertSame( 11, $historial[0]['id'] );
		$this->assertSame( 7.5, $historial[0]['puntuacion'] );
	}

	/**
	 * Sin filas no hay historial.
	 */
	public function test_sin_filas_no_hay_historial(): void {
		$this->assertSame( array(), Exam_View::attempts_data( 9, 7 ) );
	}

	/**
	 * El aviso de la URL se traduce a su mensaje; sin aviso no hay nada.
	 */
	public function test_el_aviso_de_la_url_se_traduce(): void {
		$this->assertSame( '', $this->aviso( Exam_View::data( 7, 9 ) ) );

		$_GET['convoca_msg'] = 'exam_expired';
		$datos               = Exam_View::data( 7, 9 );

		$this->assertNotSame( '', $this->aviso( $datos ), 'un aviso conocido se pinta' );
		$this->assertSame( Handlers::message( 'exam_expired' ), $this->aviso( $datos ) );
	}

	/**
	 * Un intento abierto trae sus preguntas.
	 */
	public function test_un_intento_abierto_trae_sus_preguntas(): void {
		$this->examen( 7, 3 );
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'          => '11',
				'exam_id'     => '7',
				'course_id'   => '3',
				'user_id'     => '9',
				'estado'      => 'abierto',
				'iniciado_en' => '2026-10-23 09:00:00',
				'preguntas'   => '[5]',
			),
			array(
				'id'        => '5',
				'exam_id'   => '7',
				'course_id' => '3',
				'tipo'      => 'unica',
				'enunciado' => 'Pregunta',
				'opciones'  => '["A","B"]',
				'correctas' => '[0]',
				'puntos'    => '1',
			),
		);

		$datos = Exam_View::data( 7, 9 );

		$this->assertNotNull( $datos['open'] );
		$this->assertCount( 1, $datos['questions'], 'la pregunta del intento entra' );
		$this->assertSame( 5, $datos['questions'][0]['id'] );
	}

	/**
	 * Los datos de configuración del examen llegan a la plantilla.
	 */
	public function test_la_configuracion_del_examen_llega_a_la_plantilla(): void {
		$this->examen(
			7,
			3,
			array(
				'tiempo_maximo'     => '90',
				'intentos'          => '3',
				'nota_minima'       => '75',
				'retroalimentacion' => 'al_cerrar',
			)
		);
		$GLOBALS['convoca_test_campos'][7] = array( 'post_content' => 'Instrucciones del examen' );

		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'id'         => '11',
					'exam_id'    => '7',
					'user_id'    => '9',
					'estado'     => 'enviado',
					'puntuacion' => '6',
				),
			),
		);
		$datos                       = Exam_View::data( 7, 9 );

		$this->assertSame( 3, $datos['course_id'] );
		$this->assertSame( 'https://example.test/?p=3', $datos['course_url'], 'el enlace al curso se pinta' );
		$this->assertCount( 1, $datos['attempts'], 'el historial llega a la pantalla' );
		$this->assertSame( 90, $datos['time_limit'] );
		$this->assertSame( 3, $datos['attempts_max'] );
		$this->assertSame( 75.0, $datos['min_score'] );
		$this->assertSame( 'Instrucciones del examen', $datos['intro'], 'la introducción es el contenido del examen' );
		$this->assertSame( 'al_cerrar', $datos['feedback'], 'la corrección configurada llega a la pantalla' );
	}

	/**
	 * Un intento pedido en la URL se devuelve solo si es de esta persona.
	 */
	public function test_un_intento_pedido_en_la_url_se_devuelve(): void {
		$this->examen( 7, 3 );
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'         => '11',
				'exam_id'    => '7',
				'user_id'    => '9',
				'estado'     => 'enviado',
				'puntuacion' => '7.5',
			),
		);

		$_GET['convoca_intento'] = '11';
		// El intento se consulta dos veces: para saber si hay uno abierto y para el pedido en la URL.
		$GLOBALS['wpdb']->filas = array( $GLOBALS['wpdb']->filas[0], $GLOBALS['wpdb']->filas[0] );

		$datos = Exam_View::data( 7, 9 );

		$this->assertNotNull( $datos['result'], 'el intento pedido se devuelve' );
		$this->assertSame( 11, $datos['result']['id'] );
	}

	/**
	 * Un intento de otra persona no se devuelve.
	 */
	public function test_un_intento_ajeno_no_se_devuelve(): void {
		$this->examen( 7, 3 );
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'      => '11',
				'exam_id' => '7',
				'user_id' => '10',
				'estado'  => 'enviado',
			),
		);

		$_GET['convoca_intento'] = '11';
		$GLOBALS['wpdb']->filas  = array( $GLOBALS['wpdb']->filas[0], $GLOBALS['wpdb']->filas[0] );

		$datos = Exam_View::data( 7, 9 );

		$this->assertNull( $datos['result'], 'el intento de otra persona no se enseña' );
	}

	/**
	 * Un curso con la fecha de fin pasada marca el examen como caducado.
	 */
	public function test_un_curso_terminado_marca_el_examen_caducado(): void {
		$this->examen( 7, 3 );
		// La fecha de fin es del curso, no del examen.
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_fin' ] = gmdate( 'Y-m-d', 1_600_000_000 );
		$this->matricula_activa( 44, 3, 9 );

		$datos = Exam_View::data( 7, 9 );

		// Sin resolver la matrícula, `is_expired` no puede decir nada: se comprueba el paso previo.
		$this->assertSame( 44, \Convoca\Courses\Enrollment::active_id( 9, 3 ), 'la matrícula activa se encuentra' );
		$this->assertTrue( $datos['expired'], 'con el curso terminado no se puede seguir' );
		$this->assertTrue( $datos['enrolled'], 'y sigue matriculada' );
	}

	/**
	 * Sin matrícula no se puede decir que el curso esté caducado para esta persona.
	 */
	public function test_sin_matricula_no_esta_caducado(): void {
		$this->examen( 7, 3 );
		$GLOBALS['convoca_test_meta'][3][ CPT_Base::META_PREFIX . 'fecha_fin' ] = gmdate( 'Y-m-d', 1_600_000_000 );

		$datos = Exam_View::data( 7, 9 );

		$this->assertFalse( $datos['expired'] );
		$this->assertFalse( $datos['enrolled'] );
	}

	/**
	 * Saca el aviso de los datos.
	 *
	 * @param array<string, mixed> $datos Datos de la pantalla.
	 * @return string
	 */
	private function aviso( array $datos ): string {
		return isset( $datos['notice'] ) ? (string) $datos['notice'] : '';
	}

	/**
	 * Da de alta una matrícula activa.
	 *
	 * @param int $id      Matrícula.
	 * @param int $curso   Curso.
	 * @param int $persona Persona.
	 */
	private function matricula_activa( int $id, int $curso, int $persona ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = \Convoca\Courses\CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
	}

	/**
	 * Da de alta un examen de un curso.
	 *
	 * @param int                  $id    Examen.
	 * @param int                  $curso Curso.
	 * @param array<string,string> $meta  Configuración extra.
	 */
	private function examen( int $id, int $curso, array $meta = array() ): void {
		$GLOBALS['convoca_test_posts'][ $id ]                                      = (object) array(
			'ID'           => $id,
			'post_type'    => CPT_Examen::TYPE,
			'post_status'  => 'publish',
			'post_title'   => 'Examen ' . $id,
			'post_content' => '',
		);
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Examen::TYPE;
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
