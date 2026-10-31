<?php
/**
 * Los handlers del alumnado atienden también sin sesión de WordPress (courses#27).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Handlers;
use PHPUnit\Framework\TestCase;

/**
 * `admin_post_<accion>` solo dispara con sesión; el alumnado por correo no la tiene.
 */
final class HandlersSinSesionTest extends TestCase {

	/**
	 * Acciones que usa el alumnado.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function acciones(): array {
		return array(
			'completar lección' => array( Handlers::ACTION_COMPLETE ),
			'abrir examen'       => array( Handlers::ACTION_EXAM_OPEN ),
			'enviar examen'      => array( Handlers::ACTION_EXAM_SUBMIT ),
			'entregar tarea'     => array( Handlers::ACTION_TASK_SUBMIT ),
		);
	}

	/**
	 * Cada acción está enganchada también en `nopriv`.
	 *
	 * @dataProvider acciones
	 *
	 * @param string $accion Acción.
	 */
	public function test_la_accion_atiende_sin_sesion( string $accion ): void {
		Handlers::register();

		$this->assertNotFalse(
			has_action( 'admin_post_nopriv_' . $accion ),
			'sin admin_post_nopriv_ el POST del alumnado sin cuenta no lo atiende nadie: ' . $accion
		);
	}
}
