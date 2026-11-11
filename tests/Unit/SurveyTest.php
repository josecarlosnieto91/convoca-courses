<?php
/**
 * Encuesta de calidad: preguntas y agregación.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Survey;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras de la encuesta.
 */
final class SurveyTest extends TestCase {

	/**
	 * Normaliza líneas vacías y repetidas.
	 */
	public function test_normaliza_preguntas(): void {
		$preguntas = Survey::normalizar_preguntas( "Pregunta uno\n\nPregunta dos\r\nPregunta uno\n" );

		$this->assertSame( array( 'Pregunta uno', 'Pregunta dos' ), $preguntas );
	}

	/**
	 * Si no hay texto, vuelven las preguntas por defecto.
	 */
	public function test_sin_texto_usa_las_preguntas_por_defecto(): void {
		$this->assertSame( Survey::DEFAULT_PREGUNTAS, Survey::normalizar_preguntas( "  \n " ) );
	}

	/**
	 * Agrega medias por pregunta y comentarios.
	 */
	public function test_agrega_medias_y_comentarios(): void {
		$resultado = Survey::agregar(
			array(
				array(
					'answers'    => array( 5, 4, 3 ),
					'comentario' => 'Muy bien',
				),
				array(
					'answers'    => array( 3, 2, 1 ),
					'comentario' => '',
				),
				array(
					'answers'    => '{"answers":[]}',
					'comentario' => 'Solo comentario',
				),
			)
		);

		$this->assertSame( 3, $resultado['total'] );
		$this->assertSame( 4.0, $resultado['medias'][0] );
		$this->assertSame( 3.0, $resultado['medias'][1] );
		$this->assertSame( 2.0, $resultado['medias'][2] );
		$this->assertSame( array( 'Muy bien', 'Solo comentario' ), $resultado['comentarios'] );
	}

	/**
	 * Valores fuera de 1-5 no contaminan la media.
	 */
	public function test_ignora_valores_fuera_de_rango(): void {
		$resultado = Survey::agregar(
			array(
				array(
					'answers'    => array( 9, 4 ),
					'comentario' => '',
				),
			)
		);

		$this->assertArrayNotHasKey( 0, $resultado['medias'] );
		$this->assertSame( 4.0, $resultado['medias'][1] );
	}
}
