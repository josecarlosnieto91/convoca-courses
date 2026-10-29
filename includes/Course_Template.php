<?php
/**
 * Plantilla propia de la ficha del curso.
 *
 * Por defecto **no se activa**: manda el tema, que es lo que ya funciona en los sitios en producción. Cuando un
 * tema pinta el curso como una entrada cualquiera —sin matrícula, sin programa y sin botón de empezar—, se
 * enciende desde Ajustes y el plugin usa su plantilla.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carga de la plantilla del curso.
 */
class Course_Template {

	/**
	 * Opción que la activa.
	 */
	public const OPTION = 'convoca_courses_plantilla_propia';

	/**
	 * Ficheros que un tema puede usar para sustituirla.
	 */
	public const OVERRIDES = array( 'convoca-curso.php', 'single-convoca_curso.php' );

	/**
	 * Engancha el filtro.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
	}

	/**
	 * ¿Está activada en este sitio?
	 *
	 * @return bool
	 */
	public static function activa(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * Sustituye la plantilla del curso cuando corresponde.
	 *
	 * @param string $template Plantilla que ha elegido WordPress.
	 * @return string
	 */
	public static function template( string $template ): string {
		if ( ! self::activa() || ! is_singular( CPT_Curso::TYPE ) ) {
			return $template;
		}

		$tema = locate_template( self::OVERRIDES );
		if ( '' !== $tema ) {
			return $tema;
		}

		$propia = dirname( __DIR__ ) . '/templates/curso-completo.php';

		return is_readable( $propia ) ? $propia : $template;
	}
}
