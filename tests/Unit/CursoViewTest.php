<?php
/**
 * La ficha del curso se compone desde sus relaciones, no desde el contenido.
 *
 * Lo que se prueba aquí es la decisión de diseño: el curso no lleva escrita la estructura. Los módulos,
 * las lecciones, las tareas y el examen salen de sus vínculos, y un examen se coloca en su módulo o al
 * final según su `modulo_id`. Si alguien vuelve a resolver el examen por un id pegado en el contenido,
 * estas pruebas no lo verán (el contenido no se lee), pero sí verán que el examen correcto aparece.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Curso_View;
use PHPUnit\Framework\TestCase;

/**
 * Composición de la ficha del curso.
 */
class CursoViewTest extends TestCase {

	/**
	 * Carga la clase y deja el escenario limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_cc_posts']                    = array();
		$GLOBALS['convoca_test_post_types']      = array();
		$GLOBALS['convoca_test_meta']            = array();
		$GLOBALS['convoca_test_titulos']         = array();
		$GLOBALS['convoca_courses_do_shortcode'] = array();
		$GLOBALS['convoca_test_usuarios']        = array();

		require_once dirname( __DIR__, 2 ) . '/includes/Curso_View.php';

		$GLOBALS['convoca_test_post_types'][100] = 'convoca_curso';
		$GLOBALS['convoca_test_titulos'][100]    = 'Curso de prueba';
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_cc_posts']                    = array();
		$GLOBALS['convoca_test_post_types']      = array();
		$GLOBALS['convoca_test_meta']            = array();
		$GLOBALS['convoca_courses_do_shortcode'] = array();
	}

	/**
	 * Siembra módulos y lecciones.
	 *
	 * @param int $modulo_examen Módulo del examen (0 = del curso).
	 */
	private function sembrar_estructura( int $modulo_examen = 0 ): void {
		$GLOBALS['_cc_posts']['convoca_modulo'] = array(
			array(
				'id'     => 10,
				'objeto' => (object) array(
					'ID'           => 10,
					'post_title'   => 'Módulo 1. Empieza aquí',
					'post_content' => '',
				),
				'meta'   => array( '_convoca_curso_id' => 100 ),
			),
			array(
				'id'     => 11,
				'objeto' => (object) array(
					'ID'           => 11,
					'post_title'   => 'Módulo 2. Sigue',
					'post_content' => '',
				),
				'meta'   => array( '_convoca_curso_id' => 100 ),
			),
		);

		$GLOBALS['_cc_posts']['convoca_leccion'] = array(
			array(
				'id'     => 20,
				'objeto' => (object) array(
					'ID'           => 20,
					'post_title'   => 'Lección A',
					'post_content' => 'Contenido A',
				),
				'meta'   => array( '_convoca_modulo_id' => 10 ),
			),
			array(
				'id'     => 21,
				'objeto' => (object) array(
					'ID'           => 21,
					'post_title'   => 'Lección B',
					'post_content' => 'Contenido B',
				),
				'meta'   => array( '_convoca_modulo_id' => 11 ),
			),
		);

		$GLOBALS['convoca_test_post_types'][200] = 'convoca_examen';
		$GLOBALS['convoca_test_titulos'][200]    = 'Examen final';
		$GLOBALS['convoca_test_meta'][200]       = array(
			'_convoca_curso_id'  => 100,
			'_convoca_modulo_id' => $modulo_examen,
		);
	}

	/**
	 * Un curso que no existe no revienta: avisa.
	 */
	public function test_un_curso_inexistente_avisa(): void {
		$html = Curso_View::render( 999 );

		$this->assertStringContainsString( 'Este curso no existe', $html );
	}

	/**
	 * La ficha compone portada, programa y evaluación en ese orden.
	 */
	public function test_compone_portada_programa_y_evaluacion(): void {
		$this->sembrar_estructura( 0 );

		$html = Curso_View::render( 100 );

		$this->assertStringContainsString( '[convoca_curso id="100"]', $html, 'la portada la sigue pintando el shortcode de siempre' );
		$this->assertStringContainsString( 'Programa del curso', $html );
		$this->assertStringContainsString( 'Módulo 1. Empieza aquí', $html );
		$this->assertStringContainsString( 'Módulo 2. Sigue', $html );
		$this->assertStringContainsString( 'Lección A', $html );
		$this->assertStringContainsString( 'Lección B', $html );
		$this->assertStringContainsString( 'Evaluación final', $html );
		$this->assertStringContainsString( '[convoca_curso_examen id="200"]', $html );
	}

	/**
	 * Un examen con módulo se pinta dentro de ese módulo y no al final.
	 */
	public function test_el_examen_de_un_modulo_va_en_su_modulo(): void {
		$this->sembrar_estructura( 11 );

		$html = Curso_View::render( 100 );

		$this->assertStringNotContainsString( 'Evaluación final', $html, 'no se duplica al final' );
		$this->assertStringContainsString( '[convoca_curso_examen id="200"]', $html );

		// Y va después del módulo 2, no del 1.
		$pos_modulo_2 = strpos( $html, 'Módulo 2. Sigue' );
		$pos_examen   = strpos( $html, '[convoca_curso_examen id="200"]' );
		$this->assertGreaterThan( (int) $pos_modulo_2, (int) $pos_examen );
	}

	/**
	 * Un curso sin módulos lo dice; no deja una caja vacía.
	 */
	public function test_un_curso_sin_modulos_lo_dice(): void {
		$html = Curso_View::render( 100 );

		$this->assertStringContainsString( 'todavía no tiene lecciones', $html );
	}

	/**
	 * Las tareas del curso salen en su sección.
	 */
	public function test_las_tareas_salen_en_su_seccion(): void {
		$this->sembrar_estructura( 0 );
		$GLOBALS['convoca_test_post_types'][300] = 'convoca_tarea';
		$GLOBALS['convoca_test_meta'][300]       = array( '_convoca_curso_id' => 100 );

		$html = Curso_View::render( 100 );

		$this->assertStringContainsString( 'Actividades de práctica', $html );
		$this->assertStringContainsString( '[convoca_curso_tareas id="100"]', $html );
	}

	/**
	 * Sin tareas no aparece la sección (ni un título huérfano).
	 */
	public function test_sin_tareas_no_hay_seccion(): void {
		$this->sembrar_estructura( 0 );

		$this->assertStringNotContainsString( 'Actividades de práctica', Curso_View::render( 100 ) );
	}

	/**
	 * La plantilla se incluye en el ámbito global: la clase tiene que ir con su nombre completo.
	 *
	 * Sin esto, el sitio devolvió un 500 («Class "Curso_View" not found») en cuanto se encendió la
	 * plantilla propia en producción.
	 */
	public function test_la_plantilla_llama_a_la_vista_con_su_nombre_completo(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un fichero del propio plugin.
		$plantilla = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/curso-completo.php' );

		$this->assertStringContainsString( '\\Convoca\\Courses\\Curso_View::render', $plantilla );
		$this->assertStringNotContainsString( '<?php echo Curso_View::', $plantilla );
	}
}
