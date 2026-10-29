<?php
/**
 * Condiciones de venta publicables.
 *
 * La plantilla vive en `templates/condiciones.md` con marcadores; los datos los pone cada organización en
 * Ajustes, así que el mismo plugin sirve a cualquier entidad sin tocar código.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Texto legal y su shortcode.
 */
class Condiciones {

	/**
	 * Shortcode público.
	 */
	public const SHORTCODE = 'convoca_condiciones';

	/**
	 * Opción con el texto propio de la instalación.
	 */
	public const OPTION = 'convoca_courses_condiciones_texto';

	/**
	 * Texto con los datos de la entidad ya puestos.
	 *
	 * @return string
	 */
	public static function texto(): string {
		$propio = (string) get_option( self::OPTION, '' );

		if ( '' !== trim( $propio ) ) {
			return Entidad::sustituir( $propio );
		}

		return Entidad::sustituir( self::plantilla() );
	}

	/**
	 * Texto de fábrica, sin sustituir.
	 *
	 * @return string
	 */
	public static function plantilla(): string {
		$ruta = dirname( __DIR__ ) . '/templates/condiciones.md';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		return is_readable( $ruta ) ? (string) file_get_contents( $ruta ) : '';
	}

	/**
	 * Pinta el texto como HTML.
	 *
	 * @return string
	 */
	public static function html(): string {
		$texto = self::texto();

		if ( '' === $texto ) {
			return '';
		}

		return wpautop( esc_html( $texto ) );
	}

	/**
	 * Shortcode: `[convoca_condiciones]`.
	 *
	 * @return string
	 */
	public static function render(): string {
		if ( ! Entidad::completa() ) {
			if ( current_user_can( 'manage_options' ) || Access::can_manage() ) {
				return '<div class="convoca-alert convoca-alert--warning">'
					. esc_html__( 'Faltan el nombre, el NIF o el correo de desistimiento de la entidad. Rellénalos en Cursos → Ajustes antes de publicar esta página.', 'convoca-courses' )
					. '</div>';
			}

			return '';
		}

		return '<div class="convoca-condiciones">' . self::html() . '</div>';
	}
}
