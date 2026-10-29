<?php
/**
 * Datos de la entidad que vende los cursos.
 *
 * No se escriben en el código ni en el texto legal: cada organización que instale el plugin los rellena en
 * Ajustes, y las condiciones de venta los toman de ahí.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emisor de las condiciones de venta.
 */
class Entidad {

	/**
	 * Opción con los datos.
	 */
	public const OPTION = 'convoca_courses_entidad';

	/**
	 * Campos y su etiqueta.
	 *
	 * @return array<string, string>
	 */
	public static function campos(): array {
		return array(
			'nombre'              => __( 'Nombre o razón social', 'convoca-courses' ),
			'nif'                 => __( 'NIF / CIF', 'convoca-courses' ),
			'email_desistimiento' => __( 'Correo para desistimientos', 'convoca-courses' ),
			'email_avisos'        => __( 'Correo para avisos (vacío: el administrador de WordPress)', 'convoca-courses' ),
			'direccion'           => __( 'Dirección postal', 'convoca-courses' ),
			'telefono'            => __( 'Teléfono de contacto', 'convoca-courses' ),
		);
	}

	/**
	 * Datos guardados, con todas las claves.
	 *
	 * @return array<string, string>
	 */
	public static function datos(): array {
		$guardado = get_option( self::OPTION, array() );
		$guardado = is_array( $guardado ) ? $guardado : array();
		$datos    = array();

		foreach ( array_keys( self::campos() ) as $campo ) {
			$datos[ $campo ] = isset( $guardado[ $campo ] ) ? (string) $guardado[ $campo ] : '';
		}

		return $datos;
	}

	/**
	 * Campos obligatorios para publicar las condiciones.
	 *
	 * @return array<int, string>
	 */
	public static function obligatorios(): array {
		return array( 'nombre', 'nif', 'email_desistimiento' );
	}

	/**
	 * Campos obligatorios que faltan por rellenar.
	 *
	 * @return array<int, string>
	 */
	public static function faltan(): array {
		$datos  = self::datos();
		$faltan = array();

		foreach ( self::obligatorios() as $campo ) {
			if ( '' === trim( $datos[ $campo ] ) ) {
				$faltan[] = $campo;
			}
		}

		return $faltan;
	}

	/**
	 * ¿Están los datos mínimos para publicar las condiciones?
	 *
	 * @return bool
	 */
	public static function completa(): bool {
		return array() === self::faltan();
	}

	/**
	 * Saneado de los datos enviados.
	 *
	 * @param mixed $input Valores.
	 * @return array<string, string>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$datos = array();

		$datos['nombre']              = isset( $input['nombre'] ) ? sanitize_text_field( (string) $input['nombre'] ) : '';
		$datos['nif']                 = isset( $input['nif'] ) ? strtoupper( sanitize_text_field( (string) $input['nif'] ) ) : '';
		$datos['email_desistimiento'] = isset( $input['email_desistimiento'] ) ? sanitize_email( (string) $input['email_desistimiento'] ) : '';
		$datos['email_avisos']        = isset( $input['email_avisos'] ) ? sanitize_email( (string) $input['email_avisos'] ) : '';
		$datos['direccion']           = isset( $input['direccion'] ) ? sanitize_text_field( (string) $input['direccion'] ) : '';
		$datos['telefono']            = isset( $input['telefono'] ) ? sanitize_text_field( (string) $input['telefono'] ) : '';

		return $datos;
	}

	/**
	 * Sustituye los marcadores del texto legal por los datos de la entidad.
	 *
	 * @param string $texto Texto con marcadores.
	 * @return string
	 */
	public static function sustituir( string $texto ): string {
		$datos                        = self::datos();
		$datos['email_desistimiento'] = '' !== $datos['email_desistimiento']
			? $datos['email_desistimiento']
			: __( '(correo de desistimiento sin configurar)', 'convoca-courses' );

		foreach ( $datos as $campo => $valor ) {
			$texto = str_replace( '{{' . $campo . '}}', $valor, $texto );
		}

		return $texto;
	}
}
