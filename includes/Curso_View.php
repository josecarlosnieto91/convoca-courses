<?php
/**
 * Ficha del curso compuesta desde sus relaciones.
 *
 * El curso no lleva la estructura escrita en el contenido: los módulos, las lecciones, las tareas y el
 * examen ya están relacionados en la base de datos. Esta clase los junta en el orden que se ve en la
 * ficha, y la plantilla solo pinta.
 *
 * Se expone de dos formas: la plantilla propia del curso (`Course_Template`) y el shortcode
 * `[convoca_curso_completo]`, para poder reutilizarla en una landing o en un patrón sin duplicar la
 * composición.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composición de la ficha del curso.
 */
class Curso_View {

	/**
	 * Devuelve el HTML completo de la ficha de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	public static function render( int $course_id ): string {
		if ( $course_id <= 0 || ! Hierarchy::is_course( $course_id ) ) {
			return '<p class="convoca-courses__error">' . esc_html__( 'Este curso no existe.', 'convoca-courses' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$modules = Shortcodes::modules_data( $course_id, $user_id );
		$exams   = CPT_Examen::of_course( $course_id );
		$tasks   = CPT_Tarea::of_course( $course_id );

		$exams_por_modulo = array();
		$exams_sueltos    = array();
		foreach ( $exams as $exam ) {
			$exam_id = (int) $exam->ID;
			$module  = CPT_Examen::module_id( $exam_id );
			if ( $module > 0 ) {
				$exams_por_modulo[ $module ][] = $exam_id;
			} else {
				$exams_sueltos[] = $exam_id;
			}
		}

		return self::portada( $course_id )
			. self::programa( $course_id, $modules, $exams_por_modulo )
			. self::tareas( $course_id, $tasks )
			. self::evaluacion( $exams_sueltos );
	}

	/**
	 * Cabecera y matrícula: la lógica de siempre, sin reescribirla.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	private static function portada( int $course_id ): string {
		return (string) do_shortcode( '[convoca_curso id="' . (int) $course_id . '"]' );
	}

	/**
	 * Programa: acordeón de módulos con sus lecciones y los exámenes de cada módulo.
	 *
	 * @param int                            $course_id        Curso.
	 * @param array<int,array<string,mixed>> $modules    Módulos con sus lecciones.
	 * @param array<int,array<int,int>>      $exams_por_modulo Exámenes indexados por módulo.
	 * @return string
	 */
	private static function programa( int $course_id, array $modules, array $exams_por_modulo ): string {
		if ( empty( $modules ) ) {
			return '<p class="convoca-courses__empty">' . esc_html__( 'Este curso todavía no tiene lecciones publicadas.', 'convoca-courses' ) . '</p>';
		}

		return Certificate::render_template(
			'lecciones-acordeon.php',
			array(
				'course_id'        => $course_id,
				'modules'          => $modules,
				'progress'         => Access::summary( get_current_user_id(), $course_id ),
				'enrolled'         => Access::is_enrolled( get_current_user_id(), $course_id ),
				'abierto'          => self::modulo_abierto( $modules ),
				'exams_por_modulo' => $exams_por_modulo,
			),
			false
		);
	}

	/**
	 * Qué módulo se abre al cargar: el de la primera lección sin completar; si no hay, el primero.
	 *
	 * @param array<int,array<string,mixed>> $modules Módulos.
	 * @return int Id del módulo, 0 si no hay.
	 */
	private static function modulo_abierto( array $modules ): int {
		foreach ( $modules as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( empty( $lesson['completed'] ) ) {
					return (int) $module['id'];
				}
			}
		}

		return isset( $modules[0]['id'] ) ? (int) $modules[0]['id'] : 0;
	}

	/**
	 * Actividades de práctica, si el curso tiene tareas.
	 *
	 * @param int                 $course_id Curso.
	 * @param array<int,\WP_Post> $tasks     Tareas del curso.
	 * @return string
	 */
	private static function tareas( int $course_id, array $tasks ): string {
		if ( empty( $tasks ) ) {
			return '';
		}

		return '<h2 class="convoca-courses__subtitle">' . esc_html__( 'Actividades de práctica', 'convoca-courses' ) . '</h2>'
			. (string) do_shortcode( '[convoca_curso_tareas id="' . (int) $course_id . '"]' );
	}

	/**
	 * Evaluación final: los exámenes que no cuelgan de un módulo.
	 *
	 * @param array<int,int> $exams Exámenes del curso sin módulo.
	 * @return string
	 */
	private static function evaluacion( array $exams ): string {
		if ( empty( $exams ) ) {
			return '';
		}

		$html = '<h2 class="convoca-courses__subtitle">' . esc_html__( 'Evaluación final', 'convoca-courses' ) . '</h2>';

		foreach ( $exams as $exam_id ) {
			$html .= (string) do_shortcode( '[convoca_curso_examen id="' . (int) $exam_id . '"]' );
		}

		return $html;
	}
}
