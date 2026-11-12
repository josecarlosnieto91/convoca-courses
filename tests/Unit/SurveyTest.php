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
	 * La configuración del curso se normaliza.
	 */
	public function test_config_del_curso(): void {
		update_post_meta(
			5,
			Survey::META,
			array(
				'enabled'    => true,
				'titulo'     => 'Calidad',
				'preguntas'  => array( 'Uno', 'Dos' ),
				'comentario' => false,
			)
		);
		$config = Survey::config( 5 );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'Calidad', $config['titulo'] );
		$this->assertSame( array( 'Uno', 'Dos' ), $config['preguntas'] );
		$this->assertFalse( $config['comentario'] );
	}

	/**
	 * Sin matrícula no se pinta la encuesta.
	 */
	public function test_render_sin_matricula(): void {
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => true );
		update_post_meta(
			5,
			Survey::META,
			array(
				'enabled'   => true,
				'titulo'    => 'Calidad',
				'preguntas' => array( 'Uno' ),
			)
		);

		$this->assertSame( '', Survey::render( 5 ) );
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
