<?php
/**
 * Doble del cerrojo del núcleo (`Convoca\Core\Utils`).
 *
 * La matrícula con plazas limitadas toma un cerrojo para no vender dos veces la última plaza. El
 * doble deja decidir si se consigue y apunta cuándo se toma y cuándo se suelta.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\\Core\\Utils' ) ) {
	/**
	 * Utilidades del núcleo (doble del cerrojo).
	 */
	class Utils {

		/**
		 * Cerrojos tomados.
		 *
		 * @var array<int, array>
		 */
		public static $tomados = array();

		/**
		 * Cerrojos soltados.
		 *
		 * @var array<int, string>
		 */
		public static $soltados = array();

		/**
		 * Toma un cerrojo.
		 *
		 * @param string $clave    Clave.
		 * @param int    $segundos Segundos.
		 * @return bool
		 */
		public static function acquire_lock( $clave, $segundos = 10 ) {
			self::$tomados[] = array( (string) $clave, (int) $segundos );
			return (bool) ( $GLOBALS['convoca_courses_lock_ok'] ?? true );
		}

		/**
		 * Suelta un cerrojo.
		 *
		 * @param string $clave Clave.
		 * @return bool
		 */
		public static function release_lock( $clave ) {
			self::$soltados[] = (string) $clave;
			return true;
		}
	}
}
