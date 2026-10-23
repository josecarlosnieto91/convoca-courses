<?php
/**
 * Arranque del módulo y limpieza al borrar contenido.
 *
 * `Plugin` es el punto único de enganche: si falta un enganche, una parte del módulo deja de
 * funcionar en silencio. Aquí se comprueba que se engancha todo lo que debe, que las pantallas de
 * administración solo se cargan en el panel, y que borrar un contenido se lleva sus datos dependientes
 * —progreso, preguntas, intentos y entregas— sin tocar los de los demás.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\CPT_Tarea;
use Convoca\Courses\Installer;
use Convoca\Courses\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Arranque y limpieza.
 */
class PluginArranqueTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$base = dirname( __DIR__, 2 ) . '/includes/';

		// Módulos del plugin: `includes/<Clase>.php`.
		foreach ( array( 'Installer', 'CPT_Base', 'CPT_Curso', 'CPT_Modulo', 'CPT_Leccion', 'CPT_Matricula', 'CPT_Examen', 'CPT_Tarea', 'Hierarchy', 'Progress', 'Enrollment', 'Access', 'Questions', 'Quiz', 'Attempts', 'Roster', 'Notifications', 'Gradebook', 'Cohorts', 'Certificate_QR', 'Certificate', 'Verification', 'Shortcodes', 'Handlers', 'Assets', 'Reminders', 'Settings', 'License', 'Pro', 'Pricing', 'Course_Payment', 'Task', 'Plugin' ) as $fichero ) {
			$ruta = $base . $fichero . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}

		// Pantallas del panel: `admin/class-<nombre-con-guiones>.php` (no siguen PSR-4).
		foreach ( array( 'Admin_Fields', 'Admin_Columns', 'Admin_Examen', 'Admin_Entregas' ) as $pantalla ) {
			$ruta = dirname( __DIR__, 2 ) . '/admin/class-' . strtolower( str_replace( '_', '-', $pantalla ) ) . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
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
		foreach ( array( 'convoca_courses_acciones', 'convoca_courses_enganches', 'convoca_courses_filtros', 'convoca_test_acciones', 'convoca_test_post_types', 'convoca_test_meta', '_cc_cpt' ) as $clave ) {
			$GLOBALS[ $clave ] = array();
		}

		$GLOBALS['wpdb']->borrados        = array();
		$GLOBALS['convoca_courses_admin'] = false;

		// El arranque se prueba con el esquema y las capacidades al día: migrar aquí solo estorbaría.
		$GLOBALS['convoca_courses_opciones'][ Installer::DB_OPTION ]   = CONVOCA_COURSES_DB_VERSION;
		$GLOBALS['convoca_courses_opciones'][ Installer::CAPS_OPTION ] = CONVOCA_COURSES_VERSION;
	}

	/**
	 * La instancia es única.
	 */
	public function test_la_instancia_es_unica(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	/**
	 * El arranque deja enganchado lo que el módulo necesita, en el panel y fuera de él.
	 */
	public function test_el_arranque_engancha_lo_del_modulo(): void {
		Plugin::instance()->boot();

		$enganches = $GLOBALS['convoca_courses_acciones'];

		foreach ( array( 'init', 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'template_redirect', 'before_delete_post', 'convoca_gateway_payment_completed', 'convoca_gateway_payment_failed' ) as $gancho ) {
			$this->assertArrayHasKey( $gancho, $enganches, 'falta el enganche ' . $gancho );
		}

		$this->assert_enganchado( 'init', array( Plugin::instance(), 'register_types' ), 'el registro de tipos va en init' );
		$this->assertArrayHasKey( 'map_meta_cap', $GLOBALS['convoca_courses_filtros'], 'las capacidades por entrada necesitan su puente' );
		$this->assertArrayHasKey( 'query_vars', $GLOBALS['convoca_courses_filtros'], 'la verificación del certificado lee su variable' );
	}

	/**
	 * Fuera del panel no se carga la pantalla de ajustes del módulo.
	 */
	public function test_fuera_del_panel_no_se_carga_la_pantalla_de_ajustes(): void {
		Plugin::instance()->boot();

		$this->assertArrayNotHasKey( 'admin_init', $GLOBALS['convoca_courses_acciones'], 'sin panel no hay ajustes que registrar' );
		$this->assertArrayHasKey( 'add_meta_boxes', $GLOBALS['convoca_courses_acciones'], 'las cajas de edición sí: son parte del tipo de contenido' );
	}

	/**
	 * Dentro del panel sí: los ajustes se registran.
	 */
	public function test_dentro_del_panel_se_cargan_los_ajustes(): void {
		$GLOBALS['convoca_courses_admin'] = true;

		Plugin::instance()->boot();

		$this->assertArrayHasKey( 'admin_init', $GLOBALS['convoca_courses_acciones'], 'en el panel los ajustes se registran' );
		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['convoca_courses_acciones'], 'y su página aparece en el menú' );
	}

	/**
	 * Registra los siete tipos de contenido del módulo.
	 */
	public function test_registra_los_tipos_de_contenido(): void {
		Plugin::instance()->register_types();

		$tipos = array_keys( $GLOBALS['_cc_cpt'] ?? array() );

		foreach ( array( CPT_Curso::TYPE, CPT_Modulo::TYPE, CPT_Leccion::TYPE, CPT_Examen::TYPE, CPT_Tarea::TYPE, 'convoca_matricula' ) as $tipo ) {
			$this->assertContains( $tipo, $tipos, 'no se registra ' . $tipo );
		}
	}

	/**
	 * Borrar una lección se lleva su progreso y nada más.
	 */
	public function test_borrar_una_leccion_se_lleva_su_progreso(): void {
		$this->con_tipo( 11, CPT_Leccion::TYPE );

		Plugin::instance()->cleanup_on_delete( 11 );

		$this->assert_count_borrados( 1, 'progress', 'el progreso de la lección se borra' );
	}

	/**
	 * Borrar un curso se lleva su progreso.
	 */
	public function test_borrar_un_curso_se_lleva_su_progreso(): void {
		$this->con_tipo( 12, CPT_Curso::TYPE );

		Plugin::instance()->cleanup_on_delete( 12 );

		$this->assert_count_borrados( 1, 'progress', 'el progreso del curso se borra' );
	}

	/**
	 * Borrar un examen se lleva preguntas e intentos: las dos tablas.
	 */
	public function test_borrar_un_examen_se_lleva_preguntas_e_intentos(): void {
		$this->con_tipo( 13, CPT_Examen::TYPE );

		Plugin::instance()->cleanup_on_delete( 13 );

		$tablas = array();
		foreach ( $GLOBALS['wpdb']->borrados as $borrado ) {
			$tablas[] = (string) $borrado[0];
		}

		$texto = implode( ' ', $tablas );
		$this->assertStringContainsString( 'questions', $texto, 'las preguntas del examen se borran' );
		$this->assertStringContainsString( 'attempts', $texto, 'y los intentos' );
	}

	/**
	 * Borrar una tarea se lleva sus entregas.
	 */
	public function test_borrar_una_tarea_se_lleva_sus_entregas(): void {
		$this->con_tipo( 14, CPT_Tarea::TYPE );

		Plugin::instance()->cleanup_on_delete( 14 );

		$this->assert_count_borrados( 1, 'submissions', 'las entregas de la tarea se borran' );
	}

	/**
	 * Borrar cualquier otra cosa no toca las tablas del módulo.
	 */
	public function test_borrar_otra_cosa_no_toca_nada(): void {
		$this->con_tipo( 15, 'post' );

		Plugin::instance()->cleanup_on_delete( 15 );

		$this->assertSame( array(), $GLOBALS['wpdb']->borrados, 'una entrada normal no tiene datos nuestros' );
	}

	/**
	 * Afirma que un callback concreto está enganchado a un gancho.
	 *
	 * Se mira la lista completa y no `$GLOBALS['convoca_courses_acciones'][ $gancho ]`: un mismo gancho
	 * tiene varios callbacks y el doble se queda con el último, así que quitar uno de en medio no se
	 * notaría.
	 *
	 * @param string   $gancho   Gancho.
	 * @param callable $callback Callback esperado.
	 * @param string   $mensaje  Mensaje del fallo.
	 */
	private function assert_enganchado( string $gancho, callable $callback, string $mensaje ): void {
		foreach ( $GLOBALS['convoca_courses_enganches'] as $entrada ) {
			if ( $gancho === $entrada['gancho'] && $callback === $entrada['callback'] ) {
				$this->assertTrue( true, $mensaje );
				return;
			}
		}

		$this->fail( $mensaje );
	}

	/**
	 * Deja un contenido con su tipo.
	 *
	 * @param int    $id   Contenido.
	 * @param string $tipo Tipo.
	 */
	private function con_tipo( int $id, string $tipo ): void {
		$GLOBALS['convoca_test_post_types'][ $id ] = $tipo;
	}

	/**
	 * Afirma que se borró en la tabla indicada.
	 *
	 * @param int    $esperados Cuántos borrados se esperan.
	 * @param string $tabla     Trozo del nombre de la tabla.
	 * @param string $mensaje   Mensaje del fallo.
	 */
	private function assert_count_borrados( int $esperados, string $tabla, string $mensaje ): void {
		$cuenta = 0;
		foreach ( $GLOBALS['wpdb']->borrados as $borrado ) {
			if ( str_contains( (string) $borrado[0], $tabla ) ) {
				++$cuenta;
			}
		}

		$this->assertSame( $esperados, $cuenta, $mensaje );
	}
}
