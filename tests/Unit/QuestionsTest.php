<?php
/**
 * Pruebas de la validación del banco de preguntas.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Questions;
use PHPUnit\Framework\TestCase;

/**
 * Validación por tipo, opciones y respuesta correcta.
 *
 * @covers \Convoca\Courses\Questions
 */
final class QuestionsTest extends TestCase {

	/**
	 * Pregunta válida de respuesta única.
	 *
	 * @param array $correctas Índices correctos.
	 * @return array<string, mixed>
	 */
	private function base( array $correctas = array( '1' ) ): array {
		return array(
			'tipo'      => 'unica',
			'enunciado' => '¿Dónde está Oviedo?',
			'opciones'  => array( 'Sevilla', 'Asturias', 'Murcia' ),
			'correctas' => $correctas,
			'puntos'    => 1.5,
		);
	}

	/**
	 * Una pregunta correcta pasa la validación y se normaliza.
	 *
	 * @return void
	 */
	public function test_pregunta_valida(): void {
		$check = Questions::validate( $this->base() );

		$this->assertTrue( $check['ok'] );
		$this->assertSame( 'unica', $check['pregunta']['tipo'] );
		$this->assertSame( array( '1' ), $check['pregunta']['correctas'] );
		$this->assertSame( 1.5, $check['pregunta']['puntos'] );
	}

	/**
	 * Sin enunciado no hay pregunta.
	 *
	 * @return void
	 */
	public function test_sin_enunciado_no_vale(): void {
		$data              = $this->base();
		$data['enunciado'] = '   ';

		$check = Questions::validate( $data );

		$this->assertFalse( $check['ok'] );
		$this->assertContains( 'La pregunta necesita un enunciado.', $check['errores'] );
	}

	/**
	 * Menos de dos opciones no es una pregunta.
	 *
	 * @return void
	 */
	public function test_hacen_falta_dos_opciones(): void {
		$data             = $this->base();
		$data['opciones'] = array( 'Solo esta' );

		$check = Questions::validate( $data );

		$this->assertFalse( $check['ok'] );
	}

	/**
	 * Las opciones repetidas no cuentan dos veces.
	 *
	 * @return void
	 */
	public function test_opciones_repetidas_se_colapsan(): void {
		$data             = $this->base();
		$data['opciones'] = array( 'Asturias', 'Asturias', 'Asturias' );

		$check = Questions::validate( $data );

		$this->assertFalse( $check['ok'] );
		$this->assertCount( 1, $check['pregunta']['opciones'] );
	}

	/**
	 * Una única con dos correctas es un error de configuración.
	 *
	 * @return void
	 */
	public function test_unica_con_dos_correctas_no_vale(): void {
		$check = Questions::validate( $this->base( array( '0', '1' ) ) );

		$this->assertFalse( $check['ok'] );
		$this->assertContains( 'Una pregunta de respuesta única solo puede tener una correcta.', $check['errores'] );
	}

	/**
	 * Múltiple necesita al menos dos correctas.
	 *
	 * @return void
	 */
	public function test_multiple_con_una_correcta_no_vale(): void {
		$data         = $this->base( array( '1' ) );
		$data['tipo'] = 'multiple';

		$check = Questions::validate( $data );

		$this->assertFalse( $check['ok'] );
	}

	/**
	 * Múltiple con dos correctas válidas.
	 *
	 * @return void
	 */
	public function test_multiple_valida(): void {
		$data         = $this->base( array( '0', '2' ) );
		$data['tipo'] = 'multiple';

		$check = Questions::validate( $data );

		$this->assertTrue( $check['ok'] );
		$this->assertSame( array( '0', '2' ), $check['pregunta']['correctas'] );
	}

	/**
	 * Índice correcto fuera de rango: no se guarda como si existiera.
	 *
	 * @return void
	 */
	public function test_correcta_fuera_de_rango_no_vale(): void {
		$check = Questions::validate( $this->base( array( '9' ) ) );

		$this->assertFalse( $check['ok'] );
		$this->assertContains( 'Hay que marcar cuál es la respuesta correcta.', $check['errores'] );
	}

	/**
	 * Verdadero/falso fija sus opciones aunque lleguen otras.
	 *
	 * @return void
	 */
	public function test_verdadero_falso_fija_opciones(): void {
		$data             = $this->base( array( '0' ) );
		$data['tipo']     = 'verdadero_falso';
		$data['opciones'] = array( 'Sí', 'No', 'Quizá' );

		$check = Questions::validate( $data );

		$this->assertTrue( $check['ok'] );
		$this->assertSame( Questions::OPCIONES_VF, $check['pregunta']['opciones'] );
	}

	/**
	 * Un tipo inventado cae a única y se avisa.
	 *
	 * @return void
	 */
	public function test_tipo_desconocido_avisa(): void {
		$data         = $this->base();
		$data['tipo'] = 'rellenar_huecos';

		$check = Questions::validate( $data );

		$this->assertFalse( $check['ok'] );
		$this->assertSame( 'unica', $check['pregunta']['tipo'] );
	}

	/**
	 * Puntos a cero se elevan a uno: una pregunta que no puntúa no tiene sentido.
	 *
	 * @return void
	 */
	public function test_puntos_cero_suben_a_uno(): void {
		$data           = $this->base();
		$data['puntos'] = 0;

		$check = Questions::validate( $data );

		$this->assertSame( 1.0, $check['pregunta']['puntos'] );
	}

	/**
	 * Sin marcar ninguna correcta no se puede guardar.
	 *
	 * @return void
	 */
	public function test_sin_correcta_no_vale(): void {
		$check = Questions::validate( $this->base( array() ) );

		$this->assertFalse( $check['ok'] );
	}
}
