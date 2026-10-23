<?php
/**
 * Preguntas de examen: la validación, la limpieza de listas y las lecturas.
 *
 * `Questions` estaba al 11,4 % (12 de 105). La validación es lo que impide que un examen se guarde
 * con una pregunta imposible (sin enunciado, sin correcta, o de respuesta única con dos marcadas),
 * así que se prueba caso a caso.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Questions;
use PHPUnit\Framework\TestCase;

/**
 * Reglas y lecturas de las preguntas.
 */
class QuestionsReglasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->borrados   = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Questions.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->borrados   = array();
	}

	/**
	 * Una pregunta correcta pasa la validación y sale normalizada.
	 */
	public function test_una_pregunta_correcta_pasa(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => '  ¿Cuánto son dos más dos?  ',
				'opciones'  => array( 'Cuatro', 'Cinco', 'Cuatro' ),
				'correctas' => array( '0' ),
				'puntos'    => '2.555',
			)
		);

		$this->assertTrue( $r['ok'] );
		$this->assertSame( array(), $r['errores'] );
		$this->assertSame( '¿Cuánto son dos más dos?', $r['pregunta']['enunciado'], 'el enunciado va sin espacios de sobra' );
		$this->assertSame( array( 'Cuatro', 'Cinco' ), $r['pregunta']['opciones'], 'las opciones repetidas no cuentan dos veces' );
		$this->assertSame( array( '0' ), $r['pregunta']['correctas'] );
		$this->assertSame( 2.56, $r['pregunta']['puntos'], 'los puntos van a dos decimales' );
	}

	/**
	 * Un tipo que no existe se rechaza y la pregunta queda como de respuesta única.
	 */
	public function test_un_tipo_inventado_se_rechaza(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'inventado',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B' ),
				'correctas' => array( 0 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'El tipo de pregunta no es válido.', $r['errores'] );
		$this->assertSame( 'unica', $r['pregunta']['tipo'], 'se cae a un tipo seguro' );
	}

	/**
	 * Sin enunciado no hay pregunta.
	 */
	public function test_sin_enunciado_no_hay_pregunta(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => '   ',
				'opciones'  => array( 'A', 'B' ),
				'correctas' => array( 0 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'La pregunta necesita un enunciado.', $r['errores'] );
	}

	/**
	 * Con menos de dos opciones distintas no hay nada que elegir.
	 */
	public function test_con_una_sola_opcion_no_hay_nada_que_elegir(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'A' ),
				'correctas' => array( 0 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'La pregunta necesita al menos dos opciones distintas.', $r['errores'] );
	}

	/**
	 * Verdadero/falso usa siempre sus dos opciones, aunque lleguen otras.
	 */
	public function test_verdadero_falso_impone_sus_opciones(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'verdadero_falso',
				'enunciado' => 'El agua hierve a 100 grados',
				'opciones'  => array( 'Sí', 'No', 'Quizá' ),
				'correctas' => array( 0 ),
			)
		);

		$this->assertTrue( $r['ok'] );
		$this->assertSame( array( 'Verdadero', 'Falso' ), $r['pregunta']['opciones'] );
	}

	/**
	 * Sin respuesta correcta no hay pregunta.
	 */
	public function test_sin_correcta_no_hay_pregunta(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B' ),
				'correctas' => array(),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'Hay que marcar cuál es la respuesta correcta.', $r['errores'] );
	}

	/**
	 * Una correcta fuera de rango se descarta: la pregunta queda sin respuesta.
	 */
	public function test_una_correcta_fuera_de_rango_se_descarta(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B' ),
				'correctas' => array( 5, -1 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $r['pregunta']['correctas'] );
	}

	/**
	 * Respuesta única con dos marcadas: no vale.
	 */
	public function test_respuesta_unica_con_dos_correctas_no_vale(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'unica',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B', 'C' ),
				'correctas' => array( 0, 2 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'Una pregunta de respuesta única solo puede tener una correcta.', $r['errores'] );
	}

	/**
	 * Respuesta múltiple con una sola marcada: no vale.
	 */
	public function test_respuesta_multiple_con_una_correcta_no_vale(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'multiple',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B', 'C' ),
				'correctas' => array( 1 ),
			)
		);

		$this->assertFalse( $r['ok'] );
		$this->assertContains( 'Una pregunta de respuesta múltiple necesita al menos dos correctas.', $r['errores'] );
	}

	/**
	 * Respuesta múltiple con dos marcadas: vale, y las correctas salen ordenadas y sin repetir.
	 */
	public function test_respuesta_multiple_ordena_y_deduplica(): void {
		$r = Questions::validate(
			array(
				'tipo'      => 'multiple',
				'enunciado' => 'Pregunta',
				'opciones'  => array( 'A', 'B', 'C' ),
				'correctas' => array( 2, 0, 0 ),
			)
		);

		$this->assertTrue( $r['ok'] );
		$this->assertSame( array( '0', '2' ), $r['pregunta']['correctas'] );
	}

	/**
	 * Los puntos nunca quedan a cero ni negativos.
	 */
	public function test_los_puntos_no_quedan_a_cero(): void {
		$base = array(
			'tipo'      => 'unica',
			'enunciado' => 'Pregunta',
			'opciones'  => array( 'A', 'B' ),
			'correctas' => array( 0 ),
		);

		$this->assertSame( 1.0, Questions::validate( $base + array( 'puntos' => 0 ) )['pregunta']['puntos'] );
		$this->assertSame( 1.0, Questions::validate( $base + array( 'puntos' => -5 ) )['pregunta']['puntos'] );
		$this->assertSame( 1.0, Questions::validate( $base )['pregunta']['puntos'], 'sin puntos, un punto' );
	}

	/**
	 * Limpiar una lista de textos quita vacíos, repetidos y lo que no es texto.
	 */
	public function test_limpiar_lista_de_textos(): void {
		$this->assertSame( array(), Questions::clean_list( 'no es una lista' ) );
		$this->assertSame(
			array( 'uno', 'dos' ),
			Questions::clean_list( array( ' uno ', '', 'dos', 'uno', array( 'anidado' ) ) )
		);
	}

	/**
	 * Limpiar índices acepta un valor suelto y quita los vacíos.
	 */
	public function test_limpiar_lista_de_indices(): void {
		$this->assertSame( array( '2' ), Questions::clean_keys( 2 ), 'un valor suelto cuenta como una lista de uno' );
		$this->assertSame( array( '0', '2' ), Questions::clean_keys( array( '0', '', ' 2 ', array( 'x' ) ) ) );
	}

	/**
	 * Sin examen no se cuentan preguntas de más.
	 */
	public function test_contar_sin_preguntas(): void {
		$this->assertSame( 0, Questions::count_for_exam( 4 ) );
	}

	/**
	 * Sin identificador no se busca la pregunta.
	 */
	public function test_sin_identificador_no_se_busca(): void {
		$this->assertNull( Questions::get( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin id no se consulta la base' );
	}

	/**
	 * Una fila encontrada se normaliza: opciones y correctas salen de su JSON.
	 */
	public function test_una_pregunta_encontrada_se_normaliza(): void {
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'        => '7',
				'exam_id'   => '4',
				'tipo'      => 'multiple',
				'enunciado' => 'Pregunta',
				'opciones'  => '["A","B","C"]',
				'correctas' => '[0,2]',
				'puntos'    => '1.5',
			),
		);

		$pregunta = Questions::get( 7 );

		$this->assertSame( 7, $pregunta['id'] );
		$this->assertSame( array( 'A', 'B', 'C' ), $pregunta['opciones'] );
		$this->assertSame( array( '0', '2' ), $pregunta['correctas'], 'las correctas salen como texto, listas para comparar' );
		$this->assertSame( 1.5, $pregunta['puntos'] );
	}

	/**
	 * Un JSON roto no revienta: la pregunta queda sin opciones.
	 */
	public function test_un_json_roto_no_revienta(): void {
		$pregunta = Questions::decode(
			array(
				'opciones'  => 'no-es-json',
				'correctas' => '{',
			)
		);

		$this->assertSame( array(), $pregunta['opciones'] );
		$this->assertSame( array(), $pregunta['correctas'] );
		$this->assertSame( 'unica', $pregunta['tipo'], 'sin tipo se asume respuesta única' );
	}

	/**
	 * Borrar sin identificador no ejecuta nada.
	 */
	public function test_borrar_sin_identificador_no_hace_nada(): void {
		$this->assertFalse( Questions::delete( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->borrados );
	}

	/**
	 * Borrar una pregunta usa la API de borrado con su condición.
	 */
	public function test_borrar_una_pregunta(): void {
		$this->assertTrue( Questions::delete( 7 ) );
		$this->assertSame( array( 'id' => 7 ), $GLOBALS['wpdb']->borrados[0][1] );
	}

	/**
	 * Borrar las preguntas de un examen sin examen no hace nada.
	 */
	public function test_borrar_las_preguntas_sin_examen_no_hace_nada(): void {
		Questions::delete_for_exam( 0 );

		$this->assertSame( array(), $GLOBALS['wpdb']->borrados );
	}

	/**
	 * Borrar las preguntas de un examen se lleva solo las de ese examen.
	 */
	public function test_borrar_las_preguntas_de_un_examen(): void {
		Questions::delete_for_exam( 4 );

		$this->assertSame( array( 'exam_id' => 4 ), $GLOBALS['wpdb']->borrados[0][1] );
	}
}
