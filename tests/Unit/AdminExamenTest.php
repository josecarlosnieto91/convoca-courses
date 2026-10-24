<?php
/**
 * El editor de exámenes: configuración, banco de preguntas y avisos.
 *
 * Lo que se protege: el guardado del examen no puede escribir sin nonce ni sin capacidad; los manejadores de
 * preguntas **cortan** con `wp_die` si el nonce no vale o falta permiso (no siguen a medias); y el peso se recorta a
 * 0-100, que es lo que decide cuánto cuenta el examen en la nota.
 *
 * La capa de datos (`Questions`) se dobla: aquí se mide el manejador (nonce, permiso, redirección), no el SQL.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Admin_Examen;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Modulo;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
require_once dirname( __DIR__, 2 ) . '/includes/Hierarchy.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
require_once dirname( __DIR__ ) . '/../admin/class-admin-examen.php';

/**
 * Editor de exámenes.
 */
final class AdminExamenTest extends TestCase {

	/**
	 * Editor.
	 *
	 * @var Admin_Examen
	 */
	private Admin_Examen $editor;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_caps']        = array( 'convoca_manage_courses' => true );
		$GLOBALS['convoca_courses_nonce_ok'] = 1;
		$GLOBALS['convoca_test_cajas']       = array();
		$GLOBALS['convoca_courses_acciones'] = array();
		$GLOBALS['convoca_test_titulos']     = array();
		$GLOBALS['convoca_examen_preguntas'] = array();
		$GLOBALS['convoca_examen_llamadas']  = array();
		$_POST                               = array();
		$_GET                                = array();
		$this->editor                        = new Admin_Examen();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		foreach ( array(
			'convoca_test_meta',
			'convoca_test_post_types',
			'convoca_test_caps',
			'convoca_courses_nonce_ok',
			'convoca_test_cajas',
			'convoca_courses_acciones',
			'convoca_test_titulos',
			'convoca_examen_preguntas',
			'convoca_examen_llamadas',
		) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}
		unset( $GLOBALS['convoca_courses_pantalla'] );
		$_POST = array();
		$_GET  = array();
	}

	/**
	 * Un post del tipo pedido.
	 *
	 * @param string $tipo Tipo.
	 * @return \WP_Post
	 */
	private function post( string $tipo ): \WP_Post {
		$post            = new \WP_Post();
		$post->ID        = 90;
		$post->post_type = $tipo;

		return $post;
	}

	/**
	 * Los cinco enganches.
	 */
	public function test_registra(): void {
		$this->editor->register();

		foreach ( array(
			'add_meta_boxes',
			'save_post',
			'admin_post_' . Admin_Examen::ACTION_QUESTION,
			'admin_post_' . Admin_Examen::ACTION_QUESTION_DELETE,
			'admin_notices',
		) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['convoca_courses_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * Dos cajas, ambas en el examen.
	 */
	public function test_las_cajas(): void {
		$this->editor->add_meta_boxes();

		$this->assertSame( CPT_Examen::TYPE, $GLOBALS['convoca_test_cajas']['convoca_courses_exam']['pantalla'] );
		$this->assertSame( CPT_Examen::TYPE, $GLOBALS['convoca_test_cajas']['convoca_courses_exam_questions']['pantalla'] );
	}

	/**
	 * Un tipo que no es examen no se toca.
	 */
	public function test_otro_tipo(): void {
		$_POST = array(
			Admin_Examen::NONCE     => 'bueno',
			'convoca_num_preguntas' => '10',
		);

		$this->editor->save( 90, $this->post( 'post' ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Sin nonce no se guarda.
	 */
	public function test_sin_nonce(): void {
		$_POST = array( 'convoca_num_preguntas' => '10' );

		$this->editor->save( 90, $this->post( CPT_Examen::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Nonce inválido, tampoco.
	 */
	public function test_nonce_invalido(): void {
		$_POST                               = array(
			Admin_Examen::NONCE     => 'malo',
			'convoca_num_preguntas' => '10',
		);
		$GLOBALS['convoca_courses_nonce_ok'] = 0;

		$this->editor->save( 90, $this->post( CPT_Examen::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Sin capacidad, tampoco.
	 */
	public function test_sin_capacidad(): void {
		$_POST = array(
			Admin_Examen::NONCE     => 'bueno',
			'convoca_num_preguntas' => '10',
		);
		$GLOBALS['convoca_test_caps']['convoca_manage_courses'] = false;

		$this->editor->save( 90, $this->post( CPT_Examen::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Configuración completa: el peso se recorta y el módulo se valida por tipo.
	 */
	public function test_guarda_la_configuracion(): void {
		$GLOBALS['convoca_test_post_types'][61] = CPT_Modulo::TYPE;
		$_POST                                  = array(
			Admin_Examen::NONCE         => 'bueno',
			'convoca_curso_id'          => '50',
			'convoca_modulo_id'         => '61',
			'convoca_num_preguntas'     => '12',
			'convoca_aleatorio'         => '1',
			'convoca_intentos'          => '3',
			'convoca_nota_minima'       => '70',
			'convoca_tiempo_maximo'     => '45',
			'convoca_retroalimentacion' => 'inventada',
			'convoca_peso'              => '150',
		);

		$this->editor->save( 90, $this->post( CPT_Examen::TYPE ) );

		$m = $GLOBALS['convoca_test_meta'][90];
		$this->assertSame( 50, (int) $m['_convoca_curso_id'] );
		$this->assertSame( 61, (int) $m['_convoca_modulo_id'], 'el módulo existe' );
		$this->assertSame( 12, (int) $m['_convoca_num_preguntas'] );
		$this->assertSame( 1, (int) $m['_convoca_aleatorio'] );
		$this->assertSame( 3, (int) $m['_convoca_intentos'] );
		$this->assertSame( 70, (int) $m['_convoca_nota_minima'] );
		$this->assertSame( 45, (int) $m['_convoca_tiempo_maximo'] );
		$this->assertSame( 100.0, (float) $m['_convoca_peso'], 'el peso no pasa de 100' );
	}

	/**
	 * Un módulo que no existe se guarda como 0, no como id inventado.
	 */
	public function test_modulo_invalido(): void {
		$_POST = array(
			Admin_Examen::NONCE => 'bueno',
			'convoca_modulo_id' => '999',
		);

		$this->editor->save( 90, $this->post( CPT_Examen::TYPE ) );

		$this->assertSame( 0, (int) $GLOBALS['convoca_test_meta'][90]['_convoca_modulo_id'] );
	}

	/**
	 * Guardar una pregunta sin nonce válido **corta**: no sigue a medias.
	 */
	public function test_pregunta_sin_nonce(): void {
		$_POST                               = array(
			'exam_id'        => '90',
			'_convoca_nonce' => 'malo',
		);
		$GLOBALS['convoca_courses_nonce_ok'] = 0;

		$this->expectException( \RuntimeException::class );
		$this->editor->handle_question_save();
	}

	/**
	 * Sin permiso, corta.
	 */
	public function test_pregunta_sin_permiso(): void {
		$_POST                        = array(
			'exam_id'        => '90',
			'_convoca_nonce' => 'bueno',
		);
		$GLOBALS['convoca_test_caps'] = array(
			'convoca_manage_courses' => false,
			'manage_options'         => false,
		);

		$this->expectException( \RuntimeException::class );
		$this->editor->handle_question_save();
	}




	/**
	 * La vuelta al editor lleva el aviso y el detalle, y **corta** (no sigue tras redirigir).
	 */
	public function test_la_vuelta_al_editor(): void {
		try {
			$this->privado( 'back', array( 90, 'error', 'Faltan opciones' ) );
			$this->fail( 'debía redirigir' );
		} catch ( \Convoca_Courses_Redirect $r ) {
			$this->assertStringContainsString( 'convoca_msg=error', $r->destino );
			$this->assertStringContainsString( 'convoca_detail=Faltan', $r->destino );
			$this->assertStringContainsString( 'post=90', $r->destino );
		}
	}

	/**
	 * Sin detalle, la URL no arrastra el parámetro vacío.
	 */
	public function test_la_vuelta_sin_detalle(): void {
		try {
			$this->privado( 'back', array( 90, 'created' ) );
		} catch ( \Convoca_Courses_Redirect $r ) {
			$this->assertStringNotContainsString( 'convoca_detail', $r->destino );
		}
	}

	/**
	 * Borrar sin nonce corta.
	 */
	public function test_borrar_sin_nonce(): void {
		$_GET                                = array(
			'exam_id'        => '90',
			'_convoca_nonce' => 'malo',
		);
		$GLOBALS['convoca_courses_nonce_ok'] = 0;

		$this->expectException( \RuntimeException::class );
		$this->editor->handle_question_delete();
	}


	/**
	 * El texto del aviso según la clave.
	 */
	public function test_los_avisos(): void {
		$casos = array(
			'created' => 'Pregunta añadida.',
			'updated' => 'Pregunta actualizada.',
			'deleted' => 'Pregunta borrada.',
		);
		foreach ( $casos as $clave => $texto ) {
			$_GET = array( 'convoca_msg' => $clave );
			$this->assertSame( $texto, $this->privado( 'notice_text' ) );
		}

		$_GET = array(
			'convoca_msg'    => 'error',
			'convoca_detail' => 'Motivo concreto',
		);
		$this->assertSame( 'Motivo concreto', $this->privado( 'notice_text' ) );

		$_GET = array( 'convoca_msg' => 'error' );
		$this->assertStringContainsString( 'No se ha podido guardar', $this->privado( 'notice_text' ) );

		$_GET = array();
		$this->assertSame( '', $this->privado( 'notice_text' ) );
	}

	/**
	 * El aviso solo se pinta en la pantalla del examen y si hay algo que decir.
	 */
	public function test_el_aviso_en_pantalla(): void {
		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => CPT_Examen::TYPE );
		$_GET['convoca_msg']                 = 'created';

		ob_start();
		try {
			$this->editor->notices();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Pregunta añadida.', $html );

		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => 'post' );
		ob_start();
		try {
			$this->editor->notices();
		} finally {
			$html = (string) ob_get_clean();
		}
		$this->assertSame( '', $html, 'fuera de su pantalla no se pinta' );
	}

	/**
	 * Los selectores de curso y módulo.
	 */
	public function test_los_selectores(): void {
		$GLOBALS['convoca_test_post_types'] = array(
			50 => CPT_Curso::TYPE,
			61 => CPT_Modulo::TYPE,
		);
		$GLOBALS['convoca_test_titulos']    = array(
			50 => 'Curso',
			61 => 'Módulo',
		);

		$cursos  = $this->privado( 'courses_options' );
		$modulos = $this->privado( 'modules_options' );

		$this->assertArrayHasKey( 50, $cursos );
		$this->assertSame( 'Curso', $cursos[50] );
		$this->assertArrayHasKey( 61, $modulos );
		$this->assertSame( 0, array_key_first( $modulos ), 'la opción «todo el curso» va primera' );
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param array  $args   Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $args = array() ) {
		$reflexion = new \ReflectionMethod( Admin_Examen::class, $metodo );
		return $reflexion->invokeArgs( $this->editor, $args );
	}
}
