<?php
/**
 * Los shortcodes del curso: qué se registra y qué se hace cuando falta el curso o la lección.
 *
 * `Shortcodes` estaba al 0 % (233 statements). El valor está en las guardas: un shortcode sin
 * `id` —o con un `id` que no es un curso— tiene que devolver un aviso, no montar media pantalla
 * con datos vacíos. Se prueban las siete altas, las guardas de los siete bloques y el aviso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Shortcodes;
use PHPUnit\Framework\TestCase;

/**
 * Los siete shortcodes del curso y sus guardas.
 */
class ShortcodesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_shortcodes'] = array();
		$GLOBALS['convoca_courses_user_id']    = 0;
		$GLOBALS['convoca_courses_post_id']    = 0;
		$GLOBALS['convoca_courses_titulos']    = array();
		$_GET                                  = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Shortcodes.php';
	}

	/**
	 * Prepara el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_shortcodes'] = array();
		$GLOBALS['convoca_courses_user_id']    = 0;
		$GLOBALS['convoca_courses_post_id']    = 0;
		$GLOBALS['convoca_courses_titulos']    = array();
		$_GET                                  = array();
	}

	// ── el alta de los siete shortcodes ─────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_los_siete_shortcodes_quedan_registrados(): void {
		Shortcodes::register();

		$registrados = $GLOBALS['convoca_courses_shortcodes'];

		foreach ( array(
			'convoca_curso',
			'convoca_curso_lecciones',
			'convoca_curso_leccion',
			'convoca_curso_examen',
			'convoca_curso_tareas',
			'convoca_curso_notas',
			'convoca_curso_acta',
		) as $tag ) {
			$this->assertArrayHasKey( $tag, $registrados, 'falta el shortcode ' . $tag );
			$this->assertIsCallable( $registrados[ $tag ] );
		}
	}

	/**
	 * Caso de prueba.
	 */
	public function test_cada_shortcode_apunta_a_su_metodo(): void {
		Shortcodes::register();

		$this->assertSame( array( Shortcodes::class, 'course' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso'] );
		$this->assertSame( array( Shortcodes::class, 'lessons' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_lecciones'] );
		$this->assertSame( array( Shortcodes::class, 'lesson' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_leccion'] );
		$this->assertSame( array( Shortcodes::class, 'exam' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_examen'] );
		$this->assertSame( array( Shortcodes::class, 'tasks' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_tareas'] );
		$this->assertSame( array( Shortcodes::class, 'grades' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_notas'] );
		$this->assertSame( array( Shortcodes::class, 'gradebook' ), $GLOBALS['convoca_courses_shortcodes']['convoca_curso_acta'] );
	}

	// ── las guardas: sin curso, aviso ───────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_sin_id_y_sin_curso_actual_las_notas_avisan(): void {
		$html = Shortcodes::grades( array() );

		$this->assertStringContainsString( 'No se ha encontrado el curso', $html );
		$this->assertStringContainsString( 'convoca-courses-notice--error', $html, 'el aviso se marca como error, no como contenido' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_sin_id_y_sin_curso_actual_el_acta_avisa(): void {
		$this->assertStringContainsString( 'No se ha encontrado el curso', Shortcodes::gradebook( array() ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_sin_curso_las_lecciones_y_el_curso_avisan(): void {
		$this->assertStringContainsString( 'No se ha encontrado el curso', Shortcodes::lessons( array() ) );
		$this->assertStringContainsString( 'No se ha encontrado el curso', Shortcodes::course( array() ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_una_leccion_que_no_existe_avisa(): void {
		$this->assertStringContainsString( 'No se ha encontrado el curso o la lección', Shortcodes::lesson( array( 'id' => '999' ) ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_un_examen_o_unas_tareas_sin_curso_avisan(): void {
		$this->assertStringContainsString( 'No se ha encontrado el curso o la lección', Shortcodes::exam( array() ) );
		$this->assertStringContainsString( 'No se ha encontrado el curso o la lección', Shortcodes::tasks( array() ) );
	}

	// ── el curso de la entrada actual ──────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_el_id_del_atributo_que_no_es_curso_cae_al_aviso(): void {
		// 4242 no es un curso: no se inventa nada con el id que llegue.
		$this->assertStringContainsString( 'No se ha encontrado', Shortcodes::grades( array( 'id' => '4242' ) ) );
	}
}
