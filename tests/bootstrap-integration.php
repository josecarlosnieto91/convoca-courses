<?php
/**
 * Bootstrap de las pruebas de integración: WordPress en memoria + mock de Convoca Gateway.
 *
 * Lo que se prueba es el **cableado** entre el módulo de cursos y la pasarela: matrícula, creación del
 * pago, confirmación del cobro y rechazo. El gateway no se toca: se dobla su API pública con la misma
 * firma y se disparan sus ganchos tal y como los dispara él.
 *
 * @package Convoca\Courses
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/../' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'CONVOCA_COURSES_VERSION', '0.2.1' );
	define( 'CONVOCA_COURSES_DB_VERSION', '1.0.0' );
	define( 'CONVOCA_COURSES_FILE', __DIR__ . '/../convoca-courses.php' );
	define( 'CONVOCA_COURSES_DIR', __DIR__ . '/../' );
	define( 'CONVOCA_COURSES_URL', 'https://example.org/wp-content/plugins/convoca-courses/' );

	// Almacén en memoria y estado del mock.
	$GLOBALS['cc_posts']   = array();
	$GLOBALS['cc_meta']    = array();
	$GLOBALS['cc_actions'] = array();
	$GLOBALS['cc_next_id'] = 100;
	$GLOBALS['cc_gateway'] = array(
		'available'  => true,
		'calls'      => array(),
		'fail_next'  => false,
		'core_calls'    => 0,
		'receipt'       => '',
		'receipt_calls' => 0,
	);

	if ( ! class_exists( 'WP_Error' ) ) {
		/**
		 * Error de WordPress.
		 */
		class WP_Error {

			/** @var string */
			private $code;

			/** @var string */
			private $message;

			/**
			 * Constructor.
			 *
			 * @param string $code    Código.
			 * @param string $message Mensaje.
			 */
			public function __construct( $code = '', $message = '' ) {
				$this->code    = (string) $code;
				$this->message = (string) $message;
			}

			/**
			 * Código.
			 *
			 * @return string
			 */
			public function get_error_code() {
				return $this->code;
			}

			/**
			 * Mensaje.
			 *
			 * @return string
			 */
			public function get_error_message() {
				return $this->message;
			}
		}
	}

	/**
	 * ¿Es un error?
	 *
	 * @param mixed $thing Valor.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	/**
	 * Traducción.
	 *
	 * @param string $text   Texto.
	 * @param string $domain Dominio.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}

	/**
	 * Escapado de texto.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	function esc_html( $text ) {
		return (string) $text;
	}

	/**
	 * Escapado de URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}

	/**
	 * Saneado de texto.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}

	/**
	 * Filtros: se devuelve el valor sin oyentes.
	 *
	 * @param string $hook  Hook.
	 * @param mixed  $value Valor.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}

	$GLOBALS['cc_hooks'] = array();

	/**
	 * Registra un gancho (acción o filtro).
	 *
	 * El arnés despacha de verdad: el plugin registra sus ganchos llamando a `Plugin::boot()`, así que
	 * lo que se prueba es el cableado real y no una imitación.
	 *
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Prioridad.
	 * @param int      $accepted_args Argumentos aceptados.
	 * @return void
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cc_hooks'][ (string) $hook ][] = array(
			'callback' => $callback,
			'args'     => (int) $accepted_args,
		);
	}

	/**
	 * Registra un filtro (mismo almacén que las acciones).
	 *
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Prioridad.
	 * @param int      $accepted_args Argumentos aceptados.
	 * @return void
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Shortcodes (no se usan en estas pruebas).
	 *
	 * @param string   $tag      Etiqueta.
	 * @param callable $callback Callback.
	 * @return void
	 */
	function add_shortcode( $tag, $callback ) {
		unset( $tag, $callback );
	}

	/**
	 * Reglas de reescritura (no se usan en estas pruebas).
	 *
	 * @param string $regex  Patrón.
	 * @param string $query  Consulta.
	 * @param string $after  Posición.
	 * @return void
	 */
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
		unset( $regex, $query, $after );
	}

	/**
	 * ¿Pantalla de administración? En estas pruebas, no.
	 *
	 * @return bool
	 */
	function is_admin() {
		return false;
	}

	/**
	 * Acciones: se apuntan y se despachan a los ganchos registrados.
	 *
	 * @param string $hook Hook.
	 * @param mixed  ...$args Argumentos.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		$GLOBALS['cc_actions'][] = array(
			'hook' => (string) $hook,
			'args' => $args,
		);

		foreach ( $GLOBALS['cc_hooks'][ (string) $hook ] ?? array() as $listener ) {
			call_user_func_array( $listener['callback'], array_slice( $args, 0, max( 1, $listener['args'] ) ) );
		}
	}

	/**
	 * Inserta una entrada.
	 *
	 * @param array<string, mixed> $args Datos.
	 * @return int
	 */
	function wp_insert_post( $args ) {
		$id = ++$GLOBALS['cc_next_id'];

		$GLOBALS['cc_posts'][ $id ] = (object) array(
			'ID'           => $id,
			'post_type'    => (string) ( $args['post_type'] ?? 'post' ),
			'post_status'  => (string) ( $args['post_status'] ?? 'publish' ),
			'post_title'   => (string) ( $args['post_title'] ?? '' ),
			'post_content' => (string) ( $args['post_content'] ?? '' ),
		);

		return $id;
	}

	/**
	 * Lee una entrada.
	 *
	 * @param int $post_id Identificador.
	 * @return object|null
	 */
	function get_post( $post_id ) {
		return $GLOBALS['cc_posts'][ (int) $post_id ] ?? null;
	}

	/**
	 * Tipo de una entrada.
	 *
	 * @param int $post_id Identificador.
	 * @return string|false
	 */
	function get_post_type( $post_id ) {
		$post = get_post( (int) $post_id );
		return $post ? $post->post_type : false;
	}

	/**
	 * Título.
	 *
	 * @param int $post_id Identificador.
	 * @return string
	 */
	function get_the_title( $post_id ) {
		$post = get_post( (int) $post_id );
		return $post ? $post->post_title : '';
	}

	/**
	 * Meta.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @param bool   $single  Único.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		return $GLOBALS['cc_meta'][ (int) $post_id ][ (string) $key ] ?? '';
	}

	/**
	 * Guarda meta.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @param mixed  $value   Valor.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['cc_meta'][ (int) $post_id ][ (string) $key ] = $value;
		return true;
	}

	/**
	 * Borra meta.
	 *
	 * @param int    $post_id Identificador.
	 * @param string $key     Clave.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['cc_meta'][ (int) $post_id ][ (string) $key ] );
		return true;
	}

	/**
	 * Consulta de entradas: tipo, estado y meta_query (lo que usan las clases bajo prueba).
	 *
	 * @param array<string, mixed> $args Argumentos.
	 * @return array<int, int>|array<int, object>
	 */
	function get_posts( $args = array() ) {
		$ids = array();

		foreach ( $GLOBALS['cc_posts'] as $id => $post ) {
			if ( isset( $args['post_type'] ) && $args['post_type'] !== $post->post_type ) {
				continue;
			}

			if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] ) {
				$statuses = (array) $args['post_status'];
				if ( ! in_array( $post->post_status, $statuses, true ) ) {
					continue;
				}
			}

			$matches = true;
			$query   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

			foreach ( $query as $condition ) {
				if ( ! is_array( $condition ) || ! isset( $condition['key'] ) ) {
					continue;
				}

				$stored  = (string) ( $GLOBALS['cc_meta'][ $id ][ $condition['key'] ] ?? '' );
				$value   = $condition['value'] ?? '';
				$compare = $condition['compare'] ?? '=';

				if ( 'IN' === $compare ) {
					$matches = $matches && in_array( $stored, array_map( 'strval', (array) $value ), true );
				} else {
					$matches = $matches && $stored === (string) $value;
				}
			}

			if ( $matches ) {
				$ids[] = $id;
			}
		}

		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			return $ids;
		}

		return array_map( 'get_post', $ids );
	}

	/**
	 * Consulta de entradas (objeto): solo lo que usa Enrollment::occupied_seats().
	 */
	class WP_Query {

		/** @var int */
		public $found_posts = 0;

		/** @var array<int, mixed> */
		public $posts = array();

		/**
		 * Constructor.
		 *
		 * @param array<string, mixed> $args Argumentos.
		 */
		public function __construct( $args = array() ) {
			$found             = get_posts( $args );
			$this->posts       = $found;
			$this->found_posts = count( $found );
		}
	}

	/**
	 * Persona.
	 *
	 * @param int $user_id Identificador.
	 * @return object|false
	 */
	function get_userdata( $user_id ) {
		return (object) array(
			'ID'           => (int) $user_id,
			'display_name' => 'Persona de prueba',
			'user_email'   => 'persona@example.org',
		);
	}

	/**
	 * Nombre del sitio.
	 *
	 * @param string $what Qué.
	 * @return string
	 */
	function get_bloginfo( $what ) {
		unset( $what );
		return 'Sitio de ejemplo';
	}

	/**
	 * Fecha del sitio.
	 *
	 * @param string $type Tipo.
	 * @return string
	 */
	function current_time( $type ) {
		unset( $type );
		return '2026-10-20 12:00:00';
	}

	/**
	 * Opciones.
	 *
	 * @param string $name    Nombre.
	 * @param mixed  $default Por defecto.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		if ( 'convoca_courses_db_version' === (string) $name ) {
			return CONVOCA_COURSES_DB_VERSION;
		}
		return $default;
	}

	/**
	 * Contraseña aleatoria (determinista para las pruebas).
	 *
	 * @param int  $length  Longitud.
	 * @param bool $special Especiales.
	 * @param bool $digits  Dígitos.
	 * @return string
	 */
	function wp_generate_password( $length = 12, $special = true, $digits = true ) {
		unset( $special, $digits );
		return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz', 3 ), 0, (int) $length );
	}

	/**
	 * Enlace permanente.
	 *
	 * @param int $post_id Identificador.
	 * @return string
	 */
	function get_permalink( $post_id ) {
		return 'https://example.org/curso/' . (int) $post_id . '/';
	}

	// Clases del módulo bajo prueba.
	foreach ( array( 'CPT_Base', 'CPT_Curso', 'CPT_Modulo', 'CPT_Leccion', 'CPT_Matricula', 'Progress', 'Enrollment', 'Access', 'Hierarchy', 'License', 'Course_Payment', 'Certificate', 'Video', 'Installer', 'Verification', 'Shortcodes', 'Handlers', 'Assets', 'Settings', 'Plugin' ) as $convoca_courses_class ) {
		require_once __DIR__ . '/../includes/' . $convoca_courses_class . '.php';
	}

	// Clases del panel (mapa de WordPress, no PSR-4).
	require_once __DIR__ . '/../admin/class-admin-fields.php';
	require_once __DIR__ . '/../admin/class-admin-columns.php';

	// Se arranca el plugin como en producción: así los ganchos de pago que se prueban son los que
	// registra el propio plugin, no una lista copiada en el arnés.
	\Convoca\Courses\Plugin::instance()->boot();
}

