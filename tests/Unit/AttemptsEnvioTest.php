<?php
/**
 * Intentos: el envío y la corrección, y las lecturas que los alimentan.
 *
 * `Attempts` tenía cubiertas sus funciones puras pero no `submit()`, que es donde se decide la nota
 * y donde un intento ajeno o ya cerrado tiene que quedarse fuera.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Attempts;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\Installer;
use Convoca\Courses\Questions;
use PHPUnit\Framework\TestCase;

/**
 * Envío, corrección y lecturas de los intentos.
 */
class AttemptsEnvioTest extends TestCase {

	/**
	 * Momento fijo de las pruebas.
	 *
	 * @var int
	 */
	private const AHORA = 1_761_220_800;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$GLOBALS['convoca_courses_ahora'] = self::AHORA;
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Questions.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Quiz.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Attempts.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		$GLOBALS['wpdb']->vars              = array();
		$GLOBALS['wpdb']->filas             = array();
		$GLOBALS['wpdb']->resultados        = array();
		$GLOBALS['wpdb']->consultas         = array();
		$GLOBALS['wpdb']->inserts           = array();
		$GLOBALS['wpdb']->actualizados      = array();
		$GLOBALS['wpdb']->borrados          = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['convoca_test_meta']       = array();
	}

	/**
	 * Sin identificador no hay intento.
	 */
	public function test_sin_identificador_no_hay_intento(): void {
		$this->assertNull( Attempts::get( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );
	}

	/**
	 * Una fila encontrada se normaliza.
	 */
	public function test_una_fila_de_intento_se_normaliza(): void {
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'          => '4',
				'exam_id'     => '7',
				'user_id'     => '9',
				'estado'      => 'abierto',
				'puntuacion'  => '3.333',
				'iniciado_en' => '2026-10-23 09:00:00',
			),
		);

		$intento = Attempts::get( 4 );

		$this->assertSame( 4, $intento['id'] );
		$this->assertSame( 9, $intento['user_id'] );
		$this->assertSame( 3.33, $intento['puntuacion'], 'la puntuación se redondea a dos decimales' );
	}

	/**
	 * Sin persona o sin examen no se cuentan intentos.
	 */
	public function test_sin_persona_o_sin_examen_no_se_cuentan(): void {
		$this->assertSame( 0, Attempts::count_for( 0, 7 ) );
		$this->assertSame( 0, Attempts::count_for( 9, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );
	}

	/**
	 * Los intentos contados salen de la consulta.
	 */
	public function test_los_intentos_se_cuentan(): void {
		$GLOBALS['wpdb']->vars = array( '3' );

		$this->assertSame( 3, Attempts::count_for( 9, 7 ) );
	}

	/**
	 * Sin persona o sin curso no hay mejores notas por examen.
	 */
	public function test_sin_persona_o_sin_curso_no_hay_mejores_notas(): void {
		$this->assertSame( array(), Attempts::best_by_exam( 0, 3 ) );
		$this->assertSame( array(), Attempts::best_by_exam( 9, 0 ) );
	}

	/**
	 * Las mejores notas se indexan por examen y se redondean.
	 */
	public function test_las_mejores_notas_se_indexan_por_examen(): void {
		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'exam_id' => '7',
					'nota'    => '6.666',
				),
			),
		);

		$this->assertSame( array( 7 => 6.67 ), Attempts::best_by_exam( 9, 3 ) );
	}

	/**
	 * Sin filas no hay mejores notas.
	 */
	public function test_sin_filas_no_hay_mejores_notas(): void {
		$this->assertSame( array(), Attempts::best_by_exam( 9, 3 ) );
	}

	/**
	 * Sin intento no hay respuestas.
	 */
	public function test_sin_intento_no_hay_respuestas(): void {
		$this->assertSame( array(), Attempts::answers( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );
	}

	/**
	 * Las respuestas de un intento se normalizan.
	 */
	public function test_las_respuestas_de_un_intento_se_normalizan(): void {
		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'question_id'      => '5',
					'respuesta'        => '[0]',
					'acertada'         => '1',
					'puntos_obtenidos' => '2.5',
				),
			),
		);

		$respuestas = Attempts::answers( 4 );

		$this->assertCount( 1, $respuestas );
		$this->assertSame( 5, $respuestas[0]['question_id'] );
		$this->assertSame( array( '0' ), $respuestas[0]['respuesta'], 'la respuesta sale de su JSON' );
		$this->assertTrue( $respuestas[0]['correcta'] );
		$this->assertSame( 2.5, $respuestas[0]['puntos'] );
	}

	/**
	 * Borrar los intentos sin examen no hace nada.
	 */
	public function test_borrar_los_intentos_sin_examen_no_hace_nada(): void {
		Attempts::delete_for_exam( 0 );

		$this->assertSame( array(), $GLOBALS['wpdb']->borrados );
	}

	/**
	 * Borrar los intentos de un examen se lleva también sus respuestas.
	 */
	public function test_borrar_los_intentos_de_un_examen(): void {
		Attempts::delete_for_exam( 7 );

		$this->assertNotEmpty( $GLOBALS['wpdb']->borrados, 'se borran las respuestas y los intentos' );
		$this->assertSame( array( 'exam_id' => 7 ), $GLOBALS['wpdb']->borrados[0][1] );
	}

	/**
	 * Las preguntas candidatas juntan las del examen y las del banco del curso.
	 */
	public function test_las_preguntas_candidatas_juntan_examen_y_banco(): void {
		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'id'        => '5',
					'course_id' => '3',
					'tipo'      => 'unica',
					'opciones'  => '["A","B"]',
					'correctas' => '[0]',
				),
			),
			array(
				array(
					'id'        => '6',
					'course_id' => '3',
					'tipo'      => 'unica',
					'opciones'  => '["A","B"]',
					'correctas' => '[0]',
				),
				array(
					'id'        => '7',
					'course_id' => '99',
					'tipo'      => 'unica',
					'opciones'  => '["A","B"]',
					'correctas' => '[0]',
				),
			),
		);

		$this->assertSame( array( 5, 6 ), Attempts::candidates( 7, 3 ), 'el banco de otro curso no entra' );
	}

	/**
	 * Enviar un intento que no existe devuelve error.
	 */
	public function test_enviar_un_intento_que_no_existe(): void {
		$r = Attempts::submit( 4, 9, array() );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_attempt', $r->get_error_code() );
	}

	/**
	 * Enviar el intento de otra persona devuelve error.
	 */
	public function test_enviar_el_intento_de_otra_persona(): void {
		$this->intento( 4, 9, 'abierto' );

		$r = Attempts::submit( 4, 10, array() );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_attempt_owner', $r->get_error_code() );
	}

	/**
	 * Un intento ya cerrado no se puede enviar otra vez.
	 */
	public function test_un_intento_cerrado_no_se_envia(): void {
		$this->intento( 4, 9, 'enviado' );

		$r = Attempts::submit( 4, 9, array() );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_attempt_closed', $r->get_error_code() );
	}

	/**
	 * Un intento fuera de plazo se cierra a cero sin corregir.
	 */
	public function test_un_intento_fuera_de_plazo_se_cierra_a_cero(): void {
		$this->intento( 4, 9, 'abierto', 30, self::AHORA - 7200 );

		$r = Attempts::submit( 4, 9, array( 5 => array( 0 ) ) );

		$this->assertSame( 'expirado', $r['estado'] );
		$this->assertSame( 0.0, $r['puntuacion'] );
		$this->assertFalse( $r['aprobado'] );
		$this->assertFalse( $r['recibido'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->actualizados, 'el intento se marca expirado' );
		$this->assertSame( 'expirado', $GLOBALS['wpdb']->actualizados[0][1]['estado'] );
	}

	/**
	 * Enviar dentro de plazo corrige, guarda la nota y apunta cada respuesta.
	 */
	public function test_enviar_dentro_de_plazo_corrige_y_guarda(): void {
		$this->intento( 4, 9, 'abierto', 0, self::AHORA - 60, array( 5 ) );
		$this->pregunta( 5, 'unica', '["A","B"]', '[0]', 2.0 );

		$r = Attempts::submit( 4, 9, array( 5 => array( 0 ) ) );

		$this->assertTrue( $r['recibido'] );
		$this->assertSame( 'enviado', $r['estado'] );
		$this->assertSame( 100.0, $r['puntuacion'], 'una de una acertada es el 100 %' );
		$this->assertTrue( $r['aprobado'] );

		$this->assertCount( 1, $GLOBALS['wpdb']->inserts, 'cada pregunta corregida se guarda' );
		$insertada = $GLOBALS['wpdb']->inserts[0][1];
		$this->assertSame( 5, $insertada['question_id'] );
		$this->assertSame( 1, $insertada['acertada'] );
		$this->assertSame( 2.0, $insertada['puntos_obtenidos'] );

		$ultimo = end( $GLOBALS['wpdb']->actualizados );
		$this->assertSame( 'enviado', $ultimo[1]['estado'] );
		$this->assertSame( 100.0, $ultimo[1]['puntuacion'] );
		$this->assertSame( 1, $ultimo[1]['aprobado'] );
	}

	/**
	 * Una respuesta equivocada da cero puntos pero también se guarda.
	 */
	public function test_una_respuesta_equivocada_se_guarda_con_cero(): void {
		$this->intento( 4, 9, 'abierto', 0, self::AHORA - 60, array( 5 ) );
		$this->pregunta( 5, 'unica', '["A","B"]', '[0]', 2.0 );

		$r = Attempts::submit( 4, 9, array( 5 => array( 1 ) ) );

		$this->assertSame( 0.0, $r['puntuacion'] );
		$this->assertFalse( $r['aprobado'] );
		$this->assertSame( 0, $GLOBALS['wpdb']->inserts[0][1]['acertada'] );
		$this->assertSame( 0.0, $GLOBALS['wpdb']->inserts[0][1]['puntos_obtenidos'] );
	}

	/**
	 * Monta un intento en el doble de la base.
	 *
	 * @param int    $id       Identificador del intento.
	 * @param int    $persona  Persona dueña.
	 * @param string $estado   Estado.
	 * @param int    $minutos  Tiempo máximo del examen (0 = sin límite).
	 * @param int    $iniciado Marca de tiempo del inicio.
	 * @param array  $preguntas Identificadores de las preguntas del intento.
	 */
	private function intento( int $id, int $persona, string $estado, int $minutos = 0, ?int $iniciado = null, array $preguntas = array() ): void {
		$iniciado = $iniciado ?? self::AHORA;

		$GLOBALS['convoca_test_post_types'][7]                                      = CPT_Examen::TYPE;
		$GLOBALS['convoca_test_meta'][7][ CPT_Base::META_PREFIX . 'curso_id' ]      = '3';
		$GLOBALS['convoca_test_meta'][7][ CPT_Base::META_PREFIX . 'tiempo_maximo' ] = (string) $minutos;

		$GLOBALS['wpdb']->filas = array(
			array(
				'id'          => (string) $id,
				'exam_id'     => '7',
				'course_id'   => '3',
				'user_id'     => (string) $persona,
				'estado'      => $estado,
				'iniciado_en' => gmdate( 'Y-m-d H:i:s', $iniciado ),
				'preguntas'   => (string) \wp_json_encode( $preguntas ),
			),
		);
	}

	/**
	 * Encola una pregunta en el doble de la base.
	 *
	 * @param int    $id        Identificador.
	 * @param string $tipo      Tipo.
	 * @param string $opciones  Opciones en JSON.
	 * @param string $correctas Correctas en JSON.
	 * @param float  $puntos    Puntos.
	 */
	private function pregunta( int $id, string $tipo, string $opciones, string $correctas, float $puntos ): void {
		$GLOBALS['wpdb']->filas[]              = array(
			'id'        => (string) $id,
			'exam_id'   => '7',
			'course_id' => '3',
			'tipo'      => $tipo,
			'opciones'  => $opciones,
			'correctas' => $correctas,
			'puntos'    => (string) $puntos,
		);
		$GLOBALS['convoca_test_post_types'][7] = CPT_Examen::TYPE;
	}
}
