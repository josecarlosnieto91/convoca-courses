<?php
/**
 * Enlace firmado de un solo uso para el profesorado.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Profesorado;
use PHPUnit\Framework\TestCase;

/**
 * Tokens de docente.
 */
final class ProfesoradoTest extends TestCase {

	/**
	 * Opciones limpias.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
	}

	/**
	 * Un token válido devuelve el curso.
	 */
	public function test_un_token_valido_devuelve_el_curso(): void {
		$token = Profesorado::token( 42, 1000 );

		$this->assertSame( 42, Profesorado::verificar( $token, 1000 ) );
	}

	/**
	 * Pasada la caducidad no vale.
	 */
	public function test_caducado_no_vale(): void {
		$token = Profesorado::token( 42, 1000 );

		$this->assertSame( 0, Profesorado::verificar( $token, 1000 + Profesorado::VIDA + 1 ) );
	}

	/**
	 * Un token manipulado no vale.
	 */
	public function test_manipulado_no_vale(): void {
		$token = Profesorado::token( 42, 1000 );
		$malo  = '43' . substr( $token, 2 );

		$this->assertSame( 0, Profesorado::verificar( $malo, 1000 ) );
	}

	/**
	 * Un token con forma rara no vale.
	 */
	public function test_token_con_forma_rara_no_vale(): void {
		$this->assertSame( 0, Profesorado::verificar( 'basura', 1000 ) );
		$this->assertSame( 0, Profesorado::verificar( '1.2', 1000 ) );
	}

	/**
	 * La URL lleva el token.
	 */
	public function test_la_url_lleva_el_token(): void {
		$url = Profesorado::url( 42 );

		$this->assertStringContainsString( 'convoca_docente=', $url );
	}

	/**
	 * El token es distinto para cursos distintos.
	 */
	public function test_tokens_distintos_por_curso(): void {
		$this->assertNotSame( Profesorado::token( 1, 1000 ), Profesorado::token( 2, 1000 ) );
	}
}
