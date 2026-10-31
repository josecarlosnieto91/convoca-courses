<?php
/**
 * Tras matricularse se vuelve a la página del formulario, donde está el aviso (courses#27).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Matricula_Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Antes aterrizaba en el archivo del CPT y el alumnado no veía qué había pasado.
 */
final class MatriculaDestinoTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_referer'] = false;
		$_POST                              = array();
		$_GET                               = array();
	}

	/**
	 * Con referente, se vuelve a él con el aviso.
	 */
	public function test_vuelve_a_la_pagina_del_formulario(): void {
		$GLOBALS['convoca_courses_referer'] = 'https://demo.local/pagina-del-formulario/';

		$_POST = array(
			'_convoca_nonce' => 'nonce',
			'email'          => 'alguien@example.com',
			'nombre'         => 'Alguien',
			'course'         => '3441',
			'consentimiento' => '1',
			'mayor_14'       => '1',
		);

		try {
			Matricula_Identidad::procesar();
			$this->fail( 'debería redirigir' );
		} catch ( \Convoca_Courses_Redirect $redireccion ) {
			$this->assertStringStartsWith( 'https://demo.local/pagina-del-formulario/', $redireccion->destino );
			$this->assertStringContainsString( 'convoca_matricula=', $redireccion->destino );
		}
	}

	/**
	 * Sin referente, se cae al curso: nunca a una URL vacía.
	 */
	public function test_sin_referente_cae_al_curso(): void {
		$_POST = array(
			'_convoca_nonce' => 'nonce',
			'email'          => 'otro@example.com',
			'nombre'         => 'Otro',
			'course'         => '3441',
			'consentimiento' => '1',
			'mayor_14'       => '1',
		);

		try {
			Matricula_Identidad::procesar();
			$this->fail( 'debería redirigir' );
		} catch ( \Convoca_Courses_Redirect $redireccion ) {
			$this->assertNotSame( '', $redireccion->destino );
			$this->assertStringContainsString( 'convoca_matricula=', $redireccion->destino );
		}
	}
}
