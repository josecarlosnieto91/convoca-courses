<?php
/**
 * Las piezas de `Handlers` que se pueden ejercitar sin mandar cabeceras: el alta de las acciones,
 * los nonces y URLs de los formularios, el enlace del acta y el diccionario de avisos.
 *
 * `Handlers` estaba al 6,2 % (21 de 336). Los `handle_*` acaban en redirección y `exit`, así que su
 * cola no es medible; lo que sí —y es donde se rompe el contrato con las plantillas— son los nonces
 * (una acción distinta por formulario, y el de completar ligado a la lección), las URLs y los avisos.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Handlers;
use PHPUnit\Framework\TestCase;

/**
 * Piezas de los formularios del curso que se pueden ejercitar sin mandar cabeceras.
 */
class HandlersFormulariosPiezasTest extends TestCase {

	/**
	 * Caso de prueba.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_acciones'] = array();
		$GLOBALS['convoca_courses_nonce_ok'] = 1;
		$GLOBALS['convoca_courses_referer']  = false;
		$_GET                                = array();
		$_POST                               = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Handlers.php';
	}

	/**
	 * Caso de prueba.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_acciones'] = array();
		$GLOBALS['convoca_courses_nonce_ok'] = 1;
		$GLOBALS['convoca_courses_referer']  = false;
		$_GET                                = array();
		$_POST                               = array();
	}

	// ── las acciones quedan enganchadas ────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_las_trece_acciones_quedan_enganchadas(): void {
		Handlers::register();

		$acciones = $GLOBALS['convoca_courses_acciones'];

		$this->assertGreaterThanOrEqual( 13, count( $acciones ), 'cada formulario necesita su admin_post' );

		foreach ( $acciones as $hook => $datos ) {
			$callback = $datos['callback'];
			$this->assertTrue(
				0 === strpos( $hook, 'admin_post_' ) || 0 === strpos( $hook, 'wp_ajax_' ),
				$hook . ' no es un gancho de formulario'
			);
			$this->assertTrue( method_exists( $callback[0], $callback[1] ), $callback[1] . ' no existe' );
		}
	}

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_la_matricula_se_atiende_tambien_sin_sesion(): void {
		Handlers::register();

		$this->assertArrayHasKey( 'admin_post_nopriv_' . Handlers::ACTION_ENROLL, $GLOBALS['convoca_courses_acciones'], 'quien se matricula puede no tener cuenta todavía' );
	}

	// ── nonces: uno por formulario ────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_cada_formulario_lleva_su_nonce(): void {
		$this->assertSame( wp_create_nonce( Handlers::ACTION_ENROLL ), Handlers::enroll_nonce() );
		$this->assertSame( wp_create_nonce( Handlers::ACTION_COMPLETE . '_' . 12 ), Handlers::complete_nonce( 12 ), 'el nonce de completar va ligado a la lección: sin eso se puede marcar otra' );
		$this->assertNotSame( Handlers::complete_nonce( 12 ), Handlers::complete_nonce( 13 ), 'cada lección tiene el suyo' );
		$this->assertSame( wp_create_nonce( Handlers::ACTION_EXAM_OPEN . '_' . 5 ), Handlers::exam_open_nonce( 5 ) );
		$this->assertSame( wp_create_nonce( Handlers::ACTION_EXAM_SUBMIT . '_' . 7 ), Handlers::exam_submit_nonce( 7 ) );
		$this->assertSame( wp_create_nonce( Handlers::ACTION_TASK_SUBMIT . '_' . 9 ), Handlers::task_nonce( 9 ) );
		$this->assertSame( wp_create_nonce( Handlers::ACTION_TASK_GRADE . '_' . 11 ), Handlers::task_grade_nonce( 11 ) );
	}

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_los_nonces_de_dos_formularios_no_coinciden(): void {
		$this->assertNotSame( Handlers::enroll_nonce(), Handlers::complete_nonce( 1 ), 'un nonce compartido deja reutilizar el de un formulario en otro' );
	}

	// ── URLs de los formularios ───────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_los_formularios_apuntan_a_admin_post(): void {
		$this->assertStringContainsString( 'admin-post.php', Handlers::enroll_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::complete_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::exam_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::task_form_action() );
	}

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_el_enlace_del_acta_lleva_accion_curso_y_nonce(): void {
		$url = Handlers::gradebook_csv_url( 34 );

		$this->assertStringContainsString( 'action=' . Handlers::ACTION_GRADEBOOK_CSV, $url );
		$this->assertStringContainsString( 'course_id=34', $url, 'sin el curso el acta no sabe de qué es' );
		$this->assertStringContainsString( '_convoca_nonce=', $url, 'la descarga también se protege' );
	}

	// ── el acta: sus dos guardas ──────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_el_acta_no_se_descarga_con_el_nonce_caducado(): void {
		$GLOBALS['convoca_courses_nonce_ok'] = false;
		$_GET                                = array(
			'course_id'      => '34',
			'_convoca_nonce' => 'caducado',
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'sesión ha caducado' );

		Handlers::handle_gradebook_csv();
	}

	// ── el diccionario de avisos ──────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_cada_aviso_conocido_tiene_su_texto(): void {
		$claves = array(
			'enrolled',
			'cancelled',
			'completed',
			'exam_sent',
			'exam_expired',
			'exam_locked',
			'exam_no_attempts',
			'exam_empty',
			'exam_error',
			'task_sent',
			'task_graded',
			'task_file',
			'task_error',
			'group_sent',
			'group_error',
			'bank_imported',
			'bank_error',
			'pending_payment',
			'payment_error',
			'nonce',
			'error',
		);

		foreach ( $claves as $clave ) {
			$this->assertNotSame( '', Handlers::message( $clave ), 'el aviso «' . $clave . '» no tiene texto' );
		}
	}

	/**
	 * Caso de prueba.
	 */
	/**
	 * Caso de prueba.
	 */
	public function test_un_aviso_desconocido_no_inventa_texto(): void {
		$this->assertSame( '', Handlers::message( 'inventado' ), 'mejor nada que un aviso que no significa nada' );
	}

	/**
	 * El aviso de matrícula pendiente de pago dice qué hay que hacer.
	 */
	public function test_el_aviso_de_matricula_pendiente_de_pago_dice_qué_hacer(): void {
		$this->assertStringContainsString( 'pago', Handlers::message( 'pending_payment' ) );
	}
}
