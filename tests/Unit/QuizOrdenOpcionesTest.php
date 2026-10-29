<?php
/**
 * Orden de las opciones del examen: distinto en cada intento y estable dentro del intento.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Quiz;
use PHPUnit\Framework\TestCase;

/**
 * Barajado de opciones.
 */
final class QuizOrdenOpcionesTest extends TestCase {

	/**
	 * El orden es una permutación: ni pierde ni repite opciones.
	 */
	public function test_el_orden_es_una_permutacion(): void {
		$orden = Quiz::option_order( 12, 34, 5 );

		$this->assertCount( 5, $orden );
		$limpio = array_values( array_unique( $orden ) );
		sort( $limpio );
		$this->assertSame( array( 0, 1, 2, 3, 4 ), $limpio );
	}

	/**
	 * Dentro del mismo intento el orden no cambia: recargar no mueve las respuestas.
	 */
	public function test_es_estable_dentro_del_intento(): void {
		$this->assertSame(
			Quiz::option_order( 7, 99, 4 ),
			Quiz::option_order( 7, 99, 4 )
		);
	}

	/**
	 * Dos intentos distintos no comparten orden (con 4 opciones, 24 permutaciones posibles).
	 */
	public function test_cambia_entre_intentos(): void {
		$ordenes = array();
		for ( $intento = 1; $intento <= 6; $intento++ ) {
			$ordenes[] = implode( ',', Quiz::option_order( $intento, 99, 4 ) );
		}

		$this->assertGreaterThan( 1, count( array_unique( $ordenes ) ), 'los intentos no pueden compartir el mismo orden' );
	}

	/**
	 * Con cero o una opción no hay nada que barajar.
	 */
	public function test_casos_limite(): void {
		$this->assertSame( array(), Quiz::option_order( 1, 2, 0 ) );
		$this->assertSame( array( 0 ), Quiz::option_order( 1, 2, 1 ) );
	}

	/**
	 * El valor enviado sigue siendo el índice real: la plantilla no renumera.
	 */
	public function test_la_plantilla_conserva_el_indice_real(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		$plantilla = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/examen.php' );

		$this->assertStringContainsString( 'opciones_orden', $plantilla );
		$this->assertStringContainsString( 'value="<?php echo esc_attr( (string) $convoca_indice ); ?>"', $plantilla );
	}
}
