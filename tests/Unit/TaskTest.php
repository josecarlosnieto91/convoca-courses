<?php
/**
 * Pruebas del estado de una entrega.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\Task;
use PHPUnit\Framework\TestCase;

/**
 * Plazo de entrega y estado resultante.
 *
 * @covers \Convoca\Courses\Task
 */
final class TaskTest extends TestCase {

	/**
	 * Sin plazo, nunca es tarde.
	 *
	 * @return void
	 */
	public function test_sin_plazo_no_es_tarde(): void {
		$this->assertFalse( Task::is_late( 999999, 0 ) );
		$this->assertSame( 'entregada', Task::status_for( 999999, 0 ) );
	}

	/**
	 * Antes del plazo, entregada.
	 *
	 * @return void
	 */
	public function test_antes_del_plazo(): void {
		$this->assertFalse( Task::is_late( 100, 200 ) );
		$this->assertSame( 'entregada', Task::status_for( 100, 200 ) );
	}

	/**
	 * Después del plazo, fuera de plazo.
	 *
	 * @return void
	 */
	public function test_despues_del_plazo(): void {
		$this->assertTrue( Task::is_late( 300, 200 ) );
		$this->assertSame( 'fuera_de_plazo', Task::status_for( 300, 200 ) );
	}
}