namespace Convoca\Core {

	/**
	 * Funciones del ecosistema (dobladas).
	 */
	class Features {

		/**
		 * ¿Gateway activo? Lo decide cada prueba.
		 *
		 * @return bool
		 */
		public static function is_gateway_active(): bool {
			return (bool) $GLOBALS['cc_gateway']['available'];
		}
	}

	/**
	 * Locks (doblados: siempre se conceden).
	 */
	class Utils {

		/**
		 * Toma un lock.
		 *
		 * @param string $key Clave.
		 * @param int    $ttl Segundos.
		 * @return bool
		 */
		public static function acquire_lock( string $key, int $ttl = 60 ): bool {
			unset( $key, $ttl );
			return true;
		}

		/**
		 * Libera un lock.
		 *
		 * @param string $key Clave.
		 * @return bool
		 */
		public static function release_lock( string $key ): bool {
			unset( $key );
			return true;
		}

		/**
		 * Sal del sitio.
		 *
		 * @return string
		 */
		public static function get_persistent_salt(): string {
			return 'sal-de-prueba';
		}
	}

	/**
	 * Registro (doblado: deja rastro en el diario de la prueba).
	 */
	class Logger {

		/**
		 * Error.
		 *
		 * @param string   $message   Mensaje.
		 * @param string   $context   Contexto.
		 * @param int|null $object_id Objeto.
		 * @return void
		 */
		public static function error( string $message, string $context = 'General', ?int $object_id = null ): void {
			$GLOBALS['cc_actions'][] = array(
				'hook' => 'log:error',
				'args' => array( $message, $context, $object_id ),
			);
		}

