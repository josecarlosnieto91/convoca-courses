<?php
/**
 * El registro de los tipos de contenido de exámenes y tareas.
 *
 * Los dos estaban al 0 % (121 y 75 statements).
 *
 * **Corrección de lo que decía este fichero.** Se escribió afirmando que, como el registro lleva
 * `capability_type => 'post'`, los exámenes y las tareas usaban los permisos de las entradas del blog y
 * cualquier autor podía editarlos. **Es falso.** WordPress solo usa `capability_type` para *generar* las
 * capacidades cuando no le das `capabilities`; en cuanto el array `capabilities` va explícito —y va—, manda
 * él. Medido: con `CPT_Examen::register()`, las capacidades son `convoca_edit_curso`, `convoca_read_curso`,
 * `convoca_delete_curso` y `convoca_manage_courses` para el resto. Ninguna es la de las entradas.
 *
 * El `capability_type => 'post'` sobra y engaña, pero no cambia nada. Se deja porque quitarlo es un cambio
 * de producto sin efecto funcional, y lo que sí se hace es **afirmar lo que de verdad ocurre**.
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
	 * **El examen NO usa las capacidades de las entradas del blog.**
	 *
	 * Es lo que hay que dejar clavado: `capabilities` explícito manda sobre `capability_type`, así que quien
	 * pueda `edit_posts` en el blog —cualquier autor— **no** puede tocar un examen. Editar un examen es poder
	 * cambiar las respuestas correctas, o sea, lo que se evalúa.
	 */
	public function test_el_examen_usa_capacidades_propias_y_no_las_de_las_entradas(): void {
		CPT_Examen::register();

		$caps = $GLOBALS['_cc_cpt'][ CPT_Examen::TYPE ]['capabilities'] ?? array();

		$this->assertNotEmpty( $caps, 'el registro trae su mapa de capacidades' );
		$this->assertTrue( (bool) ( $GLOBALS['_cc_cpt'][ CPT_Examen::TYPE ]['map_meta_cap'] ?? false ) );

		// Las tres primitivas por entrada, con nombre propio del módulo.
		$this->assertSame( 'convoca_edit_curso', $caps['edit_post'] ?? null );
		$this->assertSame( 'convoca_read_curso', $caps['read_post'] ?? null );
		$this->assertSame( 'convoca_delete_curso', $caps['delete_post'] ?? null );

		// Y las globales, a la puerta del módulo: NO a `edit_posts` del blog.
		foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts' ) as $capacidad ) {
			$this->assertSame( 'convoca_manage_courses', $caps[ $capacidad ] ?? null, $capacidad . ' no puede ser la del blog' );
			$this->assertNotSame( $capacidad, $caps[ $capacidad ] ?? null, 'ni su propio nombre' );
		}
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
