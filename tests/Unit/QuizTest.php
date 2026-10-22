<?php
/**
 * Pruebas de la corrección de exámenes.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Quiz;
use PHPUnit\Framework\TestCase;

/**
 * Corrección: los tres tipos, en blanco, examen vacío y aprobado.
 *
 * @covers \Convoca\Courses\Quiz
 */
final class QuizTest extends TestCase {

	/**
	 * Pregunta de respuesta única.
	 *
	 * @param int   $id    Identificador.
	 * @param array $ok    Índices correctos.
	 * @param float $puntos Puntos.
	 * @return array<string, mixed>
	 */
	private function single( int $id, array $ok, float $puntos = 1.0 ): array {
		return array(
			'id'        => $id,
			'tipo'      => 'unica',
			'opciones'  => array( 'Madrid', 'Oviedo', 'Sevilla' ),
			'correctas' => $ok,
			'puntos'    => $puntos,
		);
	}

	/**
	 * Única acertada: puntos completos y nota 100.
	 *
	 * @return void
	 */
	public function test_unica_acertada_da_todo_el_punto(): void {
		$resultado = Quiz::grade( array( $this->single( 1, array( '1' ) ) ), array( 1 => '1' ), 50.0 );

		$this->assertSame( 100.0, $resultado['puntuacion'] );
		$this->assertTrue( $resultado['aprobado'] );
		$this->assertTrue( $resultado['detalle'][0]['correcta'] );
	}

	/**
	 * Única fallada: cero.
	 *
	 * @return void
	 */
	public function test_unica_fallada_no_puntua(): void {
		$resultado = Quiz::grade( array( $this->single( 1, array( '1' ) ) ), array( 1 => '0' ), 50.0 );

		$this->assertSame( 0.0, $resultado['puntuacion'] );
		$this->assertFalse( $resultado['aprobado'] );
	}

	/**
	 * Múltiple exacta: acertada.
	 *
	 * @return void
	 */
	public function test_multiple_exacta_acierta(): void {
		$pregunta = array(
			'id'        => 3,
			'tipo'      => 'multiple',
			'opciones'  => array( 'a', 'b', 'c' ),
			'correctas' => array( '0', '2' ),
			'puntos'    => 2.0,
		);

		$resultado = Quiz::grade( array( $pregunta ), array( 3 => array( '2', '0' ) ), 50.0 );

		$this->assertTrue( $resultado['detalle'][0]['correcta'] );
		$this->assertSame( 100.0, $resultado['puntuacion'] );
	}

	/**
	 * Múltiple con una de más: cero, que es lo que evita aprobar marcándolo todo.
	 *
	 * @return void
	 */
	public function test_multiple_con_una_de_mas_anula(): void {
		$pregunta = array(
			'id'        => 3,
			'tipo'      => 'multiple',
			'opciones'  => array( 'a', 'b', 'c' ),
			'correctas' => array( '0', '2' ),
			'puntos'    => 2.0,
		);

		$resultado = Quiz::grade( array( $pregunta ), array( 3 => array( '0', '1', '2' ) ), 50.0 );

		$this->assertFalse( $resultado['detalle'][0]['correcta'] );
		$this->assertSame( 0.0, $resultado['puntuacion'] );
	}

	/**
	 * Verdadero/falso.
	 *
	 * @return void
	 */
	public function test_verdadero_falso(): void {
		$pregunta = array(
			'id'        => 5,
			'tipo'      => 'verdadero_falso',
			'opciones'  => array( 'Verdadero', 'Falso' ),
			'correctas' => array( '0' ),
			'puntos'    => 1.0,
		);

		$this->assertTrue( Quiz::grade( array( $pregunta ), array( 5 => '0' ), 50.0 )['detalle'][0]['correcta'] );
		$this->assertFalse( Quiz::grade( array( $pregunta ), array( 5 => '1' ), 50.0 )['detalle'][0]['correcta'] );
	}

	/**
	 * Pregunta en blanco: cero puntos, sin reventar.
	 *
	 * @return void
	 */
	public function test_en_blanco_no_puntua(): void {
		$resultado = Quiz::grade( array( $this->single( 7, array( '1' ) ) ), array(), 50.0 );

		$this->assertSame( 0.0, $resultado['puntuacion'] );
		$this->assertSame( array(), $resultado['detalle'][0]['respuesta'] );
	}

	/**
	 * Examen sin preguntas: cero y suspenso, nunca división por cero.
	 *
	 * @return void
	 */
	public function test_examen_vacio_suspende(): void {
		$resultado = Quiz::grade( array(), array(), 50.0 );

		$this->assertSame( 0.0, $resultado['puntuacion'] );
		$this->assertSame( 0.0, $resultado['total'] );
		$this->assertFalse( $resultado['aprobado'] );
		$this->assertSame( array(), $resultado['detalle'] );
	}

	/**
	 * Nota con mezcla de aciertos: la media es sobre puntos, no sobre número de preguntas.
	 *
	 * @return void
	 */
	public function test_nota_pondera_por_puntos(): void {
		$preguntas = array(
			$this->single( 1, array( '1' ), 3.0 ),
			$this->single( 2, array( '0' ), 1.0 ),
		);

		$resultado = Quiz::grade(
			$preguntas,
			array(
				1 => '1',
				2 => '1',
			),
			50.0
		);

		$this->assertSame( 75.0, $resultado['puntuacion'] );
		$this->assertSame( 3.0, $resultado['puntos'] );
		$this->assertSame( 4.0, $resultado['total'] );
	}

	/**
	 * El aprobado depende del mínimo recibido, no de un 50 fijo.
	 *
	 * @return void
	 */
	public function test_minimo_configurable(): void {
		$preguntas  = array(
			$this->single( 1, array( '1' ), 2.0 ),
			$this->single( 2, array( '0' ), 1.0 ),
			$this->single( 3, array( '2' ), 1.0 ),
			$this->single( 4, array( '0' ), 1.0 ),
		);
		$respuestas = array(
			1 => '1',
			2 => '0',
			3 => '1',
			4 => '',
		);

		$con_cincuenta = Quiz::grade( $preguntas, $respuestas, 50.0 );
		$con_setenta   = Quiz::grade( $preguntas, $respuestas, 70.0 );

		$this->assertSame( 60.0, $con_cincuenta['puntuacion'] );
		$this->assertTrue( $con_cincuenta['aprobado'] );
		$this->assertFalse( $con_setenta['aprobado'] );
	}

	/**
	 * La selección recorta, no repite y baraja de forma inyectable.
	 *
	 * @return void
	 */
	public function test_pick_selecciona_sin_repetir(): void {
		$ids = Quiz::pick( array( 4, 4, 8, 15 ), 2, false );

		$this->assertSame( array( 4, 8 ), $ids );
	}

	/**
	 * Barajado determinista: se ve el orden que devuelve la función inyectada.
	 *
	 * @return void
	 */
	public function test_pick_usa_el_barajado_inyectado(): void {
		$barajar = static function ( array $ids ): array {
			return array_reverse( $ids );
		};

		$this->assertSame( array( 15, 8 ), Quiz::pick( array( 4, 8, 15 ), 2, true, $barajar ) );
	}

	/**
	 * Sin límite de preguntas se devuelven todas.
	 *
	 * @return void
	 */
	public function test_pick_sin_limite_devuelve_todas(): void {
		$this->assertSame( array( 1, 2, 3 ), Quiz::pick( array( 1, 2, 3 ), 0, false ) );
	}
}
