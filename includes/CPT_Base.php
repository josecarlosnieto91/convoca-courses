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

	/**
	 * Mapa de capabilities del tipo de contenido.
	 *
	 * @return array<string, string>
	 */
	protected static function capabilities(): array {
		return array(
			'edit_post'          => 'convoca_manage_courses',
			'read_post'          => 'read',
			'delete_post'        => 'convoca_manage_courses',
			'edit_posts'         => 'convoca_manage_courses',
			'edit_others_posts'  => 'convoca_manage_courses',
			'publish_posts'      => 'convoca_manage_courses',
			'read_private_posts' => 'convoca_manage_courses',
			'delete_posts'       => 'convoca_manage_courses',
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
