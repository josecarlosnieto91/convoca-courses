<?php
/**
 * Aviso de cierre del curso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Entidad;
use Convoca\Courses\Notifications;
use PHPUnit\Framework\TestCase;

/**
 * El destinatario lo decide cada organización; si no, el administrador.
 */
final class AvisoCierreTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();
	}

	/**
	 * Con correo configurado, ese manda.
	 */
	public function test_el_correo_configurado_manda(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'email_avisos' => 'avisos@asociacion.example',
		);

		$this->assertSame( 'avisos@asociacion.example', Notifications::destinatario_avisos() );
	}

	/**
	 * Sin correo configurado, al administrador de WordPress.
	 */
	public function test_sin_configurar_va_al_administrador(): void {
		$GLOBALS['convoca_courses_opciones']['admin_email'] = 'admin@wordpress.example';

		$this->assertSame( 'admin@wordpress.example', Notifications::destinatario_avisos() );
	}

	/**
	 * Un correo inválido no se usa como destinatario.
	 */
	public function test_un_correo_invalido_no_vale(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array( 'email_avisos' => 'no-es-un-correo' );
		$GLOBALS['convoca_courses_opciones']['admin_email']     = 'admin@wordpress.example';

		$this->assertSame( 'admin@wordpress.example', Notifications::destinatario_avisos() );
	}

	/**
	 * El aviso se envía una vez y no se repite.
	 */
	public function test_el_aviso_se_envia_una_vez(): void {
		$GLOBALS['convoca_courses_opciones'][ Entidad::OPTION ] = array(
			'email_avisos' => 'avisos@asociacion.example',
		);
		$GLOBALS['convoca_courses_opciones']['admin_email']     = 'admin@wordpress.example';

		$GLOBALS['convoca_test_post_types'][77]                                    = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][77][ CPT_Base::META_PREFIX . 'email' ]       = 'alumno@example.com';
		$GLOBALS['convoca_test_meta'][77][ CPT_Base::META_PREFIX . 'nombre' ]      = 'Alumno Prueba';
		$GLOBALS['convoca_test_meta'][77][ CPT_Base::META_PREFIX . 'cert_codigo' ] = 'CV-ABC123';

		$this->assertTrue( Notifications::aviso_cierre( 77 ), 'la primera vez se envía' );
		$this->assertSame( '1', $GLOBALS['convoca_test_meta'][77]['_convoca_aviso_cierre'] );
		$this->assertFalse( Notifications::aviso_cierre( 77 ), 'la segunda no se repite' );
	}
}
