<?php
/**
 * Stub de la API pública de Convoca Gateway que usa este módulo.
 *
 * El gateway es una dependencia OPCIONAL: no se clona en el CI de este repositorio, así que PHPStan
 * necesita conocer la firma. Solo se declara la función que se llama.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Gateway;

/**
 * Crea un pago y devuelve su identificador y el enlace para pagar.
 *
 * @param array<string, mixed> $args Datos del pago.
 * @return array{pago_id: int, payment_url: string}|\WP_Error
 */
function convoca_gateway_create_payment( array $args ) {
	// El tipo real es la unión: el stub la declara en una variable anotada para que PHPStan no
	// concluya que la rama de error es inalcanzable.
	/** @var array{pago_id: int, payment_url: string}|\WP_Error $result */
	$result = array(
		'pago_id'     => 0,
		'payment_url' => '',
	);

	return $result;
}
