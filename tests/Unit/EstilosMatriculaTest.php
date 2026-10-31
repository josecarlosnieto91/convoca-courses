<?php
/**
 * El formulario de matrícula tiene que tener estilos propios (courses#26).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Matricula_Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Contrato mínimo: cada clase que emite el formulario tiene una regla en la hoja pública.
 */
final class EstilosMatriculaTest extends TestCase {

	/**
	 * Clases que emite la plantilla y deben estar cubiertas.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function clases(): array {
		return array(
			'contenedor' => array( '.convoca-matricula {' ),
			'formulario' => array( '.convoca-matricula__form' ),
			'boton'      => array( '.convoca-boton' ),
			'avisos'     => array( '.convoca-alert' ),
			'info'       => array( '.convoca-alert--info' ),
			'exito'      => array( '.convoca-alert--success' ),
			'error'      => array( '.convoca-alert--error' ),
		);
	}

	/**
	 * La hoja pública cubre las clases del formulario.
	 *
	 * @dataProvider clases
	 *
	 * @param string $selector Selector esperado.
	 */
	public function test_la_hoja_cubre_el_formulario( string $selector ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo, no una URL.
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/courses.css' );

		$this->assertStringContainsString( $selector, $css, 'falta la regla ' . $selector );
	}

	/**
	 * El formulario se sirve con las clases que la hoja espera.
	 */
	public function test_el_formulario_emite_las_clases_que_se_estilan(): void {
		$html = Matricula_Identidad::render( array( 'course' => '123' ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo, no una URL.
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/courses.css' );

		$this->assertStringContainsString( 'convoca-matricula', $html );

		if ( false !== strpos( $html, 'convoca-boton' ) ) {
			$this->assertStringContainsString( '.convoca-boton', $css );
		}
	}

	/**
	 * Los campos llevan el contenedor que la hoja usa para estilarlos.
	 */
	public function test_los_campos_cuelgan_del_contenedor(): void {
		$html = Matricula_Identidad::render( array( 'course' => '123' ) );

		$this->assertStringContainsString( 'convoca-matricula__form', $html, 'sin este contenedor los inputs no reciben estilo' );
	}
}
