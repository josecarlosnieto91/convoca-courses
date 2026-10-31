<?php
/**
 * Bootstrap de las pruebas unitarias: sin WordPress.
 *
 * Solo se doblan las funciones de WordPress que tocan las clases bajo prueba. Cada doble es
 * deliberadamente pequeño: si una prueba necesita media API de WordPress, lo que falla es el diseño
 * de la prueba. Las clases dobladas viven en `stubs-clases.php`, porque PHPCS no admite un fichero
 * que mezcle funciones y clases.
 *
 * @package Convoca\Courses
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CONVOCA_COURSES_VERSION', '0.2.1' );

require_once __DIR__ . '/stubs-clases.php';
require_once __DIR__ . '/StubRedirect.php';
require_once __DIR__ . '/StubWpPost.php';
require_once __DIR__ . '/StubAssets.php';
require_once __DIR__ . '/StubShortcodes.php';
require_once __DIR__ . '/StubWpError.php';
require_once __DIR__ . '/StubCoreUtils.php';
require_once __DIR__ . '/StubFeatures.php';
require_once __DIR__ . '/StubPayments.php';
require_once __DIR__ . '/StubGatewayPago.php';
require_once __DIR__ . '/StubMailer.php';
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
		unset( $args );

		// Un filtro se puede forzar desde la prueba: sin esto no hay forma de encender el PRO.
		if ( array_key_exists( $hook, $GLOBALS['convoca_courses_filtros'] ?? array() ) ) {
			return $GLOBALS['convoca_courses_filtros'][ $hook ];
		}

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
				// `WP_Post` siempre trae `post_title`: sin él, cualquier selector que lo lea revienta con un aviso
				// que en producción no existe.
				$post->post_title = isset( $GLOBALS['convoca_test_titulos'][ $id ] )
					? (string) $GLOBALS['convoca_test_titulos'][ $id ]
					: 'Entrada ' . $id;
				// `WP_Post` también trae siempre el contenido: sin él, cualquier pantalla que lo lea
				// dispara un aviso que en producción no existe (y el arnés lo tapaba).
				$post->post_content = isset( $GLOBALS['convoca_test_posts'][ $id ]->post_content )
					? (string) $GLOBALS['convoca_test_posts'][ $id ]->post_content
					: '';
				return $post;
			},
			$ids
		);
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * URL de un adjunto desde el mapa que fija la prueba (0 si no está en la biblioteca).
	 *
	 * @param int $id Identificador del adjunto.
	 * @return string
	 */
	function wp_get_attachment_url( $id ) {
		$mapa = isset( $GLOBALS['convoca_test_adjuntos'] ) ? $GLOBALS['convoca_test_adjuntos'] : array();
		return isset( $mapa[ (int) $id ] ) ? (string) $mapa[ (int) $id ] : '';
	}
}

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * Se guardan los argumentos: una prueba tiene que poder decir con qué capacidades se registra
	 * un tipo de contenido (el defecto de $post_type_meta_caps empezó justo ahí).
	 *
	 * @param string $slug Nombre del tipo.
	 * @param array  $args Argumentos del registro.
	 * @return null
	 */
	function register_post_type( string $slug, array $args = array() ) {
		$GLOBALS['_cc_cpt'][ $slug ] = $args;

		return null;
	}
}

