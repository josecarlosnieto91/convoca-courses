<?php
/**
 * Ajustes del plugin: la pantalla, el registro de opciones y el saneado del vídeo.
 *
 * `Settings` estaba al 0 % (62 statements). Lo que de verdad se juega aquí es el saneado: lo que llega
 * del formulario se guarda tal cual o se limpia, y la clave secreta **no se reenvía** —si el campo va
 * vacío, se conserva la guardada en vez de borrarla—.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\Installer;
use Convoca\Courses\Settings;
use Convoca\Courses\Video;
use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de la pantalla de ajustes.
 */
class SettingsAjustesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Leccion.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Video.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate_QR.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Settings.php';
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
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_caps']        = array();
		$GLOBALS['convoca_courses_ajustes']  = array();
		$GLOBALS['convoca_courses_submenus'] = array();
		$GLOBALS['convoca_courses_acciones'] = array();
	}

	/**
	 * Engancha la pantalla y el registro de opciones.
	 */
	public function test_engancha_la_pantalla_y_el_registro(): void {
		Settings::register();

		$ganchos = $GLOBALS['convoca_courses_acciones'];

		$this->assertArrayHasKey( 'admin_menu', $ganchos );
		$this->assertArrayHasKey( 'admin_init', $ganchos );
	}

	/**
	 * La pantalla vive bajo el menú de cursos y pide la capacidad del módulo.
	 */
	public function test_la_pantalla_vive_bajo_cursos(): void {
		Settings::add_page();

		$pagina = $GLOBALS['convoca_courses_submenus'][ Settings::PAGE ];

		$this->assertSame( 'edit.php?post_type=' . CPT_Curso::TYPE, $pagina['padre'] );
		$this->assertSame( 'convoca_manage_courses', $pagina['cap'], 'no se entra sin la capacidad del módulo' );
		$this->assertSame( array( Settings::class, 'render' ), $pagina['render'] );
	}

	/**
	 * Las dos opciones quedan registradas con su saneado.
	 */
	public function test_las_dos_opciones_se_registran_con_su_saneado(): void {
		Settings::register_settings();

		$ajustes = $GLOBALS['convoca_courses_ajustes'][ Settings::GROUP ];

		$this->assertArrayHasKey( Video::OPTION, $ajustes );
		$this->assertSame( array( Settings::class, 'sanitize_video' ), $ajustes[ Video::OPTION ]['sanitize_callback'] );
		$this->assertSame( 'array', $ajustes[ Video::OPTION ]['type'] );

		$this->assertArrayHasKey( Installer::KEEP_DATA_OPTION, $ajustes );
		$this->assertSame( 'boolean', $ajustes[ Installer::KEEP_DATA_OPTION ]['type'] );

		$saneador = $ajustes[ Installer::KEEP_DATA_OPTION ]['sanitize_callback'];
		$this->assertTrue( $saneador( '1' ), 'la casilla de conservar datos se convierte en booleano' );
		$this->assertFalse( $saneador( '' ) );
	}

	/**
	 * Sin entrada, todo queda vacío y la región vale `auto`.
	 */
	public function test_sin_entrada_queda_vacio(): void {
		$config = Settings::sanitize_video( 'no es una lista' );

		// Un objeto tampoco vale: sin la guarda, sus propiedades entrarían como ajustes.
		$desde_objeto = Settings::sanitize_video( (object) array( 'bucket' => 'colado' ) );
		$this->assertSame( '', $desde_objeto['bucket'], 'solo se acepta una lista de verdad' );

		$this->assertSame( '', $config['endpoint'] );
		$this->assertSame( '', $config['bucket'] );
		$this->assertSame( 'auto', $config['region'], 'sin región se usa auto' );
		$this->assertSame( '', $config['key'] );
		$this->assertSame( '', $config['secret'] );
		$this->assertSame( '', $config['path_prefix'] );
		$this->assertFalse( $config['path_style'], 'sin marcar, no se usa path style' );
	}

	/**
	 * Lo que llega se limpia y la clave se guarda cifrada.
	 */
	public function test_lo_que_llega_se_limpia_y_la_clave_se_cifra(): void {
		$config = Settings::sanitize_video(
			array(
				'endpoint'    => '  https://s3.eu-west-1.amazonaws.com  ',
				'bucket'      => '  mi-bucket  ',
				'region'      => 'eu-west-1',
				'key'         => '  AKIA  ',
				'secret'      => '  clave-secreta  ',
				'path_prefix' => '  videos/  ',
				'path_style'  => '1',
			)
		);

		$this->assertSame( 'https://s3.eu-west-1.amazonaws.com', $config['endpoint'], 'la URL va sin espacios' );
		$this->assertSame( 'mi-bucket', $config['bucket'] );
		$this->assertSame( 'eu-west-1', $config['region'] );
		$this->assertSame( 'AKIA', $config['key'] );
		$this->assertSame( 'videos/', $config['path_prefix'] );
		$this->assertTrue( $config['path_style'] );
		$this->assertStringStartsWith( 'enc:', $config['secret'], 'la clave no se guarda en claro' );
		$this->assertStringNotContainsString( 'clave-secreta', $config['secret'] );
	}

	/**
	 * El campo de la clave vacío conserva la que ya estaba: no se borra por reenviar el formulario.
	 */
	public function test_el_campo_vacio_conserva_la_clave_guardada(): void {
		$guardada = Video::encrypt( 'la-de-antes' );
		$GLOBALS['convoca_courses_opciones'][ Video::OPTION ] = array( 'secret' => $guardada );

		$config = Settings::sanitize_video( array( 'bucket' => 'otro' ) );

		$this->assertSame( 'otro', $config['bucket'] );
		$this->assertSame( $guardada, $config['secret'], 'sin clave nueva se queda la de antes, cifrada igual' );
	}

	/**
	 * La pantalla no se pinta sin permiso: corta con el aviso y no revienta.
	 */
	public function test_sin_permiso_la_pantalla_corta(): void {
		$GLOBALS['convoca_test_caps']['convoca_manage_courses'] = false;

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		Settings::render();
	}

	/**
	 * Con permiso, la pantalla se pinta con la configuración y el estado del PRO.
	 */
	public function test_con_permiso_la_pantalla_se_pinta(): void {
		$GLOBALS['convoca_test_caps']['convoca_manage_courses']             = true;
		$GLOBALS['convoca_courses_opciones'][ Installer::KEEP_DATA_OPTION ] = true;

		ob_start();
		Settings::render();
		$html = (string) ob_get_clean();

		$this->assertNotSame( '', $html, 'la plantilla de ajustes se pinta' );
		$this->assertStringContainsString( 'action="options.php"', $html, 'el formulario va al guardado de WordPress' );
		$this->assertStringContainsString( Settings::GROUP, $html, 'y lleva su grupo de ajustes' );
	}
}
