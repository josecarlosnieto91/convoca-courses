<?php
/**
 * Shortcodes del curso: registro, resolución del curso y lo que pasa cuando no hay curso.
 *
 * `Shortcodes` estaba al 36,5 %. Lo que se prueba aquí es lo que ve cualquiera que pegue un shortcode
 * en una página: cuál es el curso, qué se pinta cuando no lo hay, y que cada uno de los siete quede
 * registrado con su nombre.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\Shortcodes;
use PHPUnit\Framework\TestCase;

/**
 * Registro y resolución de los shortcodes.
 */
class ShortcodesRegistroTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Hierarchy.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Shortcodes.php';
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
		$GLOBALS['convoca_test_posts']         = array();
		$GLOBALS['convoca_test_meta']          = array();
		$GLOBALS['convoca_test_post_types']    = array();
		$GLOBALS['convoca_courses_shortcodes'] = array();
		$GLOBALS['convoca_courses_id_actual']  = 0;
		$GLOBALS['_cc_posts']                  = array();
	}

	/**
	 * Los siete shortcodes quedan registrados con su nombre y su devolución de llamada.
	 */
	public function test_los_siete_shortcodes_se_registran(): void {
		Shortcodes::register();

		$registrados = $GLOBALS['convoca_courses_shortcodes'];

		$this->assertCount( 7, $registrados );
		$this->assertArrayHasKey( 'convoca_curso', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_lecciones', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_leccion', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_examen', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_tareas', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_notas', $registrados );
		$this->assertArrayHasKey( 'convoca_curso_acta', $registrados );
		$this->assertSame( array( Shortcodes::class, 'course' ), $registrados['convoca_curso'], 'cada nombre va a su método' );
	}

	/**
	 * Sin curso ni en el atributo ni en la entrada, todos los shortcodes avisan.
	 */
	public function test_sin_curso_todos_avisan(): void {
		$aviso = '<div class="convoca-courses-notice convoca-courses-notice--error">No se ha encontrado el curso o la lección indicados.</div>';

		$this->assertSame( $aviso, Shortcodes::course( array() ) );
		$this->assertSame( $aviso, Shortcodes::lessons( array() ) );
		$this->assertSame( $aviso, Shortcodes::lesson( array() ) );
		$this->assertSame( $aviso, Shortcodes::exam( array() ) );
		$this->assertSame( $aviso, Shortcodes::tasks( array() ) );
		$this->assertSame( $aviso, Shortcodes::grades( array() ) );
		$this->assertSame( $aviso, Shortcodes::gradebook( array() ) );
	}

	/**
	 * Un identificador que no es de un curso tampoco vale.
	 */
	public function test_un_identificador_que_no_es_curso_avisa(): void {
		$GLOBALS['convoca_test_posts'][7] = (object) array(
			'ID'          => 7,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Una entrada',
		);

		$this->assertStringContainsString( 'No se ha encontrado', Shortcodes::course( array( 'id' => '7' ) ) );
	}

	/**
	 * Un curso sin módulos no devuelve módulos.
	 */
	public function test_un_curso_sin_modulos_no_devuelve_modulos(): void {
		$this->curso( 3 );

		$this->assertSame( array(), Shortcodes::modules_data( 3, 9 ) );
	}

	/**
	 * Con un módulo y una lección, los datos salen numerados y con su estado.
	 */
	public function test_los_modulos_salen_numerados_con_sus_lecciones(): void {
		$this->curso( 3 );
		$GLOBALS['convoca_test_posts'][20]                                      = (object) array(
			'ID'           => 20,
			'post_type'    => \Convoca\Courses\CPT_Modulo::TYPE,
			'post_status'  => 'publish',
			'post_title'   => 'Módulo uno',
			'post_content' => 'Introducción',
		);
		$GLOBALS['convoca_test_post_types'][20]                                 = \Convoca\Courses\CPT_Modulo::TYPE;
		$GLOBALS['convoca_test_meta'][20][ CPT_Base::META_PREFIX . 'curso_id' ] = '3';
		$GLOBALS['_cc_posts'][ \Convoca\Courses\CPT_Modulo::TYPE ][]            = array(
			'id'     => 20,
			'status' => 'publish',
			'objeto' => $GLOBALS['convoca_test_posts'][20],
			'meta'   => array( CPT_Base::META_PREFIX . 'curso_id' => '3' ),
		);

		$leccion                           = (object) array(
			'ID'          => 30,
			'post_type'   => \Convoca\Courses\CPT_Leccion::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Lección uno',
		);
		$GLOBALS['convoca_test_posts'][30] = $leccion;
		$GLOBALS['_cc_posts'][ \Convoca\Courses\CPT_Leccion::TYPE ][] = array(
			'id'     => 30,
			'status' => 'publish',
			'objeto' => $leccion,
			'meta'   => array( CPT_Base::META_PREFIX . 'modulo_id' => '20' ),
		);

		$segunda                           = (object) array(
			'ID'          => 31,
			'post_type'   => \Convoca\Courses\CPT_Leccion::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Lección dos',
		);
		$GLOBALS['convoca_test_posts'][31] = $segunda;
		$GLOBALS['_cc_posts'][ \Convoca\Courses\CPT_Leccion::TYPE ][] = array(
			'id'     => 31,
			'status' => 'publish',
			'objeto' => $segunda,
			'meta'   => array( CPT_Base::META_PREFIX . 'modulo_id' => '20' ),
		);

		$modulos = Shortcodes::modules_data( 3, 9 );

		$this->assertCount( 1, $modulos );
		$this->assertSame( 20, $modulos[0]['id'] );
		$this->assertSame( 'Módulo uno', $modulos[0]['title'] );
		$this->assertSame( 'Introducción', $modulos[0]['content'] );
		$this->assertCount( 2, $modulos[0]['lessons'], 'las lecciones del módulo entran' );
		$leccion = $modulos[0]['lessons'][0];
		$this->assertSame( 30, $leccion['id'] );
		$this->assertSame( 'Lección uno', $leccion['title'] );
		$this->assertSame( 1, $leccion['number'], 'la numeración empieza en uno y no se reinicia por módulo' );
		$this->assertSame( 'texto', $leccion['type'], 'un tipo que no está en la lista cae a texto' );
		$this->assertFalse( $leccion['completed'], 'sin progreso no está completada' );
		$this->assertSame( 2, $modulos[0]['lessons'][1]['number'], 'la numeración sigue 1, 2 y no se queda clavada' );
		$this->assertArrayHasKey( 'locked', $leccion, 'cada lección dice si está bloqueada' );
		// El bloqueo lo decide Access::is_unlocked() con la matrícula y las reglas del curso; aquí
		// solo se comprueba que la clave existe y es un booleano: afirmar un valor fijo sería probar
		// el arnés, no el shortcode.
		$this->assertIsBool( $leccion['locked'] );
	}

	/**
	 * Da de alta un curso.
	 *
	 * @param int $id Identificador.
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
}