		/**
		 * Aviso.
		 *
		 * @param string   $message   Mensaje.
		 * @param string   $context   Contexto.
		 * @param int|null $object_id Objeto.
		 * @return void
		 */
		public static function warning( string $message, string $context = 'General', ?int $object_id = null ): void {
			$GLOBALS['cc_actions'][] = array(
				'hook' => 'log:warning',
				'args' => array( $message, $context, $object_id ),
			);
		}

		/**
		 * Información.
		 *
		 * @param string   $message   Mensaje.
		 * @param string   $context   Contexto.
		 * @param int|null $object_id Objeto.
		 * @return void
		 */
		public static function info( string $message, string $context = 'General', ?int $object_id = null ): void {
			unset( $message, $context, $object_id );
		}
	}

	/**
	 * Fachada de pagos (doblada con el mismo contrato que la del núcleo).
	 *
	 * Se dobla para poder comprobar que el módulo cobra **por la fachada** y no llamando a pelo al
	 * gateway; el backend es el mock del gateway.
	 */
	class Payments {

		/**
		 * ¿Hay pasarela?
		 *
		 * @return bool
		 */
		public static function is_available(): bool {
			return function_exists( '\Convoca\Gateway\convoca_gateway_create_payment' );
		}

		/**
		 * Crea un pago llamando al backend.
		 *
		 * @param array<string, mixed> $args Datos.
		 * @return array{pago_id: int, payment_url: string}|\WP_Error
		 */
		public static function create( array $args ) {
			++$GLOBALS['cc_gateway']['core_calls'];

			if ( empty( $args['origin'] ) || empty( $args['origin_id'] ) ) {
				return new \WP_Error( 'convoca_payments_origin', 'Falta el origen del pago o su identificador.' );
			}

			return \Convoca\Gateway\convoca_gateway_create_payment( $args );
		}

