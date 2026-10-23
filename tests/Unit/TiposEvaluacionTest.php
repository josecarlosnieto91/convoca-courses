<?php
/**
 * El registro de los tipos de contenido de exámenes y tareas.
 *
 * Los dos estaban al 0 % (121 y 75 statements). Aquí hay algo que hay que dejar por escrito con una
 * prueba: **se registran con `capability_type => 'post'`**, es decir, con los permisos de las
 * entradas normales del blog. Eso significa que cualquier autor puede crear y editar exámenes y
 * tareas, y que el tipo no tiene su propio nombre de capacidad.
 *
 * No se cambia desde una prueba —es una decisión de permisos, no de cobertura—, pero queda afirmado
 * para que el día que se corrija, esta prueba lo diga.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Tarea;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Tarea.php';

/**
 * Registro de los tipos de contenido de evaluación.
 */
final class TiposEvaluacionTest extends TestCase {

	/**
	 * Cada prueba parte de un registro vacío.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		$GLOBALS['_cc_cpt'] = array();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_cc_cpt'] );
	}

	/**
	 * El tipo de examen existe, no es público y soporta título.
	 *
	 * @return void
	 */
	public function test_el_tipo_de_examen_se_registra(): void {
		CPT_Examen::register();

		$args = $GLOBALS['_cc_cpt'][ CPT_Examen::TYPE ] ?? null;

		$this->assertNotNull( $args, 'sin registrar el tipo no hay exámenes' );
		$this->assertFalse( $args['public'], 'un examen no se publica en el sitio' );
		$this->assertContains( 'title', $args['supports'] );
	}

	/**
	 * Las preguntas y respuestas no pueden salir por la API pública.
	 *
	 * @return void
	 */
	public function test_el_examen_no_expone_su_contenido_por_la_api(): void {
		CPT_Examen::register();

		$args = $GLOBALS['_cc_cpt'][ CPT_Examen::TYPE ];

		$this->assertFalse( $args['show_in_rest'] ?? false, 'las preguntas y respuestas no salen por la API pública' );
	}

	/**
	 * Deja afirmado el `capability_type` actual (ver la incidencia abierta en el repo).
	 *
	 * @return void
	 */
	public function test_hoy_el_examen_usa_las_capacidades_de_las_entradas(): void {
		CPT_Examen::register();

		// Documentado, no deseado: con «post», manda el permiso de las entradas del blog.
		$this->assertSame( 'post', $GLOBALS['_cc_cpt'][ CPT_Examen::TYPE ]['capability_type'] );
	}

	/**
	 * El tipo de tarea existe y no se publica en el sitio.
	 *
	 * @return void
	 */
	public function test_el_tipo_de_tarea_se_registra(): void {
		CPT_Tarea::register();

		$args = $GLOBALS['_cc_cpt'][ CPT_Tarea::TYPE ] ?? null;

		$this->assertNotNull( $args );
		$this->assertFalse( $args['public'], 'una tarea no se publica' );
	}

	/**
	 * Mismo caso que el examen: permisos de las entradas normales.
	 *
	 * @return void
	 */
	public function test_hoy_la_tarea_tambien_usa_las_capacidades_de_las_entradas(): void {
		CPT_Tarea::register();

		$this->assertSame( 'post', $GLOBALS['_cc_cpt'][ CPT_Tarea::TYPE ]['capability_type'] );
	}
}
