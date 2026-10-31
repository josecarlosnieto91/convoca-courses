<?php
/**
 * Un aviso no puede leerse como token (courses#45).
 *
 * El bucle salía de que el aviso y el token compartían nombre: `/acceso-alumnado/?convoca_acceso=caducado`
 * se interpretaba como un token `caducado`, no validaba y volvía a redirigir al mismo aviso, 50 veces.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Identidad_Acceso;
use PHPUnit\Framework\TestCase;

/**
 * El aviso no se confunde con el enlace.
 */
final class AvisoNoEsTokenTest extends TestCase {

	/**
	 * Limpia la entrada.
	 */
	protected function setUp(): void {
		$_GET = array();
	}

	/**
	 * El valor del aviso, sin id, no se procesa como enlace.
	 */
	public function test_aviso_sin_id_no_se_procesa(): void {
		$_GET = array( Identidad_Acceso::PARAM_TOKEN => 'caducado' );

		$this->assertFalse( Identidad_Acceso::intentar_enlace() );
	}

	/**
	 * El valor del aviso, con un id, tampoco: es un aviso, no un enlace.
	 */
	public function test_aviso_con_id_no_se_procesa(): void {
		$_GET = array(
			Identidad_Acceso::PARAM_TOKEN => 'caducado',
			'id'                          => '12',
		);

		$this->assertFalse( Identidad_Acceso::intentar_enlace() );
	}

	/**
	 * Un token sin id no puede validarse: no se redirige a sí mismo.
	 */
	public function test_token_sin_id_no_se_procesa(): void {
		$_GET = array( Identidad_Acceso::PARAM_TOKEN => 'abc123' );

		$this->assertFalse( Identidad_Acceso::intentar_enlace() );
	}

	/**
	 * Los avisos conocidos están declarados.
	 */
	public function test_los_avisos_estan_declarados(): void {
		$this->assertContains( 'caducado', Identidad_Acceso::AVISOS );
		$this->assertContains( 'bloqueada', Identidad_Acceso::AVISOS );
		$this->assertContains( 'sin_matricula', Identidad_Acceso::AVISOS );
	}

	/**
	 * El parámetro del aviso es distinto del token.
	 */
	public function test_el_parametro_del_aviso_es_distinto(): void {
		$this->assertNotSame( Identidad_Acceso::PARAM_TOKEN, Identidad_Acceso::PARAM_AVISO );
	}
}
