<?php
/**
 * El acceso del alumnado tiene límite de intentos.
 *
 * Es un formulario público que envía códigos por correo: sin límite se puede tantear el código o
 * usar la web para inundar una dirección. La medida vive en la puerta única de Convoca Core.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Límite de intentos del acceso.
 */
final class AccesoRateLimitTest extends TestCase {

	/**
	 * `procesar()` comprueba el límite antes de dar de alta o enviar.
	 */
	public function test_el_acceso_publico_esta_limitado(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Identidad_Acceso.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.

		$this->assertStringContainsString( 'check_rate_limit(', $fuente );
		$this->assertStringContainsString( "'convoca_courses_acceso_' . \$accion", $fuente );
		$this->assertStringContainsString( 'array( \'response\' => 429 )', $fuente );

		$limite = strpos( $fuente, 'check_rate_limit(' );
		$alta   = strpos( $fuente, 'Identidad::alta(' );
		$this->assertNotFalse( $limite );
		$this->assertNotFalse( $alta );
		$this->assertLessThan( $alta, $limite, 'se limita antes de tocar la identidad' );
	}
}
