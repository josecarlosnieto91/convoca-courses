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
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CONVOCA_COURSES_VERSION', '0.2.1' );
define( 'CONVOCA_COURSES_DB_VERSION', '2.0.0' );
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
	 * Envoltorio de parse_url, como en WordPress.
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

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Emula get_posts() sobre los mapas que fija la prueba: filtra por tipo y por meta_query.
	 *
	 * Es lo mínimo para poder probar una búsqueda por meta sin base de datos. Si la búsqueda real
	 * cambia de forma, esta prueba deja de valer y hay que ampliar el doble.
	 *
	 * @param array<string, mixed> $args Argumentos.
	 * @return array<int, int>|array<int, object>
	 */
	function get_posts( $args = array() ) {
		$types = isset( $GLOBALS['convoca_test_post_types'] ) ? $GLOBALS['convoca_test_post_types'] : array();
		$meta  = isset( $GLOBALS['convoca_test_meta'] ) ? $GLOBALS['convoca_test_meta'] : array();
		$ids   = array();

		foreach ( $types as $post_id => $type ) {
			if ( isset( $args['post_type'] ) && $args['post_type'] !== $type ) {
				continue;
			}

			$matches = true;
			if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
				foreach ( $args['meta_query'] as $condition ) {
					if ( ! is_array( $condition ) || ! isset( $condition['key'] ) ) {
						continue;
					}
					$stored = isset( $meta[ $post_id ][ $condition['key'] ] ) ? (string) $meta[ $post_id ][ $condition['key'] ] : '';
					if ( $stored !== (string) $condition['value'] ) {
						$matches = false;
						break;
					}
				}
			}

			if ( $matches ) {
				$ids[] = $post_id;
			}
		}

		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			return $ids;
		}

		return array_map(
			static function ( $id ) {
				$post            = new \stdClass();
				$post->ID        = $id;
				$post->post_type = get_post_type( $id );
				return $post;
			},
			$ids
		);
	}
}

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * Se guardan los argumentos: una prueba tiene que poder decir con qué capacidades se registra
	 * un tipo de contenido (el defecto de $post_type_meta_caps empezó justo ahí).
	 */
	function register_post_type( string $slug, array $args = array() ) {
		$GLOBALS['_cc_cpt'][ $slug ] = $args;

		return null;
	}
}

if ( ! function_exists( 'register_post_meta' ) ) {
	/** Se apunta lo declarado: la meta registrada es contrato público del plugin. */
	function register_post_meta( string $post_type, string $key, array $args = array() ) {
		$GLOBALS['_cc_post_meta'][ $post_type ][ $key ] = $args;

		return true;
	}
}
