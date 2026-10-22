<?php
/**
 * Base común de los tipos de contenido del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registro perezoso y meta tipada compartida por los CPT del módulo.
 */
abstract class CPT_Base {

	/**
	 * Prefijo de todas las meta keys del módulo.
	 */
	public const META_PREFIX = '_convoca_';

	/**
	 * Slug del tipo de contenido. Lo define cada hija.
	 */
	public const TYPE = '';

	/**
	 * Registra el tipo de contenido y su meta.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type( static::TYPE, static::args() );

		foreach ( static::meta() as $key => $definition ) {
			register_post_meta(
				static::TYPE,
				self::META_PREFIX . $key,
				array(
					'type'              => $definition['type'],
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $definition['sanitize'],
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Definición de las meta keys: tipo y saneador.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array();
	}

	/**
	 * Argumentos de register_post_type().
	 *
	 * @return array<string, mixed>
	 */
	abstract protected static function args(): array;

	/**
	 * Etiquetas estándar en castellano, traducibles.
	 *
	 * @param string $singular Singular.
	 * @param string $plural   Plural.
	 * @return array<string, string>
	 */
	protected static function labels( string $singular, string $plural ): array {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			/* translators: %s: plural name of the post type. */
			'all_items'          => sprintf( __( 'Todos: %s', 'convoca-courses' ), $plural ),
			/* translators: %s: singular name of the post type. */
			'add_new_item'       => sprintf( __( 'Añadir %s', 'convoca-courses' ), $singular ),
			/* translators: %s: singular name of the post type. */
			'edit_item'          => sprintf( __( 'Editar %s', 'convoca-courses' ), $singular ),
			/* translators: %s: singular name of the post type. */
			'new_item'           => sprintf( __( 'Nuevo %s', 'convoca-courses' ), $singular ),
			/* translators: %s: singular name of the post type. */
			'view_item'          => sprintf( __( 'Ver %s', 'convoca-courses' ), $singular ),
			/* translators: %s: plural name of the post type. */
			'search_items'       => sprintf( __( 'Buscar %s', 'convoca-courses' ), $plural ),
			/* translators: %s: plural name of the post type. */
			'not_found'          => sprintf( __( 'No hay %s', 'convoca-courses' ), $plural ),
			/* translators: %s: plural name of the post type. */
			'not_found_in_trash' => sprintf( __( 'No hay %s en la papelera', 'convoca-courses' ), $plural ),
		);
	}

	/** Capacidad por entrada para editar; se traduce a la puerta del módulo. */
	public const CAP_EDIT_POST = 'convoca_edit_curso';

	/** Capacidad por entrada para leer; se traduce a la primitiva global `read`. */
	public const CAP_READ_POST = 'convoca_read_curso';

	/** Capacidad por entrada para borrar; se traduce a la puerta del módulo. */
	public const CAP_DELETE_POST = 'convoca_delete_curso';

	/**
	 * Traduce las capacidades por entrada del módulo a las capacidades reales.
	 *
	 * Los tres nombres son propios (no globales) para no contaminar $post_type_meta_caps, y aquí
	 * recuperan el significado que les toca: leer es `read`, editar y borrar son la puerta del
	 * módulo. El nombre es común a los seis tipos porque la traducción no depende del tipo.
	 *
	 * @param array<int, string> $caps Capacidades primitivas calculadas por WordPress.
	 * @param string             $cap  Capacidad que se está comprobando.
	 * @return array<int, string>
	 */
	public static function bridge_meta_caps( $caps, $cap ) {
		if ( self::CAP_READ_POST === $cap ) {
			return array( 'read' );
		}

		if ( self::CAP_EDIT_POST === $cap || self::CAP_DELETE_POST === $cap ) {
			return array( 'convoca_manage_courses' );
		}

		return $caps;
	}

	/**
	 * Mapa de capabilities del tipo de contenido.
	 *
	 * @return array<string, string>
	 */
	protected static function capabilities(): array {
		// CUIDADO: las tres primitivas POR ENTRADA no pueden ser capacidades globales. WordPress
		// mete en $post_type_meta_caps el valor de edit_post/read_post/delete_post de cada tipo
		// registrado, y a partir de ahí cualquier current_user_can() de esa capacidad SIN id de
		// entrada se resuelve como do_not_allow. Con 'read_post' => 'read' el sitio entero pierde
		// el acceso al panel (administrador incluido): medido en la demo el 22-10-2026
		// (map_meta_cap('read') devolvía ["do_not_allow"] con el módulo activo y ["read"] sin él).
		// Por eso van con nombre propio y se traducen a la puerta real en bridge_meta_caps().
		return array(
			'edit_post'              => self::CAP_EDIT_POST,
			'read_post'              => self::CAP_READ_POST,
			'delete_post'            => self::CAP_DELETE_POST,
			'edit_posts'             => 'convoca_manage_courses',
			'edit_others_posts'      => 'convoca_manage_courses',
			'edit_private_posts'     => 'convoca_manage_courses',
			'edit_published_posts'   => 'convoca_manage_courses',
			'publish_posts'          => 'convoca_manage_courses',
			'read_private_posts'     => 'convoca_manage_courses',
			'delete_posts'           => 'convoca_manage_courses',
			'delete_others_posts'    => 'convoca_manage_courses',
			'delete_private_posts'   => 'convoca_manage_courses',
			'delete_published_posts' => 'convoca_manage_courses',
		);
	}

	/**
	 * Lee una meta entera del módulo.
	 *
	 * @param int    $post_id Identificador del post.
	 * @param string $key     Clave sin prefijo.
	 * @param int    $fallback Valor por defecto.
	 * @return int
	 */
	public static function get_int( int $post_id, string $key, int $fallback = 0 ): int {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		if ( '' === $value || null === $value || false === $value ) {
			return $fallback;
		}
		return (int) $value;
	}

	/**
	 * Lee una meta de texto del módulo.
	 *
	 * @param int    $post_id Identificador del post.
	 * @param string $key     Clave sin prefijo.
	 * @param string $fallback Valor por defecto.
	 * @return string
	 */
	public static function get_string( int $post_id, string $key, string $fallback = '' ): string {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		if ( '' === $value || null === $value || false === $value ) {
			return $fallback;
		}
		return (string) $value;
	}

	/**
	 * Lee una meta booleana del módulo.
	 *
	 * @param int    $post_id Identificador del post.
	 * @param string $key     Clave sin prefijo.
	 * @param bool   $fallback Valor por defecto.
	 * @return bool
	 */
	public static function get_bool( int $post_id, string $key, bool $fallback = false ): bool {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		if ( '' === $value || null === $value || false === $value ) {
			return $fallback;
		}
		return (bool) $value;
	}

	/**
	 * Lee una meta decimal del módulo.
	 *
	 * @param int    $post_id Identificador del post.
	 * @param string $key     Clave sin prefijo.
	 * @param float  $fallback Valor por defecto.
	 * @return float
	 */
	public static function get_float( int $post_id, string $key, float $fallback = 0.0 ): float {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		if ( '' === $value || null === $value || false === $value ) {
			return $fallback;
		}
		return (float) $value;
	}
}
