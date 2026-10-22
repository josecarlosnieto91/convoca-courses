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
}
