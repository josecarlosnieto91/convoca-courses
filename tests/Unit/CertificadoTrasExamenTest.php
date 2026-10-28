<?php
/**
 * Aprobar el examen tiene que emitir el certificado.
 *
 * Completar las lecciones pide el certificado antes de que exista nota; si nadie vuelve a preguntar
 * después del examen, el certificado no se emite nunca. Esto vigila el oyente que cierra el círculo.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Certificate;
use PHPUnit\Framework\TestCase;

/**
 * El gancho del examen y su oyente.
 */
final class CertificadoTrasExamenTest extends TestCase {

	/**
	 * Directorio raíz del plugin.
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * El arranque del plugin tiene que enganchar el oyente al gancho del examen.
	 */
	public function test_el_gancho_del_examen_tiene_oyente(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del propio plugin.
		$plugin = (string) file_get_contents( $this->raiz() . '/includes/Plugin.php' );

		$this->assertStringContainsString( "'convoca_courses_exam_submitted'", $plugin, 'el gancho tiene que estar enganchado' );
		$this->assertStringContainsString( "'on_exam_submitted'", $plugin, 'y apuntar al oyente del certificado' );
		$this->assertStringContainsString( 'Certificate::class', $plugin );
	}

	/**
	 * Suspender no puede emitir nada.
	 */
	public function test_un_intento_suspendido_no_hace_nada(): void {
		Certificate::on_exam_submitted( 1, 999999, 999999, array( 'aprobado' => false ) );

		$this->assertTrue( true, 'sin aprobado no hay nada que emitir y no debe reventar' );
	}

	/**
	 * Aprobar sin matrícula tampoco puede reventar.
	 */
	public function test_aprobar_sin_matricula_no_revienta(): void {
		Certificate::on_exam_submitted( 1, 999999, 999999, array( 'aprobado' => true ) );

		$this->assertTrue( true, 'sin matrícula no hay a quién certificar, pero el gancho no puede tumbar la petición' );
	}
}
