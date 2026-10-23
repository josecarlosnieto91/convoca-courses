<?php
/**
 * Matrícula: su registro como tipo de contenido y las tres lecturas que todos consultan.
 *
 * `CPT_Matricula` estaba al 4,4 %. Lo que importa: el tipo se registra **sin URL propia y sin API**
 * (una matrícula no se toca desde fuera), vive dentro del menú de Cursos, y sus campos se limpian al
 * guardarse —el código del certificado se normaliza, el importe no puede ser negativo—.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use PHPUnit\Framework\TestCase;

/**
 * Registro y lecturas de la matrícula.
 */
class MatriculaRegistroTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_cc_cpt']           = array();
		$GLOBALS['_cc_post_meta']     = array();
		$GLOBALS['convoca_test_meta'] = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_cc_cpt']           = array();
		$GLOBALS['_cc_post_meta']     = array();
		$GLOBALS['convoca_test_meta'] = array();
	}

	/**
	 * El tipo se registra con lo que necesita: dentro de Cursos, sin URL y sin API.
	 */
	public function test_el_tipo_se_registra_como_toca(): void {
		CPT_Matricula::register();

		$args = $GLOBALS['_cc_cpt'][ CPT_Matricula::TYPE ];

		$this->assertFalse( $args['public'], 'una matrícula no es una página pública' );
		$this->assertTrue( $args['show_ui'], 'pero se gestiona desde el panel' );
		$this->assertSame( 'edit.php?post_type=' . CPT_Curso::TYPE, $args['show_in_menu'], 'vive dentro de Cursos' );
		$this->assertFalse( $args['show_in_rest'], 'no se toca por la API' );
		$this->assertFalse( $args['publicly_queryable'], 'ni tiene URL propia' );
		$this->assertSame( array( 'title' ), $args['supports'] );
		$this->assertSame( CPT_Base::CAP_EDIT_POST, $args['capabilities']['edit_post'], 'las de entrada van por su capacidad' );
		$this->assertSame( 'convoca_manage_courses', $args['capabilities']['edit_posts'] );
	}

	/**
	 * Los campos de la matrícula quedan registrados con prefijo.
	 */
	public function test_los_campos_quedan_registrados(): void {
		CPT_Matricula::register();

		$metas = $GLOBALS['_cc_post_meta'][ CPT_Matricula::TYPE ];

		foreach ( array( 'curso_id', 'user_id', 'estado', 'importe', 'moneda', 'cert_codigo' ) as $clave ) {
			$this->assertArrayHasKey( CPT_Base::META_PREFIX . $clave, $metas, 'la meta ' . $clave . ' se registra' );
		}
	}

	/**
	 * El código del certificado se normaliza al guardarse: sin separadores y en mayúsculas.
	 */
	public function test_el_codigo_del_certificado_se_normaliza(): void {
		CPT_Matricula::register();

		$saneador = $GLOBALS['_cc_post_meta'][ CPT_Matricula::TYPE ][ CPT_Base::META_PREFIX . 'cert_codigo' ]['sanitize_callback'];

		$this->assertSame( 'CC124ABCD1234', $saneador( 'cc-124-abcd 1234' ) );
	}

	/**
	 * El importe no admite negativos y la URL de pago se limpia.
	 */
	public function test_el_importe_y_la_url_de_pago_se_limpian(): void {
		CPT_Matricula::register();

		$metas = $GLOBALS['_cc_post_meta'][ CPT_Matricula::TYPE ];

		$importe = $metas[ CPT_Base::META_PREFIX . 'importe' ]['sanitize_callback'];
		$this->assertSame( 0.0, $importe( '-25' ), 'un importe negativo se queda en cero' );
		$this->assertSame( 30.0, $importe( '30' ) );

		$url = $metas[ CPT_Base::META_PREFIX . 'payment_url' ]['sanitize_callback'];
		$this->assertSame( 'https://pagos.example.test/x', $url( ' https://pagos.example.test/x ' ) );
	}

	/**
	 * Sin estado guardado, la matrícula está pendiente.
	 */
	public function test_sin_estado_esta_pendiente(): void {
		$this->assertSame( 'pendiente', CPT_Matricula::status( 44 ) );
	}

	/**
	 * Un estado guardado que existe se devuelve tal cual.
	 */
	public function test_un_estado_conocido_se_devuelve(): void {
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ] = 'completada';

		$this->assertSame( 'completada', CPT_Matricula::status( 44 ) );
	}

	/**
	 * Un estado que no existe se lee como pendiente (conservador con el aforo).
	 */
	public function test_un_estado_inventado_se_lee_como_pendiente(): void {
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ] = 'inventado';

		$this->assertSame( 'pendiente', CPT_Matricula::status( 44 ) );
	}

	/**
	 * El curso y la persona de la matrícula se leen como enteros.
	 */
	public function test_el_curso_y_la_persona_se_leen_como_enteros(): void {
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'curso_id' ] = '3';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'user_id' ]  = '9';

		$this->assertSame( 3, CPT_Matricula::course_id( 44 ) );
		$this->assertSame( 9, CPT_Matricula::user_id( 44 ) );
	}

	/**
	 * Sin datos, curso y persona valen cero.
	 */
	public function test_sin_datos_curso_y_persona_valen_cero(): void {
		$this->assertSame( 0, CPT_Matricula::course_id( 44 ) );
		$this->assertSame( 0, CPT_Matricula::user_id( 44 ) );
	}
}
