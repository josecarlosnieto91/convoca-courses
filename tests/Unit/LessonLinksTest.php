<?php
/**
 * Vínculo lección → página por metadato y URL propia de lección.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Lesson_Links;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Metadato y rewrite.
 */
final class LessonLinksTest extends TestCase {

	/**
	 * Arnés limpio entre pruebas.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_rewrites']    = array();
		$GLOBALS['convoca_test_query_vars']  = array();
		$GLOBALS['convoca_test_flush']       = 0;
		$GLOBALS['convoca_courses_opciones'] = array();

		$prop = new ReflectionProperty( Lesson_Links::class, 'mapa_meta' );
		$prop->setValue( null, null );
	}

	/**
	 * Crea una entrada en el arnés.
	 *
	 * @param int    $id     Id.
	 * @param string $tipo   Tipo.
	 * @param string $estado Estado.
	 * @param string $texto  Contenido.
	 * @return void
	 */
	private function entrada( int $id, string $tipo, string $estado, string $texto = '' ): void {
		$GLOBALS['convoca_test_post_types'][ $id ] = $tipo;
		$GLOBALS['convoca_test_posts'][ $id ]      = (object) array(
			'ID'           => $id,
			'post_type'    => $tipo,
			'post_status'  => $estado,
			'post_content' => $texto,
			'post_name'    => 'leccion-' . $id,
		);
	}

	/**
	 * Extrae las lecciones citadas y no confunde el índice.
	 */
	public function test_extrae_las_lecciones_y_no_confunde_el_indice(): void {
		$texto = '[convoca_curso_lecciones] [convoca_curso_leccion id="4"] [convoca_curso_leccion id="9"] [convoca_curso_leccion id="4"]';

		$this->assertSame( array( 4, 9 ), Lesson_Links::lesson_ids_in( $texto ) );
	}

	/**
	 * Guardar la página anota el vínculo en cada lección.
	 */
	public function test_guardar_la_pagina_anota_el_vinculo(): void {
		$this->entrada( 500, 'page', 'publish', '[convoca_curso_leccion id="4"] [convoca_curso_leccion id="9"]' );
		$this->entrada( 4, 'convoca_leccion', 'publish' );
		$this->entrada( 9, 'convoca_leccion', 'publish' );

		Lesson_Links::sync_content( 500, '[convoca_curso_leccion id="4"] [convoca_curso_leccion id="9"]', 'publish', 'page' );

		$this->assertSame( 500, (int) ( $GLOBALS['convoca_test_meta'][4]['_convoca_pagina_id'] ?? 0 ) );
		$this->assertSame( 500, (int) ( $GLOBALS['convoca_test_meta'][9]['_convoca_pagina_id'] ?? 0 ) );
	}

	/**
	 * Quitar el shortcode de la página borra el vínculo de esa lección.
	 */
	public function test_quitar_una_leccion_borra_su_vinculo(): void {
		$this->entrada( 500, 'page', 'publish', '[convoca_curso_leccion id="4"] [convoca_curso_leccion id="9"]' );
		$this->entrada( 4, 'convoca_leccion', 'publish' );
		$this->entrada( 9, 'convoca_leccion', 'publish' );
		Lesson_Links::sync_content( 500, '[convoca_curso_leccion id="4"] [convoca_curso_leccion id="9"]', 'publish', 'page' );

		Lesson_Links::sync_content( 500, '[convoca_curso_leccion id="4"]', 'publish', 'page' );

		$this->assertSame( 500, (int) ( $GLOBALS['convoca_test_meta'][4]['_convoca_pagina_id'] ?? 0 ) );
		$this->assertArrayNotHasKey( '_convoca_pagina_id', $GLOBALS['convoca_test_meta'][9] ?? array(), 'la 9 ya no está en la página' );
	}

	/**
	 * Despublicar la página deja de dar enlaces.
	 */
	public function test_despublicar_limpia_los_vinculos(): void {
		$this->entrada( 500, 'page', 'publish', '[convoca_curso_leccion id="4"]' );
		$this->entrada( 4, 'convoca_leccion', 'publish' );
		Lesson_Links::sync_content( 500, '[convoca_curso_leccion id="4"]', 'publish', 'page' );

		Lesson_Links::sync_content( 500, '', 'draft', 'page' );

		$this->assertArrayNotHasKey( '_convoca_pagina_id', $GLOBALS['convoca_test_meta'][4] ?? array() );
	}

	/**
	 * Las reglas se regeneran una vez por versión, no en cada carga.
	 */
	public function test_las_reglas_se_regeneran_una_vez_por_version(): void {
		Lesson_Links::maybe_flush();

		$this->assertSame( 1, (int) $GLOBALS['convoca_test_flush'], 'la primera vez hay que regenerar' );
		$this->assertSame(
			defined( 'CONVOCA_COURSES_VERSION' ) ? CONVOCA_COURSES_VERSION : '1',
			$GLOBALS['convoca_courses_opciones']['convoca_courses_rewrite_version'] ?? ''
		);

		Lesson_Links::maybe_flush();
		$this->assertSame( 1, (int) $GLOBALS['convoca_test_flush'], 'con la misma versión no se vuelve a regenerar' );
	}

	/**
	 * La regla de rewrite de la URL propia existe y es la acordada.
	 */
	public function test_la_regla_de_rewrite_esta_definida(): void {
		Lesson_Links::register_rewrite();

		$this->assertNotEmpty( $GLOBALS['convoca_test_rewrites'], 'tiene que registrar la regla' );
		$this->assertStringContainsString( 'cursos/', $GLOBALS['convoca_test_rewrites'][0][0] );
		$this->assertStringContainsString( 'convoca_leccion_slug', $GLOBALS['convoca_test_rewrites'][0][1] );
	}
}
