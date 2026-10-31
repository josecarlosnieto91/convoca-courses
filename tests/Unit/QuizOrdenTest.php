<?php
/**
 * El orden de opciones no puede ser siempre el natural (courses#42).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Quiz;
use PHPUnit\Framework\TestCase;

/**
 * Si la correcta va siempre primera, el examen se aprueba sin leer.
 */
final class QuizOrdenTest extends TestCase {

	/**
	 * Con cuatro opciones, el orden cambia entre intentos y no empieza siempre por 0.
	 */
	public function test_el_orden_no_es_siempre_el_natural(): void {
		$primeros = array();
		$natural  = 0;

		for ( $intento = 1; $intento <= 20; $intento++ ) {
			$orden      = Quiz::option_order( $intento, 3457, 4 );
			$primeros[] = $orden[0];

			if ( array( 0, 1, 2, 3 ) === $orden ) {
				++$natural;
			}
		}

		$this->assertLessThan( 20, $natural, 'algún intento tiene que barajar de verdad' );
		$this->assertGreaterThan( 1, count( array_unique( $primeros ) ), 'la primera opción no puede ser siempre la misma' );
	}

	/**
	 * Cada pregunta de un mismo intento puede barajar distinto.
	 */
	public function test_cada_pregunta_tiene_su_orden(): void {
		$ordenes = array();

		for ( $pregunta = 1; $pregunta <= 10; $pregunta++ ) {
			$ordenes[] = implode( ',', Quiz::option_order( 16, $pregunta, 4 ) );
		}

		$this->assertGreaterThan( 1, count( array_unique( $ordenes ) ), 'las diez preguntas no pueden compartir orden' );
	}

	/**
	 * El orden es estable para el mismo intento y pregunta: si no, no se podría corregir.
	 */
	public function test_el_orden_es_estable(): void {
		$this->assertSame(
			Quiz::option_order( 16, 7, 4 ),
			Quiz::option_order( 16, 7, 4 )
		);
	}
}
