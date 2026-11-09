<?php
/**
 * El acceso del alumnado lleva honeypot y trampa de tiempo.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Comprueba que el formulario público no queda sin la barrera anti-bot.
 */
final class AccesoHoneypotTest extends TestCase {

	/**
	 * Los dos formularios pintan los campos y `procesar()` evalúa el motivo antes del alta.
	 */
	public function test_el_acceso_publico_lleva_honeypot(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Identidad_Acceso.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.

		$this->assertSame( 2, substr_count( $fuente, 'Anti_Bot::campos()' ), 'los dos formularios llevan los campos trampa' );
		$this->assertStringContainsString( 'Anti_Bot::motivo( $_POST )', $fuente );

		$motivo = strpos( $fuente, 'Anti_Bot::motivo(' );
		$alta   = strpos( $fuente, 'Identidad::alta(' );
		$this->assertNotFalse( $motivo );
		$this->assertNotFalse( $alta );
		$this->assertLessThan( $alta, $motivo, 'se evalúa antes de tocar la identidad' );
	}
}
