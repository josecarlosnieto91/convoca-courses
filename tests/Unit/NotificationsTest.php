<?php
/**
 * Avisos del curso: qué se envía, qué queda pendiente y cómo se guarda.
 *
 * `Notifications` estaba al 0 % (116 statements). Lo que importa es que un aviso que no sale
 * **no se pierda**: queda apuntado con su motivo y su cuerpo para poder reenviarlo, y la lista
 * tiene tope.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Logger;
use Convoca\Core\Mailer;
use Convoca\Courses\Notifications;
use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de los avisos del curso.
 */
class NotificationsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones']  = array();
		$GLOBALS['convoca_courses_usuarios']  = array();
		$GLOBALS['convoca_courses_mailer_ok'] = true;
		$GLOBALS['convoca_courses_titulos']   = array();
		Mailer::$enviados                     = array();
		Logger::$escritos                     = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_courses_usuarios'] = array();
		$GLOBALS['convoca_courses_titulos']  = array();
		Mailer::$enviados                    = array();
		Logger::$escritos                    = array();
	}

	/**
	 * Da de alta una persona con correo.
	 *
	 * @param int    $id     Identificador.
	 * @param string $correo Correo.
	 */
	private function persona( int $id, string $correo ): void {
		$GLOBALS['convoca_courses_usuarios'][ $id ] = (object) array(
			'ID'         => $id,
			'user_email' => $correo,
		);
	}

	/**
	 * Un aviso enviado llega al destinatario.
	 */
	public function test_un_aviso_con_correo_y_mailer_sale(): void {
		$this->persona( 5, 'alumna@example.test' );

		$salio = Notifications::send( 5, 'Asunto', 'Cuerpo', 'plantilla' );

		$this->assertTrue( $salio );
		$this->assertCount( 1, Mailer::$enviados );
		$this->assertSame( 'alumna@example.test', Mailer::$enviados[0][0] );
		$this->assertSame( 'convoca-courses', Mailer::$enviados[0][3]['plugin'] );
		$this->assertSame( 'plantilla', Mailer::$enviados[0][3]['template'], 'sin la plantilla el correo no se puede rehacer' );
	}

	/**
	 * Una persona sin correo deja el aviso pendiente, con su motivo.
	 */
	public function test_sin_correo_el_aviso_queda_pendiente(): void {
		$this->persona( 6, '' );

		$this->assertFalse( Notifications::send( 6, 'Asunto', 'Cuerpo', 'plantilla' ) );

		$pendientes = Notifications::pending();

		$this->assertCount( 1, $pendientes );
		$this->assertSame( 'sin correo', $pendientes[0]['motivo'], 'el motivo es lo que permite arreglarlo' );
		$this->assertSame( 'Cuerpo', $pendientes[0]['body'], 'se guarda el cuerpo: si no, el aviso se pierde' );
		$this->assertNotEmpty( Logger::$escritos, 'y queda en el registro' );
	}

	/**
	 * Una persona que no existe deja el aviso pendiente.
	 */
	public function test_una_persona_inexistente_no_recibe_nada(): void {
		$this->assertFalse( Notifications::send( 999, 'Asunto', 'Cuerpo', 'plantilla' ) );
		$this->assertCount( 1, Notifications::pending() );
	}

	/**
	 * Si el envío falla, el aviso queda pendiente con su motivo.
	 */
	public function test_un_envio_fallido_queda_pendiente(): void {
		$this->persona( 7, 'alumno@example.test' );
		$GLOBALS['convoca_courses_mailer_ok'] = false;

		$this->assertFalse( Notifications::send( 7, 'Asunto', 'Cuerpo', 'plantilla' ) );
		$this->assertSame( 'envio fallido', Notifications::pending()[0]['motivo'] );
	}

	/**
	 * El aviso de tarea corregida lleva curso, tarea, nota y comentario.
	 */
	public function test_el_aviso_de_tarea_corregida_lleva_los_datos(): void {
		$this->persona( 8, 'alumna@example.test' );
		$GLOBALS['convoca_courses_titulos'] = array(
			3 => 'Curso de setas',
			4 => 'Tarea 1',
		);

		Notifications::task_graded( 8, 4, 3, 8.5, 'Buen trabajo' );

		$enviado = Mailer::$enviados[0];

		$this->assertStringContainsString( 'Tarea 1', $enviado[1], 'el asunto dice qué tarea' );
		$this->assertStringContainsString( 'Curso de setas', $enviado[2] );
		$this->assertStringContainsString( '8,50', $enviado[2], 'la nota va con formato local' );
		$this->assertStringContainsString( 'Buen trabajo', $enviado[2] );
	}

	/**
	 * Sin comentario, el aviso no deja el rótulo vacío.
	 */
	public function test_un_comentario_vacio_no_deja_rotulo_suelto(): void {
		$this->persona( 9, 'alumna@example.test' );

		Notifications::task_graded( 9, 4, 3, 5.0, '   ' );

		$this->assertStringNotContainsString( 'Comentario:', Mailer::$enviados[0][2] );
	}

	/**
	 * El aviso de certificado lleva su código.
	 */
	public function test_el_aviso_de_certificado_lleva_su_codigo(): void {
		$this->persona( 10, 'alumna@example.test' );
		$GLOBALS['convoca_courses_titulos'] = array( 3 => 'Curso de setas' );

		Notifications::certificate_issued( 10, 3, 'CERT-2026-001', 'https://example.test/verificar' );

		$enviado = Mailer::$enviados[0];

		$this->assertStringContainsString( 'Certificado emitido', $enviado[1] );
		$this->assertStringContainsString( 'CERT-2026-001', $enviado[2] );
		$this->assertStringContainsString( 'verificar', $enviado[2], 'sin el enlace no se puede comprobar' );
	}

	/**
	 * Los recordatorios y el mensaje de grupo salen con su plantilla.
	 */
	public function test_los_recordatorios_y_el_grupo_usan_su_plantilla(): void {
		$this->persona( 11, 'alumna@example.test' );

		Notifications::reminder_inactive( 11, 3 );
		Notifications::reminder_exam( 11, 3 );
		Notifications::group_message( 11, 3, 'Aviso', 'El aula cambia de hora' );

		$plantillas = array_column( array_column( Mailer::$enviados, 3 ), 'template' );

		$this->assertContains( 'recordatorio-inactividad', $plantillas );
		$this->assertContains( 'recordatorio-examen', $plantillas );
		$this->assertContains( 'mensaje-grupo', $plantillas );
	}

	/**
	 * La lista de pendientes tiene tope.
	 */
	public function test_la_lista_de_pendientes_no_crece_sin_tope(): void {
		$llenos = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$llenos[] = array( 'user_id' => $i );
		}
		$GLOBALS['convoca_courses_opciones'][ Notifications::PENDING_OPTION ] = $llenos;

		Notifications::send( 999, 'Asunto', 'Cuerpo', 'plantilla' );

		$pendientes = Notifications::pending();

		$this->assertCount( 200, $pendientes, 'una opción sin tope es una bomba de relojería' );
		$this->assertSame( 999, $pendientes[199]['user_id'], 'se tira lo viejo, no lo nuevo' );
	}

	/**
	 * Vaciar la lista la deja vacía.
	 */
	public function test_vaciar_los_pendientes(): void {
		$this->persona( 12, '' );
		Notifications::send( 12, 'Asunto', 'Cuerpo', 'plantilla' );
		$this->assertCount( 1, Notifications::pending() );

		Notifications::clear_pending();

		$this->assertSame( array(), Notifications::pending() );
	}
}
