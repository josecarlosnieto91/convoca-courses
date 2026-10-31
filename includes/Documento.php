<?php
/**
 * Validación de NIF, NIE y CIF españoles.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprueba que un documento fiscal es formalmente válido.
 *
 * Sin esto, una factura con «123» se acepta y el error se descubre cuando hay que corregir el registro
 * a mano (courses#40). No comprueba que el documento exista: solo el formato y la letra de control.
 */
class Documento {

	/**
	 * Letras de control del DNI/NIE.
	 */
	private const LETRAS = 'TRWAGMYFPDXBNJZSQVHLCKE';

	/**
	 * Letras válidas para el control de un CIF.
	 */
	private const CIF_CONTROL_LETRA = 'JABCDEFGHI';

	/**
	 * ¿Es un NIF, NIE o CIF formalmente válido?
	 *
	 * @param string $documento Documento tal cual lo escribió la persona.
	 * @return bool
	 */
	public static function valido( string $documento ): bool {
		$documento = self::limpiar( $documento );

		if ( '' === $documento ) {
			return false;
		}

		if ( preg_match( '/^\d{8}[A-Z]$/', $documento ) ) {
			return self::dni_valido( $documento );
		}

		if ( preg_match( '/^[XYZ]\d{7}[A-Z]$/', $documento ) ) {
			return self::nie_valido( $documento );
		}

		if ( preg_match( '/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $documento ) ) {
			return self::cif_valido( $documento );
		}

		return false;
	}

	/**
	 * Normaliza: mayúsculas, sin espacios ni guiones.
	 *
	 * @param string $documento Documento.
	 * @return string
	 */
	public static function limpiar( string $documento ): string {
		return (string) preg_replace( '/[\s\-\.]/', '', strtoupper( trim( $documento ) ) );
	}

	/**
	 * DNI: ocho dígitos y la letra que toca.
	 *
	 * @param string $documento Documento ya normalizado.
	 * @return bool
	 */
	private static function dni_valido( string $documento ): bool {
		$numero = (int) substr( $documento, 0, 8 );
		$letra  = substr( $documento, 8, 1 );

		return self::LETRAS[ $numero % 23 ] === $letra;
	}

	/**
	 * NIE: X, Y o Z como si fueran 0, 1 y 2, y la misma letra que el DNI.
	 *
	 * @param string $documento Documento ya normalizado.
	 * @return bool
	 */
	private static function nie_valido( string $documento ): bool {
		$prefijo = strtr(
			substr( $documento, 0, 1 ),
			array(
				'X' => '0',
				'Y' => '1',
				'Z' => '2',
			)
		);
		$numero  = (int) ( $prefijo . substr( $documento, 1, 7 ) );
		$letra   = substr( $documento, 8, 1 );

		return self::LETRAS[ $numero % 23 ] === $letra;
	}

	/**
	 * CIF: control por dígitos pares, impares doblados e inicial.
	 *
	 * @param string $documento Documento ya normalizado.
	 * @return bool
	 */
	private static function cif_valido( string $documento ): bool {
		$digitos = substr( $documento, 1, 7 );
		$control = substr( $documento, 8, 1 );
		$suma    = 0;

		for ( $i = 0; $i < 7; $i++ ) {
			$digito = (int) $digitos[ $i ];

			if ( 0 === $i % 2 ) {
				$doble = $digito * 2;
				$suma += (int) ( $doble / 10 ) + ( $doble % 10 );
				continue;
			}

			$suma += $digito;
		}

		$unidad = ( 10 - ( $suma % 10 ) ) % 10;

		// Unos CIF exigen dígito, otros letra y otros admiten las dos formas.
		$letra = self::CIF_CONTROL_LETRA[ $unidad ];

		return (string) $unidad === $control || $letra === $control;
	}
}
