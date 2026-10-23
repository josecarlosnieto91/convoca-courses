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

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Registra un gancho (doble que apunta el alta).
	 *
	 * @param string   $hook     Gancho.
	 * @param callable $callback Callback.
	 * @param int      $priority Prioridad.
	 * @param int      $args     Argumentos.
	 * @return bool
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['convoca_courses_acciones'][ $hook ] = array(
			'callback' => $callback,
			'priority' => (int) $priority,
			'args'     => (int) $args,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Corta la petición (en las pruebas, lanza para poder comprobarlo).
	 *
	 * @param string $mensaje Mensaje.
	 * @return never
	 * @throws RuntimeException Siempre: es la forma de que la prueba vea el corte.
	 */
	function wp_die( $mensaje = '' ) {
		throw new RuntimeException( 'wp_die: ' . ( is_scalar( $mensaje ) ? $mensaje : '?' ) );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Verifica un nonce (doble controlable).
	 *
	 * @param string $nonce  Nonce.
	 * @param string $accion Acción.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $accion = -1 ) {
		$GLOBALS['convoca_courses_nonces_vistos'][] = array( (string) $nonce, (string) $accion );
		return $GLOBALS['convoca_courses_nonce_ok'] ?? 1;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanea texto (doble).
	 *
	 * @param string $v Valor.
	 * @return string
	 */
	function sanitize_text_field( $v ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- este doble ES sanitize_text_field.
		return trim( strip_tags( (string) $v ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Entero positivo (doble).
	 *
	 * @param mixed $v Valor.
	 * @return int
	 */
	function absint( $v ) {
		return abs( (int) $v );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Quita barras (doble).
	 *
	 * @param mixed $v Valor.
	 * @return mixed
	 */
	function wp_unslash( $v ) {
		return is_string( $v ) ? stripslashes( $v ) : $v;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * URL del sitio (doble).
	 *
	 * @param string $ruta Ruta.
	 * @return string
	 */
	function home_url( $ruta = '' ) {
		return 'https://example.test' . $ruta;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Enlace de una entrada (doble).
	 *
	 * @param mixed $post Entrada o id.
	 * @return string
	 */
	function get_permalink( $post = 0 ) {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		return 'https://example.test/?p=' . $id;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Referente de la petición (doble).
	 *
	 * @return string|false
	 */
	function wp_get_referer() {
		return $GLOBALS['convoca_courses_referer'] ?? false;
	}
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	// phpcs:disable WordPress.WP.GlobalVariablesOverride -- doble de $wpdb para pruebas, en el arnés.
	// phpcs:disable Generic.Commenting.DocComment -- los métodos del doble se documentan arriba, no uno a uno.
	/**
	 * Doble de $wpdb con respuestas preparadas por la prueba.
	 */
	$GLOBALS['wpdb'] = new class() {
		/** @var string */
		public $prefix = 'wp_';
		/** @var string */
		public $posts = 'wp_posts';
		/** @var string */
		public $postmeta = 'wp_postmeta';
		/** @var string */
		public $options = 'wp_options';
		/** @var array<int, mixed> Respuestas para get_var(). */
		public $vars = array();
		/** @var array<int, mixed> Respuestas para get_row(). */
		public $filas = array();
		/** @var array<int, mixed> Respuestas para get_results(). */
		public $resultados = array();
		/** @var array<int, string> Consultas ejecutadas. */
		public $consultas = array();
		/** @var array<int, array> Inserciones. */
		public $inserts = array();
		/** @var array<int, string> Formatos pedidos en cada lectura. */
		public $formatos = array();

		/**
		 * Siguiente valor preparado.
		 *
		 * @param string $sql Consulta.
		 * @return mixed
		 */
		public function get_var( $sql = '' ) {
			$this->consultas[] = (string) $sql;
			return array_shift( $this->vars );
		}

		/**
		 * Siguiente fila preparada.
		 *
		 * @param string $sql    Consulta.
		 * @param string $salida Formato.
		 * @return mixed
		 */
		public function get_row( $sql = '', $salida = null ) {
			$this->formatos[]  = (string) $salida;
			$this->consultas[] = (string) $sql;
			return array_shift( $this->filas );
		}

		/**
		 * Siguiente juego de filas preparado.
		 *
		 * @param string $sql    Consulta.
		 * @param string $salida Formato.
		 * @return mixed
		 */
		public function get_results( $sql = '', $salida = null ) {
			$this->formatos[]  = (string) $salida;
			$this->consultas[] = (string) $sql;
			return array_shift( $this->resultados );
		}

		/**
		 * Anota una consulta de escritura.
		 *
		 * @param string $sql Consulta.
		 * @return bool
		 */
		public function query( $sql ) {
			$this->consultas[] = (string) $sql;
			return true;
		}

		/**
		 * Anota una inserción.
		 *
		 * @param string $tabla   Tabla.
		 * @param array  $datos   Datos.
		 * @param array  $formato Formatos.
		 * @return int
		 */
		public function insert( $tabla, $datos, $formato = null ) {
			$this->inserts[] = array( $tabla, $datos, $formato );
			return count( $this->inserts );
		}

		/**
		 * Sustituye los marcadores por sus valores (doble suficiente).
		 *
		 * @param string $sql     Consulta.
		 * @param mixed  ...$args Valores.
		 * @return string
		 */
		public function prepare( $sql, ...$args ) {
			$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
			foreach ( $args as $valor ) {
				$sql = preg_replace( '/%[sdf]/', is_string( $valor ) ? "'" . $valor . "'" : (string) $valor, $sql, 1 );
			}
			return (string) $sql;
		}
	};
	// phpcs:enable WordPress.WP.GlobalVariablesOverride
	// phpcs:enable Generic.Commenting.DocComment
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Opciones del sitio (doble en memoria).
	 *
	 * @param string $nombre  Nombre.
	 * @param mixed  $defecto Valor por defecto.
	 * @return mixed
	 */
	function get_option( $nombre, $defecto = false ) {
		$GLOBALS['convoca_courses_opciones'] = $GLOBALS['convoca_courses_opciones'] ?? array();
		return array_key_exists( $nombre, $GLOBALS['convoca_courses_opciones'] ) ? $GLOBALS['convoca_courses_opciones'][ $nombre ] : $defecto;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Guarda una opción (doble en memoria).
	 *
	 * @param string $nombre Nombre.
	 * @param mixed  $valor  Valor.
	 * @param mixed  $auto   Autoload (no se usa).
	 * @return bool
	 */
	function update_option( $nombre, $valor, $auto = null ) {
		$GLOBALS['convoca_courses_opciones'][ $nombre ] = $valor;
		$GLOBALS['convoca_courses_autoload'][ $nombre ] = $auto;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Borra una opción (doble en memoria).
	 *
	 * @param string $nombre Nombre.
	 * @return bool
	 */
	function delete_option( $nombre ) {
		unset( $GLOBALS['convoca_courses_opciones'][ $nombre ] );
		return true;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * Datos de una persona (doble que lee de convoca_courses_usuarios).
	 *
	 * @param int $user_id Identificador.
	 * @return object|false
	 */
	function get_userdata( $user_id ) {
		return $GLOBALS['convoca_courses_usuarios'][ (int) $user_id ] ?? false;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Hora actual del sitio (doble fijo: las pruebas no dependen del reloj).
	 *
	 * @param string $formato Formato.
	 * @return string
	 */
	function current_time( $formato = 'mysql' ) {
		return 'mysql' === $formato ? '2026-10-23 10:00:00' : '2026-10-23 10:00:00';
	}
}
