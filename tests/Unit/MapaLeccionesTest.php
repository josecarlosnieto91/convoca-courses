<?php
/**
 * El resolvedor de enlaces de lección: sin tope, una consulta y aviso cuando falta la página.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Shortcodes;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Mapa lección → página.
 */
final class MapaLeccionesTest extends TestCase {

	/**
	 * Reinicia el mapa y el doble de $wpdb entre pruebas.
	 */
	protected function setUp(): void {
		$GLOBALS['__gw_options']     = array();
		$GLOBALS['wpdb']->resultados = array();
		$GLOBALS['wpdb']->consultas  = array();
		$GLOBALS['wpdb']->vars       = array();

		$prop = new ReflectionProperty( Shortcodes::class, 'mapa_lecciones' );
		$prop->setValue( null, null );
	}

	/**
	 * Construye una fila como la devolvería la consulta.
	 *
	 * @param int    $page_id Página.
	 * @param string $contenido Contenido.
	 * @return object
	 */
	private function fila( int $page_id, string $contenido ): object {
		return (object) array(
			'ID'           => $page_id,
			'post_content' => $contenido,
		);
	}

	/**
	 * Un curso de 30 lecciones en un sitio con 40 páginas enlaza las 30.
	 */
	public function test_cuarenta_paginas_y_treinta_lecciones_enlazan_todas(): void {
		$filas = array();

		for ( $i = 1; $i <= 30; $i++ ) {
			$filas[] = $this->fila( 100 + $i, '[convoca_curso_leccion id="' . $i . '"]' );
		}
		for ( $i = 1; $i <= 10; $i++ ) {
			$filas[] = $this->fila( 200 + $i, '[convoca_curso_lecciones]' );
		}

		$GLOBALS['wpdb']->resultados = array( $filas );

		for ( $i = 1; $i <= 30; $i++ ) {
			$this->assertNotSame( '', Shortcodes::lesson_url( $i ), 'la lección ' . $i . ' tiene que quedar enlazada' );
		}

		$this->assertSame( '', Shortcodes::lesson_url( 31 ), 'lo que no es una lección no se enlaza' );
	}

	/**
	 * Una página con solo el índice no cuenta como página de lección.
	 */
	public function test_el_indice_no_cuenta_como_leccion(): void {
		$GLOBALS['wpdb']->resultados = array(
			array( $this->fila( 500, '[convoca_curso_lecciones]' ) ),
		);

		$this->assertSame( '', Shortcodes::lesson_url( 1 ) );
		$this->assertSame( '', Shortcodes::lesson_url( 7 ) );
	}

	/**
	 * Treinta lecciones se resuelven con una sola consulta, no con treinta.
	 */
	public function test_el_mapa_se_construye_una_sola_vez(): void {
		$filas = array();
		for ( $i = 1; $i <= 30; $i++ ) {
			$filas[] = $this->fila( 100 + $i, '[convoca_curso_leccion id="' . $i . '"]' );
		}
		$GLOBALS['wpdb']->resultados = array( $filas );

		for ( $i = 1; $i <= 30; $i++ ) {
			Shortcodes::lesson_url( $i );
		}

		$this->assertCount( 1, $GLOBALS['wpdb']->consultas, 'una consulta para todo el índice' );
	}

	/**
	 * La búsqueda no arrastra el índice del curso.
	 */
	public function test_la_consulta_descarta_el_indice(): void {
		$GLOBALS['wpdb']->resultados = array( array() );

		Shortcodes::lesson_url( 1 );

		$sql = implode( ' ', $GLOBALS['wpdb']->consultas );
		$this->assertStringContainsString( '[convoca_curso_leccion ', $sql, 'el patrón lleva el espacio y deja fuera [convoca_curso_lecciones]' );
	}

	/**
	 * Sin página, quien edita el curso tiene que verlo en la pantalla.
	 */
	public function test_la_pantalla_marca_las_lecciones_sin_pagina(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		$plantilla = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/lecciones.php' );

		$this->assertStringContainsString( 'sin página', $plantilla );
		$this->assertStringContainsString( 'current_user_can', $plantilla, 'el aviso es para quien puede editar, no para el alumnado' );
	}
}
