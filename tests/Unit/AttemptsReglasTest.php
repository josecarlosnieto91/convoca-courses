<?php
/**
 * Los intentos de examen: caducidad, intentos que quedan y lo que se lee de la base.
 *
 * `Attempts` estaba al 2,4 % (6 de 255). Se prueban primero las funciones puras —que es donde vive
 * la regla del examen: cuándo caduca, cuántos intentos quedan, qué preguntas entran— y después las
 * lecturas, con un doble de `$wpdb` que devuelve filas preparadas.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Attempts;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas de los intentos de examen.
 */
class AttemptsReglasTest extends TestCase {

	/**
	 * Caso de prueba.
	 */
	protected function setUp(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->inserts    = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Attempts.php';
	}

	/**
	 * Caso de prueba.
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->inserts    = array();
	}

	// ── caducidad ───────────────────────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_sin_limite_de_tiempo_el_intento_no_caduca(): void {
		$this->assertFalse( Attempts::expired( 1000, 0, 999999 ), 'minutos = 0 quiere decir sin límite' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_sin_fecha_de_inicio_no_se_puede_decir_que_caducó(): void {
		$this->assertFalse( Attempts::expired( 0, 30, 999999 ) );
	}

	/**
	 * Caso de prueba.
	 */
	/**
	 * Dentro del plazo no caduca; pasada la mitad del plazo tampoco; pasada, sí.
	 */
	public function test_dentro_del_tiempo_no_caduca_y_pasado_el_tiempo_si(): void {
		$inicio = 1_700_000_000;

		$this->assertFalse( Attempts::expired( $inicio, 30, $inicio + 29 * MINUTE_IN_SECONDS ) );
		$this->assertTrue( Attempts::expired( $inicio, 30, $inicio + 31 * MINUTE_IN_SECONDS ) );
	}

	// ── intentos que quedan ────────────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_sin_limite_de_intentos_devuelve_menos_uno(): void {
		$this->assertSame( -1, Attempts::remaining( 0, 5 ), '-1 es el código de «sin límite»' );
		$this->assertSame( -1, Attempts::remaining( -3, 5 ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_los_intentos_que_quedan_se_cuentan(): void {
		$this->assertSame( 2, Attempts::remaining( 3, 1 ) );
		$this->assertSame( 0, Attempts::remaining( 1, 1 ), 'agotado no es lo mismo que negativo' );
		$this->assertSame( 0, Attempts::remaining( 1, 9 ), 'nunca menos de cero' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_un_contador_negativo_no_suma_intentos(): void {
		$this->assertSame( 3, Attempts::remaining( 3, -2 ) );
	}

	// ── las preguntas del intento ──────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_las_preguntas_se_devuelven_como_enteros(): void {
		$this->assertSame( array( 4, 5 ), Attempts::question_ids( array( 'preguntas' => array( '4', '5' ) ) ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_un_intento_sin_preguntas_no_inventa_ids(): void {
		$this->assertSame( array(), Attempts::question_ids( array() ) );
		$this->assertSame( array(), Attempts::question_ids( array( 'preguntas' => 'no-es-una-lista' ) ) );
	}

	// ── normalizar una fila ────────────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_una_fila_se_normaliza_con_sus_tipos(): void {
		$fila = array(
			'id'            => '7',
			'exam_id'       => '3',
			'course_id'     => '2',
			'user_id'       => '11',
			'enrollment_id' => '5',
			'intento'       => '2',
			'preguntas'     => '[1,2,3]',
			'iniciado_en'   => '2026-10-01 10:00:00',
			'terminado_en'  => '2026-10-01 10:20:00',
			'puntuacion'    => '7.456',
			'aprobado'      => '1',
			'estado'        => 'enviado',
		);

		$intento = Attempts::decode( $fila );

		$this->assertSame( 7, $intento['id'] );
		$this->assertSame( array( 1, 2, 3 ), $intento['preguntas'], 'las preguntas vienen en JSON: sin decodificar no se pueden corregir' );
		$this->assertSame( 7.46, $intento['puntuacion'], 'la nota se redondea a dos decimales' );
		$this->assertTrue( $intento['aprobado'] );
		$this->assertSame( 'enviado', $intento['estado'] );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_una_fila_vacia_no_revienta(): void {
		$intento = Attempts::decode( array() );

		$this->assertSame( 0, $intento['id'] );
		$this->assertSame( 1, $intento['intento'], 'un intento sin número se lee como el primero' );
		$this->assertSame( array(), $intento['preguntas'] );
		$this->assertSame( 0.0, $intento['puntuacion'] );
		$this->assertSame( 'abierto', $intento['estado'] );
		$this->assertFalse( $intento['aprobado'] );
	}

	// ── lecturas ───────────────────────────────────────────────────────────────────

	/**
	 * Caso de prueba.
	 */
	public function test_un_intento_sin_identificador_no_se_busca(): void {
		$this->assertNull( Attempts::get( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin id no se consulta la base' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_un_intento_se_lee_y_se_normaliza(): void {
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'        => '9',
				'exam_id'   => '4',
				'user_id'   => '2',
				'preguntas' => '[10,11]',
				'estado'    => 'abierto',
			),
		);

		$intento = Attempts::get( 9 );

		$this->assertSame( 9, $intento['id'] );
		$this->assertSame( array( 10, 11 ), $intento['preguntas'] );
		$this->assertStringContainsString( 'convoca', (string) $GLOBALS['wpdb']->consultas[0], 'se consulta la tabla de intentos del plugin' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_si_la_fila_no_es_un_array_no_hay_intento(): void {
		$GLOBALS['wpdb']->filas = array( null );

		$this->assertNull( Attempts::get( 15 ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_sin_persona_o_sin_examen_no_hay_intento_abierto(): void {
		$this->assertNull( Attempts::current( 0, 3 ) );
		$this->assertNull( Attempts::current( 3, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin persona no se busca intento abierto' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_una_nota_sin_persona_es_cero(): void {
		$this->assertSame( 0.0, Attempts::best_score( 0, 3 ) );
		$this->assertSame( 0.0, Attempts::best_score( 3, 0 ) );
		// Y no se consulta la base: sin esta comprobación, la mutación que quita la guarda
		// seguía en verde (get_var sin respuesta también da 0.0). Lo destapó el verificador.
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin persona no hay nada que preguntar' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_la_mejor_nota_se_lee_de_la_base(): void {
		$GLOBALS['wpdb']->vars = array( '8.5' );

		$this->assertSame( 8.5, Attempts::best_score( 2, 4 ) );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_aprobar_sin_persona_o_sin_examen_es_falso(): void {
		$this->assertFalse( Attempts::has_passed( 0, 3 ) );
		$this->assertFalse( Attempts::has_passed( 3, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin persona o sin examen no se consulta' );
	}

	/**
	 * Caso de prueba.
	 */
	public function test_aprobar_se_lee_de_la_base(): void {
		$GLOBALS['wpdb']->vars = array( '1' );
		$this->assertTrue( Attempts::has_passed( 2, 4 ) );

		$GLOBALS['wpdb']->vars = array( '0' );
		$this->assertFalse( Attempts::has_passed( 2, 4 ) );
	}
}
