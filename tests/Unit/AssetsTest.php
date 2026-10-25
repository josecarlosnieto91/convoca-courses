<?php
/**
 * Carga de CSS y JS del módulo.
 *
 * La mitad del comportamiento de los assets es **lo que no sale**: en una página que no es un curso ni
 * lleva un shortcode del módulo no se encola nada, y en el panel tampoco si la pantalla no es de uno de
 * sus tipos. Eso es lo que se comprueba aquí, además de que cuando sí toca, sale con su versión.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Assets;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Handlers;
use PHPUnit\Framework\TestCase;

/**
 * Encolado de estilos y guiones.
 */
class AssetsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Assets.php';
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
		foreach ( array( 'convoca_courses_estilos', 'convoca_courses_guiones', 'convoca_courses_localizados', 'convoca_test_acciones' ) as $clave ) {
			$GLOBALS[ $clave ] = array();
		}

		$GLOBALS['convoca_courses_singular'] = '';
		$GLOBALS['convoca_test_posts']       = array();
		// El post global TAMBIÉN: `Assets::front()` pregunta `get_post()` sin argumentos, y dos casos de este mismo
		// fichero lo fijan con un contenido que lleva `[convoca_curso]`. Sin limpiarlo, el caso «una página
		// cualquiera no encola nada» heredaba el post del anterior y sí encolaba (courses#12).
		unset( $GLOBALS['convoca_courses_pantalla'], $GLOBALS['post'] );
	}

	/**
	 * Registra los dos enganches del módulo.
	 */
	public function test_registra_los_enganches(): void {
		Assets::register();

		$this->assertArrayHasKey( 'wp_enqueue_scripts', $GLOBALS['convoca_courses_acciones'] );
		$this->assertArrayHasKey( 'admin_enqueue_scripts', $GLOBALS['convoca_courses_acciones'] );
	}

	/**
	 * En una página cualquiera no se encola nada.
	 */
	public function test_en_una_pagina_cualquiera_no_sale_nada(): void {
		Assets::front();

		$this->assertSame( array(), $GLOBALS['convoca_courses_estilos'], 'sin curso ni shortcode no hay CSS' );
		$this->assertSame( array(), $GLOBALS['convoca_courses_guiones'], 'ni JS' );
		$this->assertSame( array(), $GLOBALS['convoca_courses_localizados'] );
	}

	/**
	 * En la ficha de un curso sale el CSS, el JS y sus datos.
	 */
	public function test_en_la_ficha_de_un_curso_sale_todo(): void {
		$GLOBALS['convoca_courses_singular'] = CPT_Curso::TYPE;

		Assets::front();

		$this->assertCount( 1, $GLOBALS['convoca_courses_estilos'] );
		$estilo = $GLOBALS['convoca_courses_estilos'][0];
		$this->assertSame( 'convoca-courses', $estilo['handle'] );
		$this->assertStringEndsWith( 'assets/css/courses.css', $estilo['src'] );
		$this->assertSame( CONVOCA_COURSES_VERSION, $estilo['ver'], 'la versión hace de cache-buster' );

		$this->assertCount( 1, $GLOBALS['convoca_courses_guiones'] );
		$guion = $GLOBALS['convoca_courses_guiones'][0];
		$this->assertSame( 'convoca-courses', $guion['handle'] );
		$this->assertStringEndsWith( 'assets/js/courses.js', $guion['src'] );
		$this->assertTrue( $guion['footer'], 'el guion va al pie' );

		$datos = $GLOBALS['convoca_courses_localizados']['convocaCourses'];
		$this->assertSame( 'convoca-courses', $datos['handle'] );
		$this->assertSame( Handlers::ACTION_VIDEO, $datos['datos']['action'] );
		$this->assertNotEmpty( $datos['datos']['nonce'], 'sin nonce el vídeo no se puede guardar' );
	}

	/**
	 * En una entrada con el shortcode del curso también sale.
	 */
	public function test_en_una_entrada_con_el_shortcode_tambien_sale(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- es la entrada de la plantilla, que en WordPress es un global.
		$GLOBALS['post'] = new \WP_Post(
			array(
				'ID'           => 7,
				'post_content' => 'Antes [convoca_curso id="3"] después',
			)
		);

		Assets::front();

		$this->assertCount( 1, $GLOBALS['convoca_courses_estilos'] );
	}

	/**
	 * Los otros dos shortcodes del módulo también cuentan.
	 */
	public function test_los_otros_shortcodes_cuentan(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- es la entrada de la plantilla, que en WordPress es un global.
		$GLOBALS['post'] = new \WP_Post(
			array(
				'ID'           => 7,
				'post_content' => '[convoca_curso_lecciones]',
			)
		);

		Assets::front();

		$this->assertCount( 1, $GLOBALS['convoca_courses_estilos'], 'las lecciones sueltas también necesitan el CSS' );
	}

	/**
	 * En el panel, sin pantalla conocida no se encola nada.
	 */
	public function test_en_el_panel_sin_pantalla_no_sale_nada(): void {
		Assets::admin( 'edit.php' );

		$this->assertSame( array(), $GLOBALS['convoca_courses_estilos'] );
	}

	/**
	 * En el panel, una pantalla de otro tipo tampoco.
	 */
	public function test_en_el_panel_otra_pantalla_no_sale_nada(): void {
		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => 'page' );

		Assets::admin( 'edit.php' );

		$this->assertSame( array(), $GLOBALS['convoca_courses_estilos'], 'el panel de páginas no lleva nuestro CSS' );
	}

	/**
	 * En las pantallas de los cuatro tipos del módulo sí.
	 *
	 * @param string $tipo Tipo de contenido.
	 * @dataProvider pantallas_del_modulo
	 */
	public function test_en_las_pantallas_del_modulo_sale_el_css( string $tipo ): void {
		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => $tipo );

		Assets::admin( 'edit.php' );

		$this->assertCount( 1, $GLOBALS['convoca_courses_estilos'] );
		$this->assertSame( 'convoca-courses-admin', $GLOBALS['convoca_courses_estilos'][0]['handle'] );
		$this->assertStringEndsWith( 'assets/css/admin.css', $GLOBALS['convoca_courses_estilos'][0]['src'] );
	}

	/**
	 * Los tipos del módulo que tienen pantalla propia.
	 *
	 * @return array<string, array{string}>
	 */
	public static function pantallas_del_modulo(): array {
		return array(
			'curso'     => array( CPT_Curso::TYPE ),
			'modulo'    => array( CPT_Modulo::TYPE ),
			'leccion'   => array( CPT_Leccion::TYPE ),
			'matricula' => array( 'convoca_matricula' ),
		);
	}
}
