<?php
/**
 * Los formularios de courses: a dónde apuntan y con qué nonce.
 *
 * `Handlers` estaba al 0 % (336 statements). Los `handle_*` acaban en redirección o `exit` y no se
 * pueden ejercitar en una prueba unitaria, pero lo que los alimenta sí: cada formulario tiene que
 * apuntar a `admin-post.php` y llevar un nonce **distinto por acción y por entrada**. Un nonce
 * compartido entre dos acciones es un agujero: vale el de una para la otra.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Handlers;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Handlers.php';

/**
 * Acciones y nonces de los formularios del módulo.
 */
final class HandlersFormulariosTest extends TestCase {

	/**
	 * Los nonces de esta prueba son el nombre de la acción a la que se pidieron.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_cc_nonces'] = array();
	}

	/**
	 * Y no se arrastran.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_cc_nonces'] );
	}

	/**
	 * Todos los formularios apuntan al mismo sitio: `admin-post.php`.
	 *
	 * @return void
	 */
	public function test_los_formularios_apuntan_a_admin_post(): void {
		foreach ( array( 'enroll_form_action', 'complete_form_action', 'exam_form_action', 'task_form_action' ) as $metodo ) {
			$this->assertStringContainsString( 'admin-post.php', Handlers::$metodo(), "{$metodo} tiene que apuntar a admin-post" );
		}
	}

	/**
	 * El nonce de matrícula no depende de ninguna entrada.
	 *
	 * @return void
	 */
	public function test_el_nonce_de_matricula_es_el_suyo(): void {
		$nonce = Handlers::enroll_nonce();

		$this->assertNotSame( '', $nonce );
		$this->assertCount( 1, $GLOBALS['_cc_nonces'], 'la matrícula pide un nonce y solo uno' );
	}

	/**
	 * El de completar lección lleva el id dentro: no vale el de otra lección.
	 *
	 * @return void
	 */
	public function test_el_nonce_de_completar_lleva_el_id_de_la_leccion(): void {
		Handlers::complete_nonce( 7 );
		Handlers::complete_nonce( 8 );

		$pedidos = array_keys( $GLOBALS['_cc_nonces'] );

		$this->assertCount( 2, $pedidos, 'dos lecciones, dos nonces' );
		$this->assertStringContainsString( '7', $pedidos[0] );
		$this->assertStringContainsString( '8', $pedidos[1] );
		$this->assertNotSame( $pedidos[0], $pedidos[1], 'un nonce no puede servir para dos lecciones' );
	}

	/**
	 * Examen abierto y examen entregado piden nonces distintos.
	 *
	 * @return void
	 */
	public function test_abrir_y_entregar_un_examen_no_comparten_nonce(): void {
		Handlers::exam_open_nonce( 3 );
		Handlers::exam_submit_nonce( 9 );

		$pedidos = array_keys( $GLOBALS['_cc_nonces'] );

		$this->assertCount( 2, $pedidos );
		$this->assertNotSame( $pedidos[0], $pedidos[1] );
	}

	/**
	 * Entregar tarea y calificarla tampoco comparten nonce.
	 *
	 * @return void
	 */
	public function test_entregar_y_calificar_no_comparten_nonce(): void {
		Handlers::task_nonce( 4 );
		Handlers::task_grade_nonce( 5 );

		$pedidos = array_keys( $GLOBALS['_cc_nonces'] );

		$this->assertCount( 2, $pedidos );
		$this->assertNotSame( $pedidos[0], $pedidos[1], 'calificar no puede usar el nonce de entregar' );
	}

	/**
	 * La descarga del acta lleva la acción, el curso y su nonce.
	 *
	 * @return void
	 */
	public function test_la_url_del_acta_lleva_curso_y_nonce(): void {
		$url = Handlers::gradebook_csv_url( 42 );

		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringContainsString( 'course_id=42', $url, 'el acta es de un curso concreto' );
		$this->assertStringContainsString( '_convoca_nonce=', $url, 'sin nonce, la descarga no puede ir' );
	}

	/**
	 * El acta de cada curso pide su propio nonce.
	 *
	 * @return void
	 */
	public function test_el_acta_de_cada_curso_pide_su_nonce(): void {
		Handlers::gradebook_csv_url( 1 );
		Handlers::gradebook_csv_url( 2 );

		$pedidos = array_keys( $GLOBALS['_cc_nonces'] );

		$this->assertCount( 2, $pedidos );
		$this->assertNotSame( $pedidos[0], $pedidos[1] );
	}
}
