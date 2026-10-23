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
		/** @var array<int, array> Borrados ejecutados. */
		public $borrados = array();
		/** @var array<int, array> Actualizaciones ejecutadas. */
		public $actualizados = array();

		/**
		 * Identificador de la última inserción.
		 *
		 * @var int
		 */
		public $insert_id = 101;
		/** @var array<int, array> Columnas servidas por get_col(). */
		public $cols = array();

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
		 * Anota una actualización y dice cuántas filas tocó.
		 *
		 * @param string     $tabla    Tabla.
		 * @param array      $datos    Valores.
		 * @param array      $donde    Condiciones.
		 * @param array|null $formatos Formatos de los valores.
		 * @param array|null $donde_fmt Formatos de las condiciones.
		 * @return int
		 */
		public function update( $tabla, $datos, $donde, $formatos = null, $donde_fmt = null ) {
			$this->actualizados[] = array( $tabla, $datos, $donde, $formatos, $donde_fmt );

			if ( ! empty( $GLOBALS['convoca_courses_wpdb_falla']['update'] ) ) {
				return false;
			}

			return 1;
		}

		/**
		 * Lee una columna de la consulta preparada.
		 *
		 * @param string $sql Consulta.
		 * @return array<int, mixed>
		 */
		public function get_col( $sql = '' ) {
			$this->consultas[] = (string) $sql;
			return array_shift( $this->cols );
		}

		/**
		 * Anota un borrado y dice cuántas filas se llevó.
		 *
		 * @param string $tabla   Tabla.
		 * @param array  $where   Condiciones.
		 * @param array  $formato Formatos.
		 * @return int
		 */
		public function delete( $tabla, $where, $formato = null ) {
			$this->borrados[] = array( $tabla, $where, $formato );
			return 1;
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
			++$this->insert_id;

			if ( ! empty( $GLOBALS['convoca_courses_wpdb_falla']['insert'] ) ) {
				return false;
			}
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
		$ahora = $GLOBALS['convoca_courses_ahora'] ?? 1_761_220_800;

		return 'timestamp' === $formato ? (int) $ahora : gmdate( 'Y-m-d H:i:s', (int) $ahora );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Meta de una entrada (doble que lee del mismo almacén que get_posts: convoca_test_meta).
	 *
	 * @param int    $post_id Entrada.
	 * @param string $clave   Clave.
	 * @param bool   $unico   Si se pide un solo valor.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $clave = '', $unico = false ) {
		unset( $unico );
		return $GLOBALS['convoca_test_meta'][ (int) $post_id ][ (string) $clave ] ?? '';
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * Contraseña aleatoria (doble determinista: la prueba fija el sufijo del código).
	 *
	 * @param int  $largo     Longitud.
	 * @param bool $especial  Caracteres especiales.
	 * @param bool $extra     Caracteres extra.
	 * @return string
	 */
	function wp_generate_password( $largo = 12, $especial = true, $extra = false ) {
		unset( $especial, $extra );
		$base = $GLOBALS['convoca_courses_aleatorio'] ?? 'abcd1234';
		return substr( str_pad( (string) $base, (int) $largo, '0' ), 0, (int) $largo );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Datos del sitio (doble).
	 *
	 * @param string $campo Campo.
	 * @return string
	 */
	function get_bloginfo( $campo = '' ) {
		return 'name' === $campo ? (string) ( $GLOBALS['convoca_courses_sitio'] ?? 'El sitio' ) : '';
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Nombre de fichero seguro (doble: quita lo que no sea letra, número, guion o punto).
	 *
	 * @param string $nombre Nombre.
	 * @return string
	 */
	function sanitize_file_name( $nombre ) {
		return (string) preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $nombre );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Añade la barra final si falta.
	 *
	 * @param string $ruta Ruta.
	 * @return string
	 */
	function trailingslashit( $ruta ) {
		return rtrim( (string) $ruta, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Imprime un texto escapado (doble).
	 *
	 * @param string $texto  Texto.
	 * @param string $dominio Dominio (no se usa).
	 * @return void
	 */
	function esc_html_e( $texto, $dominio = '' ) {
		unset( $dominio );
		echo function_exists( 'esc_html' ) ? esc_html( $texto ) : $texto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de esc_html_e.
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Imprime un atributo escapado (doble).
	 *
	 * @param string $texto   Texto.
	 * @param string $dominio Dominio (no se usa).
	 * @return void
	 */
	function esc_attr_e( $texto, $dominio = '' ) {
		unset( $dominio );
		echo function_exists( 'esc_attr' ) ? esc_attr( $texto ) : $texto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de esc_attr_e.
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * ¿Hay un evento programado? (doble que lee convoca_courses_cron).
	 *
	 * @param string $hook Gancho.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['convoca_courses_cron'][ (string) $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Programa un evento (doble que apunta en convoca_courses_cron).
	 *
	 * @param int    $cuando Marca de tiempo.
	 * @param string $periodo Periodicidad.
	 * @param string $hook   Gancho.
	 * @return bool
	 */
	function wp_schedule_event( $cuando, $periodo, $hook ) {
		$GLOBALS['convoca_courses_cron'][ (string) $hook ] = array( (int) $cuando, (string) $periodo );
		return true;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	/**
	 * Quita un evento programado (doble).
	 *
	 * @param int    $cuando Marca de tiempo.
	 * @param string $hook   Gancho.
	 * @return bool
	 */
	function wp_unschedule_event( $cuando, $hook ) {
		$GLOBALS['convoca_courses_cron_quitados'][] = array( (int) $cuando, (string) $hook );
		unset( $GLOBALS['convoca_courses_cron'][ (string) $hook ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_time' ) ) {
	/**
	 * Fecha de una entrada (doble que lee convoca_courses_fechas).
	 *
	 * @param string $formato Formato.
	 * @param bool   $gmt     GMT.
	 * @param int    $post_id Entrada.
	 * @return int|string
	 */
	function get_post_time( $formato = 'U', $gmt = false, $post_id = 0 ) {
		unset( $gmt );
		$marca = (int) ( $GLOBALS['convoca_courses_fechas'][ (int) $post_id ] ?? 0 );

		return 'U' === $formato ? $marca : gmdate( (string) $formato, $marca );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON de WordPress (doble que delega en json_encode).
	 *
	 * @param mixed $datos       Datos.
	 * @param int   $opciones    Opciones.
	 * @param int   $profundidad Profundidad.
	 * @return string|false
	 */
	function wp_json_encode( $datos, $opciones = 0, $profundidad = 512 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- doble de wp_json_encode, aquí no hay WordPress.
		return json_encode( $datos, (int) $opciones, (int) $profundidad );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Una entrada (doble que sirve convoca_test_posts).
	 *
	 * @param int $post_id Identificador.
	 * @return object|null
	 */
	function get_post( $post_id = 0 ) {
		return $GLOBALS['convoca_test_posts'][ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * Crea una entrada (doble que apunta lo insertado y devuelve un id fijo o un error).
	 *
	 * @param array $datos    Datos.
	 * @param bool  $wp_error Si devuelve un error.
	 * @return int|WP_Error
	 */
	function wp_insert_post( $datos = array(), $wp_error = false ) {
		unset( $wp_error );

		if ( isset( $GLOBALS['convoca_test_insert_error'] ) && $GLOBALS['convoca_test_insert_error'] ) {
			return new WP_Error( 'insert_fallido', 'No se pudo crear.' );
		}

		$GLOBALS['convoca_test_insertados'][] = $datos;
		return (int) ( $GLOBALS['convoca_test_nuevo_id'] ?? 100 );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Guarda meta de una entrada (doble que escribe en convoca_test_meta).
	 *
	 * @param int    $post_id Entrada.
	 * @param string $clave   Clave.
	 * @param mixed  $valor   Valor.
	 * @return bool
	 */
	function update_post_meta( $post_id, $clave, $valor ) {
		$GLOBALS['convoca_test_meta'][ (int) $post_id ][ (string) $clave ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * ¿Es un error de WordPress? (doble).
	 *
	 * @param mixed $cosa Valor.
	 * @return bool
	 */
	function is_wp_error( $cosa ) {
		return $cosa instanceof WP_Error;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	/**
	 * Campo de una entrada (doble que lee convoca_test_campos).
	 *
	 * @param string $campo   Campo.
	 * @param int    $post_id Entrada.
	 * @return string
	 */
	function get_post_field( $campo, $post_id = 0 ) {
		return (string) ( $GLOBALS['convoca_test_campos'][ (int) $post_id ][ (string) $campo ] ?? '' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Clave saneada (doble: solo minúsculas, números, guion y guion bajo).
	 *
	 * @param string $clave Clave.
	 * @return string
	 */
	function sanitize_key( $clave ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $clave ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * URL saneada para guardar (doble: quita espacios y esquemas raros).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		$url = trim( (string) $url );
		return (string) preg_replace( '/[^A-Za-z0-9:\/?#\[\]@!$&\'()*+,;=._~%\-]/', '', $url );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capacidades de la persona (doble que lee convoca_test_caps).
	 *
	 * @param string $capacidad Capacidad.
	 * @return bool
	 */
	function current_user_can( $capacidad ) {
		$caps = $GLOBALS['convoca_test_caps'] ?? array();
		return ! empty( $caps[ (string) $capacidad ] );
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Añade una página bajo un menú (doble que apunta los argumentos).
	 *
	 * @param string $padre   Menú padre.
	 * @param string $titulo  Título.
	 * @param string $menu    Etiqueta del menú.
	 * @param string $cap     Capacidad.
	 * @param string $slug    Identificador.
	 * @param mixed  $render  Devolución de llamada.
	 * @return string
	 */
	function add_submenu_page( $padre, $titulo = '', $menu = '', $cap = '', $slug = '', $render = null ) {
		$GLOBALS['convoca_courses_submenus'][ (string) $slug ] = array(
			'padre'  => (string) $padre,
			'titulo' => (string) $titulo,
			'menu'   => (string) $menu,
			'cap'    => (string) $cap,
			'render' => $render,
		);

		return 'hook-' . (string) $slug;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	/**
	 * Registra un ajuste (doble que apunta su saneado y su valor por defecto).
	 *
	 * @param string $grupo   Grupo.
	 * @param string $opcion  Opción.
	 * @param array  $args    Argumentos.
	 * @return void
	 */
	function register_setting( $grupo, $opcion, $args = array() ) {
		$GLOBALS['convoca_courses_ajustes'][ (string) $grupo ][ (string) $opcion ] = $args;
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	/**
	 * Campos ocultos del formulario de ajustes (doble: imprime el grupo).
	 *
	 * @param string $grupo Grupo.
	 * @return void
	 */
	function settings_fields( $grupo ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( (string) $grupo ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de settings_fields.
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Botón de guardar (doble).
	 *
	 * @param string $texto Texto.
	 * @return void
	 */
	function submit_button( $texto = 'Guardar cambios' ) {
		echo '<button type="submit">' . esc_html( (string) $texto ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de submit_button.
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Atributo escapado (doble).
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	function esc_attr( $texto ) {
		return htmlspecialchars( (string) $texto, ENT_QUOTES );
	}
}

if ( ! function_exists( 'checked' ) ) {
	/**
	 * Atributo `checked` (doble).
	 *
	 * @param mixed $actual   Valor actual.
	 * @param mixed $esperado Valor esperado.
	 * @param bool  $imprimir Si se imprime.
	 * @return string
	 */
	function checked( $actual, $esperado = true, $imprimir = true ) {
		$salida = (string) $actual === (string) $esperado ? ' checked="checked"' : '';

		if ( $imprimir ) {
			echo $salida; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de checked.
		}

		return $salida;
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Atributo `selected` (doble).
	 *
	 * @param mixed $actual   Valor actual.
	 * @param mixed $esperado Valor esperado.
	 * @param bool  $imprimir Si se imprime.
	 * @return string
	 */
	function selected( $actual, $esperado = true, $imprimir = true ) {
		$salida = (string) $actual === (string) $esperado ? ' selected="selected"' : '';

		if ( $imprimir ) {
			echo $salida; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble de selected.
		}

		return $salida;
	}
}
