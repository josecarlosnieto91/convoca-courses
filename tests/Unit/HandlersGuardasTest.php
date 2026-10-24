<?php
/**
 * Guardas de los manejadores del curso: ninguna acción sigue sin nonce válido.
 *
 * Todos los `handle_*` comparten la misma primera puerta: verifican el nonce y, si no vale, vuelven con
 * `convoca_msg=nonce` **sin tocar nada**. Aquí se prueba esa puerta en los diez, más las URL y los nonces de los
 * formularios y el registro de enganches.
 *
 * Los caminos con nonce válido pasan por matrícula, pagos, correo y ficheros: se quedan para integración.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Handlers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Handlers.php';

/**
 * Guardas de los manejadores.
 */
final class HandlersGuardasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_nonce_ok'] = 0;
		$GLOBALS['convoca_test_caps']        = array( 'convoca_manage_courses' => false );
		$_POST                               = array();
		$_GET                                = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['convoca_courses_nonce_ok'], $GLOBALS['convoca_test_caps'] );
		$_POST = array();
		$_GET  = array();
	}

	/**
	 * Los trece enganches, con los públicos del formulario de matrícula.
	 */
	public function test_registra_los_enganches(): void {
		$GLOBALS['convoca_courses_acciones'] = array();

		Handlers::register();

		foreach ( array(
			'admin_post_' . Handlers::ACTION_ENROLL,
			'admin_post_nopriv_' . Handlers::ACTION_ENROLL,
			'admin_post_' . Handlers::ACTION_CANCEL,
			'admin_post_' . Handlers::ACTION_COMPLETE,
			'admin_post_' . Handlers::ACTION_EXAM_OPEN,
			'admin_post_' . Handlers::ACTION_EXAM_SUBMIT,
			'admin_post_' . Handlers::ACTION_TASK_SUBMIT,
			'admin_post_' . Handlers::ACTION_TASK_GRADE,
			'admin_post_' . Handlers::ACTION_GRADEBOOK_CSV,
			'admin_post_' . Handlers::ACTION_GROUP_EMAIL,
			'admin_post_' . Handlers::ACTION_BANK_EXPORT,
			'admin_post_' . Handlers::ACTION_BANK_IMPORT,
			'wp_ajax_' . Handlers::ACTION_VIDEO,
		) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['convoca_courses_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * Las URL de los formularios apuntan a `admin-post.php` y los nonces no están vacíos.
	 */
	public function test_formularios(): void {
		$this->assertStringContainsString( 'admin-post.php', Handlers::enroll_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::complete_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::exam_form_action() );
		$this->assertStringContainsString( 'admin-post.php', Handlers::task_form_action() );

		$this->assertNotSame( '', Handlers::enroll_nonce() );
		$this->assertNotSame( '', Handlers::complete_nonce( 12 ) );
		$this->assertNotSame( '', Handlers::exam_open_nonce( 30 ) );
		$this->assertNotSame( '', Handlers::exam_submit_nonce( 40 ) );
		$this->assertNotSame( '', Handlers::task_nonce( 50 ) );
		$this->assertNotSame( '', Handlers::task_grade_nonce( 60 ) );
	}

	/**
	 * Los manejadores del curso vuelven con «nonce» sin ejecutar la acción.
	 *
	 * @param string $metodo Manejador.
	 * @param array  $campos Campos que lee antes de la puerta.
	 */
	#[DataProvider( 'manejadores' )]
	public function test_sin_nonce_vuelve_con_aviso( string $metodo, array $campos ): void {
		$_POST = $campos;
		$_GET  = $campos;

		try {
			Handlers::$metodo();
			$this->fail( $metodo . ' debía cortar con «nonce»' );
		} catch ( \Convoca_Courses_Redirect $r ) {
			$this->assertStringContainsString( 'convoca_msg=nonce', $r->destino, $metodo );
		}
	}

	/**
	 * Las acciones de administración no redirigen: **cortan** la petición con `wp_die`.
	 *
	 * @param string $metodo Manejador.
	 * @param array  $campos Campos que lee antes de la puerta.
	 */
	#[DataProvider( 'manejadores_administrativos' )]
	public function test_sin_nonce_muere( string $metodo, array $campos ): void {
		$_POST = $campos;
		$_GET  = $campos;

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/caducado|nonce/i' );

		Handlers::$metodo();
	}

	/**
	 * Casos que vuelven con aviso.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}>
	 */
	public static function manejadores(): array {
		$nonce = array( '_convoca_nonce' => 'inventado' );

		return array(
			'matrícula'          => array( 'handle_enroll', array( 'course_id' => 7 ) + $nonce ),
			'baja'                => array(
				'handle_cancel',
				array(
					'enrollment_id' => 9,
					'course_id'     => 7,
				) + $nonce,
			),
			'lección completada' => array( 'handle_complete', array( 'lesson_id' => 11 ) + $nonce ),
			'abrir examen'        => array( 'handle_exam_open', array( 'exam_id' => 30 ) + $nonce ),
			'enviar examen'       => array( 'handle_exam_submit', array( 'attempt_id' => 40 ) + $nonce ),
			'enviar tarea'        => array(
				'handle_task_submit',
				array(
					'task_id'   => 50,
					'course_id' => 7,
				) + $nonce,
			),
		);
	}

	/**
	 * Casos administrativos que mueren.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}>
	 */
	public static function manejadores_administrativos(): array {
		$nonce = array( '_convoca_nonce' => 'inventado' );

		return array(
			'calificar tarea' => array( 'handle_task_grade', array( 'submission_id' => 60 ) + $nonce ),
			'notas en CSV'    => array( 'handle_gradebook_csv', array( 'course_id' => 7 ) + $nonce ),
			'correo al grupo' => array( 'handle_group_email', array( 'course_id' => 7 ) + $nonce ),
			'exportar banco'  => array( 'handle_bank_export', array( 'course_id' => 7 ) + $nonce ),
			'importar banco'  => array( 'handle_bank_import', array( 'exam_id' => 30 ) + $nonce ),
		);
	}
}
