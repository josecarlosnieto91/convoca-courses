<?php
/**
 * Doble de la función del gateway de pago (`Convoca\Gateway\convoca_gateway_create_payment`).
 *
 * El módulo exige que la función exista para considerar la pasarela disponible; el doble delega en la
 * fachada del núcleo para que haya un solo sitio donde se decide qué devuelve un pago.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Gateway;

if ( ! function_exists( 'Convoca\\Gateway\\convoca_gateway_create_payment' ) ) {
	/**
	 * Crea un pago por la función del gateway (doble).
	 *
	 * @param array $args Argumentos del pago.
	 * @return array|\WP_Error
	 */
	function convoca_gateway_create_payment( $args ) {
		return \Convoca\Core\Payments::create( $args );
	}
}
