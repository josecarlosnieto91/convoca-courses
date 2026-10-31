<?php
/**
 * Enlace caducado: se avisa y hay dónde pedir otro (courses#31).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Identidad;
use Convoca\Courses\Identidad_Acceso;
use PHPUnit\Framework\TestCase;

/**
 * Antes se caía a la portada en silencio y no existía página de acceso.
 */
final class AccesoCaducadoTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$_GET    = array();
		$_COOKIE = array();
	}

	/**
	 * La ventana del enlace es de un día, no de media hora.
	 */
	public function test_la_ventana_es_de_un_dia(): void {
		$this->assertSame( 86400, Identidad::VIDA );
	}

	/**
	 * Un token que no vale manda a la página de acceso con el aviso, no a la portada.
	 */
	public function test_un_token_invalido_avisa(): void {
		$_GET = array(
			Identidad_Acceso::PARAM_TOKEN => 'no-vale',
			'id'                          => '7',
		);

		try {
			Identidad_Acceso::intentar_enlace();
			$this->fail( 'debería redirigir' );
		} catch ( \Convoca_Courses_Redirect $redireccion ) {
			$this->assertStringContainsString( 'convoca_acceso=caducado', $redireccion->destino );
		}
	}

	/**
	 * La página de acceso pinta el aviso de caducidad.
	 */
	public function test_la_pagina_muestra_el_aviso(): void {
		$_GET = array( 'convoca_acceso' => 'caducado' );

		$html = Identidad_Acceso::render();

		$this->assertStringContainsString( 'convoca-alert--warning', $html );
		$this->assertStringContainsString( 'caducado', $html );
	}

	/**
	 * La página ofrece las dos vías: enlace nuevo por correo y código.
	 */
	public function test_la_pagina_ofrece_pedir_otro_enlace(): void {
		$html = Identidad_Acceso::render();

		$this->assertStringContainsString( 'Enviarme el acceso', $html );
		$this->assertStringContainsString( 'Entrar con el código', $html );
	}
}
