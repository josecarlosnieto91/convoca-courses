<?php
/**
 * Doble de `Convoca\Core\Features`: solo lo que mira el cobro (si la pasarela está activa).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\\Core\\Features' ) ) {
	/**
	 * Funciones activas del sitio (doble).
	 */
	class Features {

		/**
		 * ¿Está activa la pasarela de pago?
		 *
		 * @return bool
		 */
		public static function is_gateway_active() {
			return (bool) ( $GLOBALS['convoca_courses_gateway_activo'] ?? false );
		}
	}
}
