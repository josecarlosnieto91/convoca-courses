<?php
/**
 * Contrato con `members`: ¿esta persona es socio activo?
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pregunta por la condición de socio y no por dentro de otro plugin.
 *
 * `courses` no depende de `members`: lanza un filtro y, si nadie contesta, la respuesta es «no es
 * socio». Sin `members` activo todo el mundo paga el precio general y no hay fatal.
 */
class Members_Bridge {

	/**
	 * Filtro que implementa `members`.
	 */
	public const FILTER = 'convoca_es_socio_activo';

	/**
	 * Filtro con los datos de la ficha del socio, para no pedirlos otra vez al matricularse.
	 */
	public const FILTER_DATOS = 'convoca_datos_socio';

	/**
	 * ¿Es socio activo?
	 *
	 * @param int $user_id Persona.
	 * @return bool
	 */
	public static function es_socio_activo( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		/**
		 * ¿Es socio activo esta persona?
		 *
		 * @param bool $es_socio Respuesta por defecto: no.
		 * @param int  $user_id  Usuario de WordPress.
		 */
		return (bool) apply_filters( self::FILTER, false, $user_id );
	}

	/**
	 * ¿Hay alguien que conteste al contrato?
	 *
	 * Sirve para el panel: si nadie contesta, no se ofrecen precios de socio en vez de ofrecerlos y
	 * cobrar mal.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return has_filter( self::FILTER );
	}

	/**
	 * Datos de la ficha del socio, si `members` los ofrece.
	 *
	 * Devuelve solo lo que se puede usar para identificar a la persona en una matrícula. Si el
	 * correo falta, el resultado es vacío: sin correo no hay identidad y el formulario tendría que
	 * pedirlo igualmente.
	 *
	 * @param int $user_id Usuario de WordPress.
	 * @return array{email:string,nombre:string,dni:string,direccion:string,municipio:string}|array{}
	 */
	public static function datos_socio( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		/**
		 * Datos de la ficha del socio para rellenar una matrícula.
		 *
		 * @param array $datos   Vacío por defecto.
		 * @param int   $user_id Usuario de WordPress.
		 */
		$datos = apply_filters( self::FILTER_DATOS, array(), $user_id );

		if ( ! is_array( $datos ) ) {
			return array();
		}

		$email = isset( $datos['email'] ) ? sanitize_email( (string) $datos['email'] ) : '';

		if ( '' === $email ) {
			return array();
		}

		return array(
			'email'     => $email,
			'nombre'    => isset( $datos['nombre'] ) ? sanitize_text_field( (string) $datos['nombre'] ) : '',
			'dni'       => isset( $datos['dni'] ) ? strtoupper( sanitize_text_field( (string) $datos['dni'] ) ) : '',
			'direccion' => isset( $datos['direccion'] ) ? sanitize_text_field( (string) $datos['direccion'] ) : '',
			'municipio' => isset( $datos['municipio'] ) ? sanitize_text_field( (string) $datos['municipio'] ) : '',
		);
	}

	/**
	 * ¿Hay datos de socio para esta persona?
	 *
	 * @param int $user_id Usuario de WordPress.
	 * @return bool
	 */
	public static function tiene_datos( int $user_id ): bool {
		return array() !== self::datos_socio( $user_id );
	}
}
