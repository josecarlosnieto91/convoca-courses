<?php
/**
 * Los tres defectos del curso: la recursión de la portada, el orden del temario y los enlaces del índice.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Shortcodes;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Portada, orden y enlaces.
 */
final class CursoPortadaOrdenEnlacesTest extends TestCase {

	/**
	 * Directorio raíz del plugin.
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Invoca un método privado de Shortcodes.
	 *
	 * @param string $metodo Método.
	 * @param array  $args   Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $args ) {
		$ref = new ReflectionMethod( Shortcodes::class, $metodo );

		return $ref->invokeArgs( null, $args );
	}

	/**
	 * La guarda de reentrada tiene que cortar la segunda llamada.
	 */
	public function test_la_portada_no_se_renderiza_dos_veces(): void {
		$prop = new ReflectionProperty( Shortcodes::class, 'renderizando' );
		$prop->setValue( null, true );

		$this->assertSame( '', Shortcodes::course( array() ), 'dentro de otra portada devuelve vacío, no recursa' );

		$prop->setValue( null, false );
	}

	/**
	 * El contenido del curso se queda sin el shortcode de portada, pero con el resto del temario.
	 */
	public function test_el_contenido_quita_la_portada_y_conserva_el_indice(): void {
		$GLOBALS['convoca_test_campos'][77]['post_content'] = "Antes\n[convoca_curso id=\"12\"]\n[convoca_curso_lecciones]\nDespués";

		$limpio = (string) $this->privado( 'contenido_del_curso', array( 77 ) );

		$this->assertStringNotContainsString( '[convoca_curso id', $limpio, 'la portada no puede quedar dentro: vuelve a ejecutar el shortcode' );
		$this->assertStringContainsString( '[convoca_curso_lecciones]', $limpio, 'el índice sí se queda' );
		$this->assertStringContainsString( 'Antes', $limpio );
	}

	/**
	 * Sin página de lección no hay enlace: mejor sin enlace que un enlace muerto.
	 */
	public function test_sin_pagina_de_leccion_no_hay_url(): void {
		$this->assertSame( '', Shortcodes::lesson_url( 0 ), 'sin lección no hay URL' );
	}

	/**
	 * El orden del temario no puede depender de un metadato que vale lo mismo en todas las filas.
	 */
	public function test_modulos_y_lecciones_se_ordenan_por_menu_order(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del propio plugin, no una URL.
		$modulo = (string) file_get_contents( $this->raiz() . '/includes/CPT_Modulo.php' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- idem.
		$leccion = (string) file_get_contents( $this->raiz() . '/includes/CPT_Leccion.php' );

		$this->assertStringContainsString( "'orderby'        => 'menu_order'", $modulo, 'los módulos se ordenan por menu_order' );
		$this->assertStringContainsString( "'orderby'        => 'menu_order'", $leccion, 'las lecciones se ordenan por menu_order' );
		$this->assertStringNotContainsString( "'orderby'        => 'meta_value_num'", $modulo, 'ordenar por el id del curso no ordena nada' );
		$this->assertStringNotContainsString( "'orderby'        => 'meta_value_num'", $leccion, 'ordenar por el id del módulo no ordena nada' );
	}

	/**
	 * El índice tiene que envolver la lección en un enlace cuando hay URL y no está bloqueada.
	 */
	public function test_el_indice_enlaza_solo_las_lecciones_accesibles(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		$plantilla = (string) file_get_contents( $this->raiz() . '/templates/lecciones.php' );

		$this->assertStringContainsString( 'convoca-courses__lesson-link', $plantilla );
		$this->assertStringContainsString( '<a class="convoca-courses__lesson-link"', $plantilla );
		$this->assertStringContainsString( "['locked']", $plantilla, 'la condición mira el bloqueo' );
		$this->assertStringContainsString( "'url'", $plantilla, 'la condición mira la URL resuelta' );
		$this->assertStringContainsString( 'id="leccion-', $plantilla, 'cada lección lleva su ancla' );
	}
}
