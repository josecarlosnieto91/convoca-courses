<?php
/**
 * Dobles de WordPress que faltaban para los shortcodes del curso.
 *
 * `Shortcodes` monta cada bloque con `Certificate::render_template()` y resuelve el curso con
 * `Hierarchy`. Sin estos dobles no se puede ni invocar un shortcode en una prueba.
 *
 * @package Convoca\Courses\Tests
 */

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Registra un shortcode (doble que apunta el alta).
	 *
	 * @param string   $tag      Nombre.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['convoca_courses_shortcodes'][ $tag ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Une atributos con sus valores por defecto (doble fiel).
	 *
	 * @param array        $pares    Defectos.
	 * @param array|string $atts     Atributos recibidos.
	 * @param string       $shortcode Nombre (se apunta: el doble no filtra, pero deja rastro).
	 * @return array
	 */
	function shortcode_atts( $pares, $atts, $shortcode = '' ) {
		$atts                                        = (array) $atts;
		$GLOBALS['convoca_courses_shortcode_atts'][] = $shortcode;
		$salida                                      = array();
		foreach ( $pares as $nombre => $defecto ) {
			$salida[ $nombre ] = array_key_exists( $nombre, $atts ) ? $atts[ $nombre ] : $defecto;
		}
		return $salida;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Usuario actual (doble).
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return (int) ( $GLOBALS['convoca_courses_user_id'] ?? 0 );
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	// El doble TIENE que llamarse como la función real de WordPress (get_the_ID): renombrarlo
	// rompe el contrato. La regla se desactiva solo para esta función, con el motivo escrito.
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName
	/**
	 * Entrada actual (doble).
	 *
	 * @return int
	 */
	function get_the_ID() {
		return (int) ( $GLOBALS['convoca_courses_post_id'] ?? 0 );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Título de una entrada (doble).
	 *
	 * @param mixed $post Entrada o id.
	 * @return string
	 */
	function get_the_title( $post = 0 ) {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		return (string) ( $GLOBALS['convoca_courses_titulos'][ $id ] ?? 'Título ' . $id );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapa HTML (doble).
	 *
	 * @param string $t Texto.
	 * @return string
	 */
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}
