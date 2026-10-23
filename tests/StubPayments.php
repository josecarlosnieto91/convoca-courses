<?php
/**
 * Doble de `Convoca\Core\Payments`: la fachada de pagos del núcleo.
 *
 * Deja decidir si está disponible y qué devuelve al crear un pago, y apunta los argumentos con los que
 * se le llamó.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\\Core\\Payments' ) ) {
	/**
	 * Fachada de pagos del núcleo (doble).
	 */
	class Payments {

		/**
		 * Pagos creados.
		 *
		 * @var array<int, array>
		 */
		public static $creados = array();

		/**
		 * ¿Está disponible la fachada?
		 *
		 * @return bool
		 */
		public static function is_available() {
			return (bool) ( $GLOBALS['convoca_courses_payments_disponible'] ?? false );
		}

		/**
		 * Crea un pago.
		 *
		 * @param array $args Argumentos.
		 * @return array|\WP_Error
		 */
		public static function create( $args ) {
			self::$creados[] = $args;

			if ( isset( $GLOBALS['convoca_courses_pago_error'] ) && $GLOBALS['convoca_courses_pago_error'] ) {
				return new \WP_Error( 'pago_fallido', 'No se pudo crear el pago.' );
			}

			return array(
				'pago_id'     => (int) ( $GLOBALS['convoca_courses_pago_id'] ?? 555 ),
				'payment_url' => (string) ( $GLOBALS['convoca_courses_pago_url'] ?? 'https://pagos.example.test/555' ),
			);
		}

		/**
		 * Enlace del recibo de un pago.
		 *
		 * @param int $pago_id Pago.
		 * @return string
		 */
		public static function receipt_url( $pago_id ) {
			return 'https://pagos.example.test/recibo/' . (int) $pago_id;
		}
	}
}
