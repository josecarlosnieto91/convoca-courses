<?php
/**
 * Dobles de las funciones de WordPress que usan los assets: cola de estilos y guiones, pantalla
 * actual y los dos predicados de plantilla.
 *
 * Todo queda apuntado en globales para poder afirmar **qué se encola y qué no**, que es la mitad del
 * comportamiento: en una página que no es un curso ni lleva shortcode, el CSS no debe salir.
 *
 * @package Convoca\Courses\Tests
 */

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * ¿Es la página de un contenido suelto?
	 *
	 * @param string|array $tipos Tipos.
	 * @return bool
	 */
	function is_singular( $tipos = '' ) {
		$actual = (string) ( $GLOBALS['convoca_courses_singular'] ?? '' );

		if ( '' === $actual ) {
			return false;
		}

		return '' === $tipos || in_array( $actual, (array) $tipos, true );
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	/**
	 * ¿El contenido lleva ese shortcode?
	 *
	 * @param string $contenido Contenido.
	 * @param string $tag       Shortcode.
	 * @return bool
	 */
	function has_shortcode( $contenido, $tag ) {
		return (bool) preg_match( '/\[\/?' . preg_quote( (string) $tag, '/' ) . '(\s|\]|\/)/', (string) $contenido );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Encola un estilo.
	 *
	 * @param string $handle Identificador del recurso.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = '' ) {
		$GLOBALS['convoca_courses_estilos'][] = array(
			'handle' => $handle,
			'src'    => $src,
			'ver'    => $ver,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Encola un guion.
	 *
	 * @param string $handle Identificador del recurso.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @param bool   $footer Va al pie de la página, sí o no.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = '', $footer = false ) {
		$GLOBALS['convoca_courses_guiones'][] = array(
			'handle' => $handle,
			'src'    => $src,
			'ver'    => $ver,
			'footer' => $footer,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Pasa datos del servidor al guion.
	 *
	 * @param string $handle Identificador del recurso.
	 * @param string $nombre Nombre del objeto que verá el guion.
	 * @param array  $datos  Datos.
	 * @return void
	 */
	function wp_localize_script( $handle, $nombre, $datos = array() ) {
		$GLOBALS['convoca_courses_localizados'][ $nombre ] = array(
			'handle' => $handle,
			'datos'  => $datos,
		);
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * Pantalla del panel, si la hay.
	 *
	 * @return object|null
	 */
	function get_current_screen() {
		return $GLOBALS['convoca_courses_pantalla'] ?? null;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Engancha un filtro (doble que apunta quién se engancha y con cuántos argumentos).
	 *
	 * @param string   $hook     Gancho.
	 * @param callable $callback Callback.
	 * @param int      $priority Prioridad.
	 * @param int      $args     Argumentos.
	 * @return bool
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['convoca_courses_filtros'][ $hook ] = array(
			'callback' => $callback,
			'priority' => (int) $priority,
			'args'     => (int) $args,
		);

		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * ¿Estamos en el panel? (controlable desde `$GLOBALS['convoca_courses_admin']`).
	 *
	 * @return bool
	 */
	function is_admin() {
		return (bool) ( $GLOBALS['convoca_courses_admin'] ?? false );
	}
}