if ( ! function_exists( 'register_post_meta' ) ) {
	/**
	 * Se apunta lo declarado: la meta registrada es contrato público del plugin.
	 *
	 * @param string $post_type Tipo de contenido.
	 * @param string $key       Clave de la meta.
	 * @param array  $args      Argumentos del registro.
	 * @return bool
	 */
	function register_post_meta( string $post_type, string $key, array $args = array() ) {
		$GLOBALS['_cc_post_meta'][ $post_type ][ $key ] = $args;

		return true;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Nonce de mentira pero **distinto por acción**: lo que se prueba es que cada acción pida el
	 * suyo, y para eso el doble apunta la acción y devuelve algo que la nombra.
	 *
	 * @param string $action Acción a la que se asocia el nonce.
	 * @return string
	 */
	function wp_create_nonce( $action = '' ) {
		$GLOBALS['_cc_nonces'][ (string) $action ] = true;

		return 'nonce-' . md5( (string) $action );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escapado de URL: en las pruebas, tal cual.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url = '' ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * URL del panel.
	 *
	 * @param string $path Ruta.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.org/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Añade parámetros a una URL, con las dos firmas de WordPress.
	 *
	 * @param mixed ...$args Clave, valor y URL; o mapa y URL.
	 * @return string
	 */
	function add_query_arg( ...$args ) {
		// Las dos firmas de WordPress: (clave, valor, url) y (mapa, url).
		if ( count( $args ) >= 3 ) {
			list( $clave, $valor, $url ) = $args;
			$union                       = false !== strpos( (string) $url, '?' ) ? '&' : '?';

			return (string) $url . $union . rawurlencode( (string) $clave ) . '=' . rawurlencode( (string) $valor );
		}

		$mapa  = (array) ( $args[0] ?? array() );
		$url   = (string) ( $args[1] ?? '' );
		$union = false !== strpos( $url, '?' ) ? '&' : '?';

		return $url . $union . http_build_query( $mapa );
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	/**
	 * Guarda la caja registrada (pantalla, contexto y callback).
	 *
	 * @param string   $id        Identificador.
	 * @param string   $titulo    Título.
	 * @param callable $callback  Devolución.
	 * @param string   $pantalla  Pantalla.
	 * @param string   $contexto  Contexto.
	 * @param string   $prioridad Prioridad.
	 * @return void
	 */
	function add_meta_box( $id, $titulo, $callback, $pantalla = 'post', $contexto = 'advanced', $prioridad = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- se usan en compact().
		$GLOBALS['convoca_test_cajas'][ $id ] = compact( 'titulo', 'callback', 'pantalla', 'contexto', 'prioridad' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Campo oculto del nonce.
	 *
	 * @param string $accion  Acción.
	 * @param string $nombre  Nombre.
	 * @param bool   $referer Referer (se ignora).
	 * @param bool   $mostrar Si debe imprimirse.
	 * @return string
	 */
	function wp_nonce_field( $accion, $nombre = '_wpnonce', $referer = true, $mostrar = true ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound -- mismo nombre que WordPress.
		unset( $referer );
		$html = '<input type="hidden" name="' . $nombre . '" value="nonce-' . $accion . '">';

		if ( $mostrar ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo ya construido y escapado.
		}

		return $html;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Guarda la meta en el mismo mapa que lee `get_post_meta()`.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @param mixed  $value   Valor.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['convoca_test_meta'][ (int) $post_id ][ (string) $key ] = is_scalar( $value ) ? (string) $value : $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Borra la meta y lo apunta.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['convoca_test_meta'][ (int) $post_id ][ (string) $key ] );
		$GLOBALS['convoca_test_meta_borrada'][] = array( (int) $post_id, (string) $key );
		return true;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Guarda el aviso temporal.
	 *
	 * @param string $clave  Clave.
	 * @param mixed  $valor  Valor.
	 * @param int    $expira Segundos.
	 * @return bool
	 */
	function set_transient( $clave, $valor, $expira = 0 ) {
		unset( $expira );
		$GLOBALS['convoca_test_transients'][ $clave ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Lee el aviso temporal.
	 *
	 * @param string $clave Clave.
	 * @return mixed
	 */
	function get_transient( $clave ) {
		return $GLOBALS['convoca_test_transients'][ $clave ] ?? false;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Limpia una URL sin escaparla para HTML.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Redirección que **corta** el flujo, como la de WordPress seguida de `exit`.
	 *
	 * @param string $url Destino.
	 * @return never
	 * @throws \Convoca_Courses_Redirect Siempre: corta con la URL de destino.
	 */
	function wp_safe_redirect( $url ) {
		throw new \Convoca_Courses_Redirect( (string) $url );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/**
	 * Enlace de edición de la entrada.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $context Contexto.
	 * @return string
	 */
	function get_edit_post_link( $post_id, $context = 'display' ) {
		unset( $context );
		return 'https://panel.example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Limpia un área de texto conservando los saltos de línea.
	 *
	 * @param mixed $valor Valor.
	 * @return string
	 */
	function sanitize_textarea_field( $valor ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $valor ) );
	}
}

/**
 * Doble de la base de datos para las pruebas de la tabla de identidades.
 */
require_once __DIR__ . '/WpdbDoble.php';

/**
 * El doble de `$wpdb` solo se instala en las pruebas que lo necesitan (ver IdentidadTest).
 */

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Hora actual (doble: UTC en formato MySQL).
	 *
	 * @param string $tipo   Tipo.
	 * @param bool   $gmt    Si es GMT.
	 * @return string
	 */
	function current_time( $tipo = 'mysql', $gmt = false ) {
		unset( $tipo, $gmt );
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Número aleatorio (doble determinista).
	 *
	 * @param int $min Mínimo.
	 * @param int $max Máximo.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return (int) ( ( (int) $min + (int) $max ) / 2 );
	}
}
