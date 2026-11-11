<?php
/**
 * Registro inalterable: cadena, ancla y resumen.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access_Log;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras del registro.
 */
final class AccessLogTest extends TestCase {

	/**
	 * Construye una fila encadenada.
	 *
	 * @param array<string, mixed> $cambios Cambios.
	 * @param string               $prev    Hash anterior.
	 * @return array<string, mixed>
	 */
	private function fila( array $cambios, string $prev = '' ): array {
		$fila = array_merge(
			array(
				'created_utc'  => '2026-11-12 00:00:00',
				'event'        => 'login',
				'user_id'      => 7,
				'identidad_id' => 0,
				'course_id'    => 0,
				'lesson_id'    => 0,
				'object_id'    => 0,
				'ip'           => '192.0.2.1',
				'user_agent'   => 'PHPUnit',
				'session_id'   => 'abc',
				'duration'     => 0,
				'data'         => '{}',
			),
			$cambios
		);

		$fila['prev_hash'] = $prev;
		$fila['hash']      = Access_Log::hash_fila( $fila, $prev );

		return $fila;
	}

	/**
	 * Una cadena correcta verifica.
	 */
	public function test_la_cadena_correcta_verifica(): void {
		$primera = $this->fila( array() );
		$segunda = $this->fila( array( 'event' => 'logout' ), (string) $primera['hash'] );

		$this->assertTrue( Access_Log::verify_rows( array( $primera, $segunda ) ) );
	}

	/**
	 * Alterar un campo rompe la verificación.
	 */
	public function test_alterar_una_fila_rompe_la_cadena(): void {
		$primera          = $this->fila( array() );
		$segunda          = $this->fila( array( 'event' => 'logout' ), (string) $primera['hash'] );
		$segunda['event'] = 'login';

		$this->assertFalse( Access_Log::verify_rows( array( $primera, $segunda ) ) );
	}

	/**
	 * Borrar una fila intermedia se detecta porque el `prev_hash` no casa.
	 */
	public function test_borrar_una_fila_intermedia_se_detecta(): void {
		$primera = $this->fila( array() );
		$segunda = $this->fila( array( 'event' => 'logout' ), (string) $primera['hash'] );

		$this->assertFalse( Access_Log::verify_rows( array( $segunda ) ) );
	}

	/**
	 * El ancla permite verificar después de purgar las filas viejas.
	 */
	public function test_el_ancla_permite_verificar_tras_la_purga(): void {
		$vieja = $this->fila( array() );
		$nueva = $this->fila( array( 'event' => 'acceso_curso' ), (string) $vieja['hash'] );

		$this->assertTrue( Access_Log::verify_rows( array( $nueva ), (string) $vieja['hash'] ) );
	}

	/**
	 * Resumen de filas.
	 */
	public function test_resumen_agrega_eventos_ultima_fecha_y_duracion(): void {
		$resumen = Access_Log::resumen_filas(
			array(
				array(
					'event'       => 'login',
					'created_utc' => '2026-11-12 10:00:00',
					'duration'    => 0,
				),
				array(
					'event'       => 'acceso_curso',
					'created_utc' => '2026-11-12 10:05:00',
					'duration'    => 120,
				),
				array(
					'event'       => 'login',
					'created_utc' => '2026-11-12 09:00:00',
					'duration'    => 0,
				),
			)
		);

		$this->assertSame( 3, $resumen['total'] );
		$this->assertSame( '2026-11-12 10:05:00', $resumen['ultima'] );
		$this->assertSame( 120, $resumen['duracion'] );
		$this->assertSame( 2, $resumen['eventos']['login'] );
	}
}
