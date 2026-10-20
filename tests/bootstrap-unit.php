<?php
/**
 * Bootstrap de las pruebas unitarias: sin WordPress.
 *
 * Solo se doblan las funciones de WordPress que tocan las clases bajo prueba. Cada doble es
 * deliberadamente pequeño: si una prueba necesita media API de WordPress, lo que falla es el diseño
 * de la prueba.
 *
 * @package Convoca\Courses
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'CONVOCA_COURSES_VERSION', '0.1.0' );
define( 'CONVOCA_COURSES_DB_VERSION', '1.0.0' );
define( 'CONVOCA_COURSES_FILE', __DIR__ . '/../convoca-courses.php' );
define( 'CONVOCA_COURSES_DIR', __DIR__ . '/../' );
define( 'CONVOCA_COURSES_URL', 'https://example.org/wp-content/plugins/convoca-courses/' );

if ( ! function_exists( '__' ) ) {
	/**
	 * Traducción: devuelve el texto tal cual.
	 *
	 * @param string $text   Texto.
	 * @param string $domain Dominio.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Traducción escapada.
	 *
	 * @param string $text   Texto.
	 * @param string $domain Dominio.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Formato numérico localizado (aquí, coma decimal).
	 *
	 * @param float $number   Número.
	 * @param int   $decimals Decimales.
	 * @return string
	 */
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals, ',', '.' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * parse_url de WordPress.
	 *
	 * @param string $url       URL.
	 * @param int    $component Componente.
	 * @return array<string,int|string>|string|int|false
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- doble de prueba.
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Quita la barra final.
	 *
	 * @param string $value Valor.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filtros: devuelve el valor sin tocar.
	 *
	 * @param string $hook      Hook.
	 * @param mixed  $value     Valor.
	 * @param mixed  ...$args   Argumentos.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Acciones: no hace nada.
	 *
	 * @param string $hook Hook.
	 * @param mixed  ...$args Argumentos.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		unset( $hook, $args );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * Tipo de contenido desde un mapa que fija la prueba.
	 *
	 * @param int $post_id Identificador.
	 * @return string|false
	 */
	function get_post_type( $post_id ) {
		$map = isset( $GLOBALS['convoca_test_post_types'] ) ? $GLOBALS['convoca_test_post_types'] : array();
		return isset( $map[ (int) $post_id ] ) ? $map[ (int) $post_id ] : false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Meta desde un mapa que fija la prueba.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @param bool   $single  Único.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		$map = isset( $GLOBALS['convoca_test_meta'] ) ? $GLOBALS['convoca_test_meta'] : array();
		return isset( $map[ (int) $post_id ][ $key ] ) ? $map[ (int) $post_id ][ $key ] : '';
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Sal del sitio fija para las pruebas.
	 *
	 * @param string $scheme Esquema.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'sal-de-prueba-fija';
	}
}

require_once __DIR__ . '/../vendor/autoload.php';
