<?php
/**
 * Pruebas del libro de notas.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Gradebook;
use PHPUnit\Framework\TestCase;

/**
 * Nota final con pesos, pesos que suman, aprobado y CSV.
 *
 * @covers \Convoca\Courses\Gradebook
 */
final class GradebookTest extends TestCase {

	/**
	 * Nota ponderada con todas las piezas.
	 *
	 * @return void
	 */
	public function test_nota_ponderada(): void {
		$final = Gradebook::final(
			array(
				array(
					'peso' => 40.0,
					'nota' => 70.0,
				),
				array(
					'peso' => 60.0,
					'nota' => 90.0,
				),
			)
		);

		// 0,4*70 + 0,6*90 = 28 + 54 = 82.
		$this->assertSame( 82.0, $final['nota'] );
		$this->assertTrue( $final['completo'] );
		$this->assertSame( 2, $final['piezas'] );
	}

	/**
	 * Una pieza sin corregir no hunde la nota: se reparte entre las que hay.
	 *
	 * @return void
	 */
	public function test_pieza_sin_nota_no_cuenta(): void {
		$final = Gradebook::final(
			array(
				array(
					'peso' => 50.0,
					'nota' => 80.0,
				),
				array(
					'peso' => 50.0,
					'nota' => null,
				),
			)
		);

		$this->assertSame( 80.0, $final['nota'] );
		$this->assertFalse( $final['completo'] );
		$this->assertSame( 1, $final['piezas'] );
	}

	/**
	 * Sin ninguna nota no hay nota final (no es un cero).
	 *
	 * @return void
	 */
	public function test_sin_notas_no_hay_nota(): void {
		$final = Gradebook::final(
			array(
				array(
					'peso' => 100.0,
					'nota' => null,
				),
			)
		);

		$this->assertNull( $final['nota'] );
	}

	/**
	 * Sin piezas tampoco.
	 *
	 * @return void
	 */
	public function test_sin_piezas_no_hay_nota(): void {
		$final = Gradebook::final( array() );

		$this->assertNull( $final['nota'] );
		$this->assertFalse( $final['completo'] );
	}

	/**
	 * La nota se acota a la escala 0-100 aunque llegue fuera.
	 *
	 * @return void
	 */
	public function test_nota_fuera_de_escala(): void {
		$final = Gradebook::final(
			array(
				array(
					'peso' => 100.0,
					'nota' => 250.0,
				),
			)
		);

		$this->assertSame( 100.0, $final['nota'] );
	}

	/**
	 * Pesos que suman 100, con el margen del redondeo.
	 *
	 * @return void
	 */
	public function test_pesos_que_suman_cien(): void {
		$this->assertTrue( Gradebook::weights_ok( array( 33.33, 33.33, 33.34 ) ) );
		$this->assertFalse( Gradebook::weights_ok( array( 50.0, 40.0 ) ) );
	}

	/**
	 * Sin nota no se aprueba.
	 *
	 * @return void
	 */
	public function test_sin_nota_no_se_aprueba(): void {
		$this->assertFalse( Gradebook::passed( null, 50.0 ) );
		$this->assertTrue( Gradebook::passed( 50.0, 50.0 ) );
		$this->assertFalse( Gradebook::passed( 49.99, 50.0 ) );
	}

	/**
	 * CSV con cabecera, punto y coma y comillas escapadas.
	 *
	 * @return void
	 */
	public function test_csv(): void {
		$csv = Gradebook::csv(
			array(
				array(
					'nombre'   => 'Ana "la" Pérez',
					'correo'   => 'a***@ejemplo.test',
					'progreso' => 100.0,
					'nota'     => 82.5,
					'aprobado' => true,
					'completo' => true,
				),
			)
		);

		$lineas = explode( "\r\n", trim( $csv ) );

		$this->assertCount( 2, $lineas );
		$this->assertStringContainsString( '"Alumno";"Correo"', $lineas[0] );
		$this->assertStringContainsString( '"Ana ""la"" Pérez"', $lineas[1] );
		$this->assertStringContainsString( '"82.50"', $lineas[1] );
		$this->assertStringContainsString( '"sí"', $lineas[1] );
	}

	/**
	 * Una nota vacía sale como campo vacío, no como cero.
	 *
	 * @return void
	 */
	public function test_csv_sin_nota(): void {
		$csv = Gradebook::csv(
			array(
				array(
					'nombre'   => 'B',
					'correo'   => 'b***@ejemplo.test',
					'progreso' => 10.0,
					'nota'     => null,
					'aprobado' => false,
					'completo' => false,
				),
			)
		);

		$lineas = explode( "\r\n", trim( $csv ) );

		$this->assertSame( '"B";"b***@ejemplo.test";"10.0";"";"no";"no"', $lineas[1] );
	}
}