		/**
		 * URL del recibo (doblada).
		 *
		 * @param int $pago_id Identificador del pago.
		 * @return string
		 */
		public static function receipt_url( int $pago_id ): string {
			unset( $pago_id );
			$GLOBALS['cc_gateway']['receipt_calls']++;
			return (string) ( $GLOBALS['cc_gateway']['receipt'] ?? '' );
		}

		/**
		 * ¿Es este origen?
		 *
		 * @param string $origin   Origen.
		 * @param string $expected Esperado.
		 * @return bool
		 */
		public static function is_origin( string $origin, string $expected ): bool {
			return $origin === $expected;
		}
	}
}

namespace Convoca\Gateway {

	/**
	 * Mock de la API pública del gateway: misma firma, mismo contrato.
	 *
	 * @param array<string, mixed> $args Datos del pago.
	 * @return array{pago_id: int, payment_url: string}|\WP_Error
	 */
	function convoca_gateway_create_payment( array $args ) {
		$GLOBALS['cc_gateway']['calls'][] = $args;

		if ( ! empty( $GLOBALS['cc_gateway']['fail_next'] ) ) {
			$GLOBALS['cc_gateway']['fail_next'] = false;
			return new \WP_Error( 'gateway_down', 'La pasarela no responde.' );
		}

		$pago_id = 9000 + count( $GLOBALS['cc_gateway']['calls'] );

		$GLOBALS['cc_posts'][ $pago_id ] = (object) array(
			'ID'           => $pago_id,
			'post_type'    => 'pago',
			'post_status'  => 'publish',
			'post_title'   => 'pago de prueba',
			'post_content' => '',
		);

		return array(
			'pago_id'     => $pago_id,
			'payment_url' => 'https://pago.example.org/pago/' . $pago_id . '?key=prueba',
		);
	}

	/**
	 * Simula lo que el gateway dispara al confirmar el cobro (webhook del TPV).
	 *
	 * @param int                 $pago_id   Pago.
	 * @param string              $origin    Origen.
	 * @param int                 $origin_id Identificador en el origen.
	 * @param array<string,mixed> $meta      Meta del pago.
	 * @return void
	 */
	function mock_webhook_completed( $pago_id, $origin, $origin_id, $meta = array() ) {
		do_action( 'convoca_payment_completed', $pago_id, $origin, $origin_id, $meta );
	}

	/**
	 * Simula un cobro rechazado.
	 *
	 * @param int    $pago_id Pago.
	 * @param string $code    Código de respuesta.
	 * @return void
	 */
	function mock_webhook_failed( $pago_id, $code ) {
		do_action( 'convoca_payment_failed', $pago_id, $code );
	}
}
