<?php
/**
 * Entregas de tarea: plazo, estado, normalización de filas y lecturas.
 *
 * `Task` estaba al 12,5 % (20 de 160). Se prueba primero lo que decide la regla —cuándo una entrega
 * va fuera de plazo y qué estado le toca— y después la fila que se escribe y las lecturas, que son
 * donde un tipo mal puesto llega a la base.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Task;
use PHPUnit\Framework\TestCase;

/**
 * Reglas y filas de las entregas de tarea.
 */
class TaskReglasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->inserts    = array();
		$GLOBALS['wpdb']->borrados   = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Task.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb']->vars       = array();
		$GLOBALS['wpdb']->filas      = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->inserts    = array();
		$GLOBALS['wpdb']->borrados   = array();
	}

	/**
	 * Sin plazo, una entrega nunca va tarde.
	 */
	public function test_sin_plazo_no_hay_retraso(): void {
		$this->assertFalse( Task::is_late( 1_700_000_000, 0 ) );
	}

	/**
	 * Entregar después del plazo es tarde; justo en el plazo, no.
	 */
	public function test_el_plazo_se_compara_con_la_entrega(): void {
		$plazo = 1_700_000_000;

		$this->assertFalse( Task::is_late( $plazo, $plazo ), 'entregar en el mismo segundo no es tarde' );
		$this->assertTrue( Task::is_late( $plazo + 1, $plazo ) );
	}

	/**
	 * El estado se deriva del plazo.
	 */
	public function test_el_estado_sale_del_plazo(): void {
		$this->assertSame( 'entregada', Task::status_for( 100, 0 ) );
		$this->assertSame( 'entregada', Task::status_for( 100, 200 ) );
		$this->assertSame( 'fuera_de_plazo', Task::status_for( 300, 200 ) );
	}

	/**
	 * La fila que se escribe lleva los tipos de cada columna.
	 */
	public function test_la_fila_lleva_tipos_y_formatos(): void {
		$fila = Task::row(
			array(
				'task_id'      => '4',
				'course_id'    => '2',
				'user_id'      => '9',
				'contenido'    => 'Mi trabajo',
				'nota'         => '7.5',
				'entregada_en' => '2026-10-01 10:00:00',
			)
		);

		$this->assertSame( 4, $fila['datos']['task_id'], 'los identificadores van como enteros' );
		$this->assertSame( 7.5, $fila['datos']['nota'] );
		$this->assertSame( 'Mi trabajo', $fila['datos']['contenido'] );
		$this->assertSame( 'entregada', $fila['datos']['estado'], 'sin estado se asume entregada' );
		$this->assertSame( 0, $fila['datos']['adjunto_id'] );
		$this->assertCount( 12, $fila['formatos'], 'cada columna lleva su formato: sin eso la base recibe un string' );
		$this->assertSame( '%d', $fila['formatos'][0] );
	}

	/**
	 * Una fila vacía no deja huecos sin formato.
	 */
	public function test_una_fila_vacia_no_revienta(): void {
		$fila = Task::row( array() );

		$this->assertSame( 0, $fila['datos']['task_id'] );
		$this->assertSame( '', $fila['datos']['contenido'] );
		$this->assertSame( 0.0, $fila['datos']['nota'] );
		$this->assertCount( 12, $fila['formatos'] );
	}

	/**
	 * Normalizar una fila de la base pone cada tipo en su sitio.
	 */
	public function test_una_fila_de_la_base_se_normaliza(): void {
		$entrega = Task::decode(
			array(
				'id'         => '7',
				'task_id'    => '4',
				'user_id'    => '9',
				'nota'       => '6.789',
				'estado'     => 'corregida',
				'contenido'  => 'Texto',
				'adjunto_id' => '3',
			)
		);

		$this->assertSame( 7, $entrega['id'] );
		$this->assertSame( 6.79, $entrega['nota'], 'la nota se redondea a dos decimales' );
		$this->assertSame( 3, $entrega['adjunto_id'] );
		$this->assertSame( 'corregida', $entrega['estado'] );
	}

	/**
	 * Una fila vacía se lee como una entrega sin datos, no como un error.
	 */
	public function test_una_fila_vacia_de_la_base_no_revienta(): void {
		$entrega = Task::decode( array() );

		$this->assertSame( 0, $entrega['id'] );
		$this->assertSame( 'entregada', $entrega['estado'] );
		$this->assertSame( 0.0, $entrega['nota'] );
	}

	/**
	 * Sin tarea o sin persona no se busca entrega.
	 */
	public function test_sin_tarea_o_sin_persona_no_se_busca(): void {
		$this->assertNull( Task::submission( 0, 5 ) );
		$this->assertNull( Task::submission( 5, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin datos con los que buscar no se consulta' );
	}

	/**
	 * Sin identificador no se busca una entrega concreta.
	 */
	public function test_sin_identificador_no_se_busca_la_entrega(): void {
		$this->assertNull( Task::get( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas );
	}

	/**
	 * Una entrega encontrada se normaliza.
	 */
	public function test_una_entrega_encontrada_se_normaliza(): void {
		$GLOBALS['wpdb']->filas = array(
			array(
				'id'      => '12',
				'task_id' => '4',
				'nota'    => '8.25',
				'estado'  => 'corregida',
			),
		);

		$entrega = Task::get( 12 );

		$this->assertSame( 12, $entrega['id'] );
		$this->assertSame( 8.25, $entrega['nota'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->consultas );
	}

	/**
	 * Si la fila no es un array no hay entrega.
	 */
	public function test_una_fila_que_no_es_array_no_es_entrega(): void {
		$GLOBALS['wpdb']->filas = array( null );

		$this->assertNull( Task::get( 13 ) );
	}

	/**
	 * Las notas por tarea se indexan por tarea, redondeadas, y las tareas sin corregir no aparecen.
	 */
	public function test_las_notas_por_tarea_se_indexan_y_se_redondean(): void {
		// El doble sirve un conjunto de filas por consulta: se encola el conjunto, no la fila.
		$GLOBALS['wpdb']->resultados = array(
			array(
				array(
					'task_id' => '4',
					'nota'    => '7.555',
				),
				array(
					'task_id' => '5',
					'nota'    => '9',
				),
			),
		);

		$notas = Task::grades_by_task( 9, 2 );

		$this->assertSame(
			array(
				4 => 7.56,
				5 => 9.0,
			),
			$notas,
			'la nota va por tarea y a dos decimales'
		);
		$this->assertStringContainsString( "estado = 'corregida'", implode( ' ', $GLOBALS['wpdb']->consultas ), 'solo cuentan las corregidas' );
	}

	/**
	 * Sin persona o sin curso no se piden notas.
	 */
	public function test_sin_persona_o_sin_curso_no_se_piden_notas(): void {
		$this->assertSame( array(), Task::grades_by_task( 0, 2 ) );
		$this->assertSame( array(), Task::grades_by_task( 9, 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'sin datos con los que preguntar no se consulta' );
	}

	/**
	 * Si la consulta no devuelve filas, no hay notas.
	 */
	public function test_una_consulta_sin_filas_no_da_notas(): void {
		$this->assertSame( array(), Task::grades_by_task( 9, 2 ) );
	}

	/**
	 * Borrar las entregas de una tarea ejecuta su consulta.
	 */
	public function test_borrar_las_entregas_de_una_tarea(): void {
		Task::delete_for_task( 4 );

		$this->assertNotEmpty( $GLOBALS['wpdb']->borrados, 'la limpieza se hace por la API de borrado, no con SQL a mano' );
		$this->assertSame( array( 'task_id' => 4 ), $GLOBALS['wpdb']->borrados[0][1], 'se borra la tarea pedida, no todas' );
	}
}
