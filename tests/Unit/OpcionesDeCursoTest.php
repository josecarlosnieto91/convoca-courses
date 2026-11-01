<?php
/**
 * El desplegable de cursos sale de un solo sitio.
 *
 * Vivía duplicado —mismo cuerpo y misma consulta— en `class-admin-fields.php` y
 * `class-admin-examen.php`. Dos copias del mismo listado se desincronizan a la primera: un
 * cambio en una pantalla y no en la otra, y el desplegable de exámenes enseña cursos que el de
 * módulos ya no ofrece.
 *
 * Se comprueban las dos mitades: que `CPT_Curso::opciones()` devuelve el marcador y los cursos,
 * y que ninguna pantalla vuelve a tener su propia copia.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Curso;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';

/**
 * Opciones del desplegable de cursos.
 */
final class OpcionesDeCursoTest extends TestCase {

	/**
	 * Limpia el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_post_types'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_post_types'] = array();
	}

	/**
	 * El primer valor es el «selecciona», con identificador 0, para que nadie guarde un 0 por error.
	 */
	public function test_empieza_por_el_marcador(): void {
		$opciones = CPT_Curso::opciones();

		$this->assertArrayHasKey( 0, $opciones );
		$this->assertSame( '— Selecciona un curso —', $opciones[0] );
	}

	/**
	 * Y detrás van los cursos, por identificador y con su título.
	 */
	public function test_lleva_los_cursos_con_su_titulo(): void {
		$GLOBALS['convoca_test_post_types'][501] = CPT_Curso::TYPE;

		$opciones = CPT_Curso::opciones();

		$this->assertArrayHasKey( 501, $opciones );
	}

	/**
	 * Ninguna pantalla de administración vuelve a tener su copia.
	 */
	public function test_las_pantallas_no_tienen_su_propia_copia(): void {
		$raiz = dirname( __DIR__, 2 );

		foreach ( array( 'admin/class-admin-fields.php', 'admin/class-admin-examen.php' ) as $fichero ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo.
			$codigo = (string) file_get_contents( $raiz . '/' . $fichero );

			$this->assertStringNotContainsString(
				'function courses_options',
				$codigo,
				$fichero . ' ha vuelto a definir su propia lista de cursos'
			);
			$this->assertStringContainsString(
				'CPT_Curso::opciones()',
				$codigo,
				$fichero . ' debe usar la lista compartida'
			);
		}
	}
}
