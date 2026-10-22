<?php
/**
 * Pruebas de que las plantillas no usan clases del módulo sin cualificar.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Las plantillas se incluyen desde el módulo, pero **no heredan su espacio de nombres**: un
 * `Handlers::ACTION_X` dentro de `templates/` busca `\Handlers` y revienta la página con un fatal
 * («Class "Handlers" not found») que ninguna prueba de unidad veía y PHPStan no mira (las plantillas
 * están fuera de su análisis).
 *
 * Medido en la demo el 22-10-2026: la página del curso se cortaba a la mitad y detrás de los tres
 * primeros shortcodes no se pintaba nada. Esta prueba es la puerta que lo impide volver.
 *
 * @coversNothing
 */
final class TemplatesNamespacedTest extends TestCase {

	/**
	 * Clases del módulo que una plantilla puede necesitar.
	 */
	private const CLASES = array(
		'Handlers',
		'Questions',
		'Quiz',
		'Attempts',
		'Exam_View',
		'Task',
		'Task_View',
		'Gradebook',
		'Gradebook_View',
		'Roster',
		'Access',
		'Enrollment',
		'Progress',
		'Certificate',
		'Certificate_QR',
		'Pricing',
		'Pro',
		'Cohorts',
		'CPT_Curso',
		'CPT_Modulo',
		'CPT_Leccion',
		'CPT_Examen',
		'CPT_Tarea',
		'CPT_Matricula',
		'Installer',
		'Hierarchy',
	);

	/**
	 * Ninguna plantilla llama a una clase del módulo sin la barra inicial.
	 *
	 * @return void
	 */
	public function test_las_plantillas_cualifican_las_clases_del_modulo(): void {
		$fallos = array();

		foreach ( glob( CONVOCA_COURSES_DIR . 'templates/*.php' ) as $fichero ) {
			$contenido = (string) file_get_contents( $fichero ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura local en una prueba.

			foreach ( self::CLASES as $clase ) {
				// Busca `Clase::` que NO venga precedido de `\` (la forma cualificada correcta).
				if ( preg_match( '/(?<!\\\\)\b' . preg_quote( $clase, '/' ) . '::/', $contenido ) === 1 ) {
					$fallos[] = basename( $fichero ) . ' → ' . $clase;
				}
			}
		}

		$this->assertSame( array(), $fallos, 'Plantillas con clases sin cualificar: ' . implode( ', ', $fallos ) );
	}
}
