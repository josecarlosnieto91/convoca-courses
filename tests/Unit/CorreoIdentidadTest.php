<?php
/**
 * Correo del alumnado sin cuenta: sale por el Mailer del núcleo, no por wp_mail.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Notifications;
use PHPUnit\Framework\TestCase;

/**
 * Avisos a un correo suelto.
 */
final class CorreoIdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones']  = array();
		$GLOBALS['convoca_courses_mailer_ok'] = true;
		\Convoca\Core\Mailer::$enviados       = array();
	}

	/**
	 * Un correo válido sale por el Mailer del núcleo.
	 */
	public function test_sale_por_el_mailer(): void {
		$ok = Notifications::send_to_email( 'ana@ejemplo.test', 'Asunto', 'Cuerpo', 'plantilla_x' );

		$this->assertTrue( $ok );
		$this->assertCount( 1, \Convoca\Core\Mailer::$enviados );

		$enviado = \Convoca\Core\Mailer::$enviados[0];

		$this->assertSame( 'ana@ejemplo.test', $enviado[0] );
		$this->assertSame( 'Asunto', $enviado[1] );
		$this->assertSame( 'Cuerpo', $enviado[2] );
		$this->assertSame( 'plantilla_x', $enviado[3]['template'] );
	}

	/**
	 * Un correo inválido no se envía y queda apuntado.
	 */
	public function test_correo_invalido_queda_pendiente(): void {
		$ok = Notifications::send_to_email( 'no-es-correo', 'Asunto', 'Cuerpo', 'plantilla_x' );

		$this->assertFalse( $ok );
		$this->assertCount( 0, \Convoca\Core\Mailer::$enviados );

		$pendientes = (array) get_option( Notifications::PENDING_OPTION, array() );
		$this->assertCount( 1, $pendientes );
		$this->assertSame( 'sin correo', $pendientes[0]['motivo'] );
	}

	/**
	 * Si el envío falla, no se pierde: queda apuntado con su motivo.
	 */
	public function test_envio_fallido_queda_pendiente(): void {
		$GLOBALS['convoca_courses_mailer_ok'] = false;

		$ok = Notifications::send_to_email( 'ana@ejemplo.test', 'Asunto', 'Cuerpo', 'plantilla_x' );

		$this->assertFalse( $ok );

		$pendientes = (array) get_option( Notifications::PENDING_OPTION, array() );
		$this->assertCount( 1, $pendientes );
		$this->assertSame( 'envio fallido', $pendientes[0]['motivo'] );
		$this->assertSame( 'ana@ejemplo.test', $pendientes[0]['email'] );
	}

	/**
	 * El pendiente guarda el correo, no un user_id.
	 */
	public function test_el_pendiente_guarda_correo(): void {
		Notifications::record_pending_email( 'bea@ejemplo.test', 'A', 'B', 'plantilla', array(), 'sin Mailer del nucleo' );

		$pendientes = (array) get_option( Notifications::PENDING_OPTION, array() );

		$this->assertSame( 'bea@ejemplo.test', $pendientes[0]['email'] );
		$this->assertArrayNotHasKey( 'user_id', $pendientes[0] );
	}

	/**
	 * La confirmación del 98.7 va por el Mailer, no por wp_mail.
	 */
	public function test_la_confirmacion_usa_el_mailer(): void {
		$GLOBALS['convoca_test_meta'][700]['_convoca_precio'] = '5';

		\Convoca\Courses\Condiciones::confirmar( 700, 700, 'carla@ejemplo.test', 'Carla', false, 0 );

		$this->assertCount( 1, \Convoca\Core\Mailer::$enviados );
		$this->assertSame( 'confirmacion_98_7', \Convoca\Core\Mailer::$enviados[0][3]['template'] );
	}
}
