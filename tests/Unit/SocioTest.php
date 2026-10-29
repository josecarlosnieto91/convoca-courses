<?php
/**
 * Vínculo con la ficha de socio por correo.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Socio;
use PHPUnit\Framework\TestCase;

/**
 * Identidad reutilizada.
 */
final class SocioTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_campos']     = array();
	}

	/**
	 * Crea una ficha de socio en el arnés.
	 *
	 * @param int    $id    Id.
	 * @param string $email Correo.
	 * @return void
	 */
	private function socio( int $id, string $email ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]             = 'miembro';
		$GLOBALS['convoca_test_meta'][ $id ]['_convoca_email'] = $email;
	}

	/**
	 * Sin correo no se busca nada.
	 */
	public function test_sin_correo_no_hay_socio(): void {
		$this->assertSame( 0, Socio::member_id_por_email( '' ) );
	}

	/**
	 * Una ficha con ese correo se encuentra.
	 */
	public function test_encuentra_la_ficha_por_correo(): void {
		$this->socio( 500, 'ana@ejemplo.test' );

		$this->assertSame( 500, Socio::member_id_por_email( 'ana@ejemplo.test' ) );
	}

	/**
	 * Otro correo no devuelve esa ficha.
	 */
	public function test_otro_correo_no_devuelve_la_ficha(): void {
		$this->socio( 500, 'ana@ejemplo.test' );

		$this->assertSame( 0, Socio::member_id_por_email( 'otra@ejemplo.test' ) );
	}

	/**
	 * Sin ficha no se anota vínculo.
	 */
	public function test_sin_ficha_no_se_vincula(): void {
		$this->assertSame( 0, Socio::vincular_matricula( 900, 7 ) );
		$this->assertArrayNotHasKey( '_convoca_member_id', $GLOBALS['convoca_test_meta'][900] ?? array() );
	}

	/**
	 * Sin ficha no hay cursos.
	 */
	public function test_sin_ficha_no_hay_cursos(): void {
		$this->assertSame( array(), Socio::cursos_de_socio( 0 ) );
	}

	/**
	 * Un pago sin matrícula no da nombre.
	 */
	public function test_un_pago_sin_matricula_no_da_nombre(): void {
		$this->assertSame( '', Socio::nombre_pagador( '', 4242 ) );
		$this->assertSame( 0, Socio::matricula_de_pago( 0 ) );
	}

	/**
	 * El nombre del filtro manda si ya viene.
	 */
	public function test_el_nombre_del_filtro_manda(): void {
		$this->assertSame( 'Ana', Socio::nombre_pagador( 'Ana', 4242 ) );
	}

	/**
	 * La matrícula de un pago se encuentra por su meta.
	 */
	public function test_encuentra_la_matricula_de_un_pago(): void {
		$GLOBALS['convoca_test_post_types'][900]               = 'convoca_matricula';
		$GLOBALS['convoca_test_meta'][900]['_convoca_pago_id'] = 4242;

		$this->assertSame( 900, Socio::matricula_de_pago( 4242 ) );
	}

	/**
	 * La matrícula se vincula al crearse.
	 */
	public function test_la_matricula_se_vincula_al_crearse(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- código del propio plugin.
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Enrollment.php' );

		$this->assertStringContainsString( 'Socio::vincular_matricula(', $fuente );
	}
}
