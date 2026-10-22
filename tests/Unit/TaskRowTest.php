<?php
/**
 * Pruebas de la fila de una entrega.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Task;
use PHPUnit\Framework\TestCase;

/**
 * El orden de `$wpdb->insert( $tabla, $datos, $formatos )` es posicional: un formato descuadrado guarda
 * el texto como número y el estado como cero. Medido en la demo el 22-10-2026 (entrega con
 * `contenido = 0` y `estado = 0`), así que la alineación se prueba aquí.
 *
 * @covers \Convoca\Courses\Task
 */
final class TaskRowTest extends TestCase {

	/**
	 * Los formatos van en el mismo orden que las columnas y con el tipo de cada una.
	 *
	 * @return void
	 */
	public function test_formatos_alineados_con_las_columnas(): void {
		$fila = Task::row(
			array(
				'task_id'       => 9,
				'course_id'     => 5,
				'user_id'       => 12,
				'enrollment_id' => 33,
				'contenido'     => 'Texto de la entrega',
				'adjunto_id'    => 77,
				'entregada_en'  => '2026-10-22 15:00:00',
				'estado'        => 'entregada',
			)
		);

		$this->assertSame(
			array( 'task_id', 'course_id', 'user_id', 'enrollment_id', 'contenido', 'adjunto_id', 'entregada_en', 'estado', 'nota', 'comentario', 'corregida_por', 'corregida_en' ),
			array_keys( $fila['datos'] )
		);

		$this->assertSame(
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%f', '%s', '%d', '%s' ),
			$fila['formatos']
		);

		$this->assertCount( count( $fila['datos'] ), $fila['formatos'] );
	}

	/**
	 * El texto de la entrega sigue siendo texto, y el estado sigue siendo un estado.
	 *
	 * Es el defecto tal cual se midió: con los formatos descuadrados, `contenido` se guardaba como 0 y
	 * `estado` como 0.
	 *
	 * @return void
	 */
	public function test_contenido_y_estado_conservan_su_valor(): void {
		$fila = Task::row(
			array(
				'task_id'   => 1,
				'course_id' => 2,
				'user_id'   => 3,
				'contenido' => 'Resumen del curso',
				'estado'    => 'fuera_de_plazo',
			)
		);

		$this->assertSame( 'Resumen del curso', $fila['datos']['contenido'] );
		$this->assertSame( 'fuera_de_plazo', $fila['datos']['estado'] );
	}

	/**
	 * La corrección nace vacía: una entrega nueva no está corregida.
	 *
	 * @return void
	 */
	public function test_correccion_nace_vacia(): void {
		$fila = Task::row( array( 'task_id' => 1 ) );

		$this->assertSame( 0.0, $fila['datos']['nota'] );
		$this->assertSame( '', $fila['datos']['comentario'] );
		$this->assertSame( 0, $fila['datos']['corregida_por'] );
		$this->assertSame( '0000-00-00 00:00:00', $fila['datos']['corregida_en'] );
		$this->assertSame( 'entregada', $fila['datos']['estado'] );
	}
}
