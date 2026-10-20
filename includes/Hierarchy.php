<?php
/**
 * Jerarquía curso → módulo → lección.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validación y recorrido de la jerarquía del curso.
 */
class Hierarchy {

	/**
	 * Comprueba si un módulo colgaría de un curso válido.
	 *
	 * @param int $module_id Módulo.
	 * @param int $course_id Curso candidato.
	 * @return bool
	 */
	public static function valid_module_course( int $module_id, int $course_id ): bool {
		unset( $module_id );
		return self::is_course( $course_id );
	}

	/**
	 * Comprueba si una lección colgaría de un módulo válido.
	 *
	 * @param int $lesson_id Lección.
	 * @param int $module_id Módulo candidato.
	 * @return bool
	 */
	public static function valid_lesson_module( int $lesson_id, int $module_id ): bool {
		unset( $lesson_id );
		return self::is_module( $module_id );
	}

	/**
	 * ¿Es un curso publicado o en borrador del tipo correcto?
	 *
	 * @param int $post_id Identificador.
	 * @return bool
	 */
	public static function is_course( int $post_id ): bool {
		return self::is_type( $post_id, CPT_Curso::TYPE );
	}

	/**
	 * ¿Es un módulo del tipo correcto?
	 *
	 * @param int $post_id Identificador.
	 * @return bool
	 */
	public static function is_module( int $post_id ): bool {
		return self::is_type( $post_id, CPT_Modulo::TYPE );
	}

	/**
	 * ¿Es una lección del tipo correcto?
	 *
	 * @param int $post_id Identificador.
	 * @return bool
	 */
	public static function is_lesson( int $post_id ): bool {
		return self::is_type( $post_id, CPT_Leccion::TYPE );
	}

	/**
	 * Comprobación base de tipo.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $type    Tipo esperado.
	 * @return bool
	 */
	public static function is_type( int $post_id, string $type ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === $type;
	}

	/**
	 * Curso al que pertenece una lección, subiendo por el módulo.
	 *
	 * @param int $lesson_id Lección.
	 * @return int 0 si la cadena está rota.
	 */
	public static function course_of_lesson( int $lesson_id ): int {
		$module_id = CPT_Leccion::module_id( $lesson_id );
		if ( 0 === $module_id ) {
			return 0;
		}
		return CPT_Modulo::course_id( $module_id );
	}

	/**
	 * Curso al que pertenece un módulo.
	 *
	 * @param int $module_id Módulo.
	 * @return int
	 */
	public static function course_of_module( int $module_id ): int {
		return CPT_Modulo::course_id( $module_id );
	}

	/**
	 * Errores de jerarquía de una lección, listos para el aviso del editor.
	 *
	 * @param int $lesson_id Lección.
	 * @return array<int, string>
	 */
	public static function lesson_errors( int $lesson_id ): array {
		$errors = array();

		$module_id = CPT_Leccion::module_id( $lesson_id );
		if ( 0 === $module_id ) {
			$errors[] = __( 'La lección no cuelga de ningún módulo.', 'convoca-courses' );
			return $errors;
		}

		if ( ! self::is_module( $module_id ) ) {
			$errors[] = __( 'El módulo de la lección no existe.', 'convoca-courses' );
			return $errors;
		}

		if ( 0 === CPT_Modulo::course_id( $module_id ) ) {
			$errors[] = __( 'El módulo de la lección no cuelga de ningún curso.', 'convoca-courses' );
		}

		return $errors;
	}
}
