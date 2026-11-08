<?php
/**
 * Convoca Courses
 *
 * @package    Convoca\Courses
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Conversión de fechas del sitio a marcas de tiempo.
 *
 * Las fechas de los cursos («2026-12-31») son fechas del sitio, no UTC: hay que leerlas en la zona
 * configurada en WordPress. `current_time( 'timestamp' )` devolvía la hora local como si fuera UTC y
 * contradecía a `strtotime()`, que interpreta en UTC: las ventanas de matrícula y los plazos de entrega
 * se adelantaban o atrasaban según la zona y el cambio de hora (issue #47).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fechas locales del sitio.
 */
final class Fechas {

	/**
	 * Convierte una fecha del sitio a marca de tiempo UTC.
	 *
	 * @param string $fecha Fecha en formato de WordPress (Y-m-d, o Y-m-d H:i:s).
	 * @param string $hora  Hora a usar si la fecha no trae una.
	 * @return int Marca de tiempo, o 0 si la fecha no es válida.
	 */
	public static function a_timestamp( string $fecha, string $hora = '00:00:00' ): int {
		$fecha = trim( $fecha );
		if ( '' === $fecha ) {
			return 0;
		}

		// Si ya trae hora, no se le añade otra.
		if ( ! preg_match( '/\d{1,2}:\d{2}/', $fecha ) ) {
			$fecha .= ' ' . $hora;
		}

		try {
			$momento = new \DateTimeImmutable( $fecha, wp_timezone() );
		} catch ( \Exception $e ) {
			return 0;
		}

		return $momento->getTimestamp();
	}

	/**
	 * «Ahora», en UTC.
	 *
	 * Sustituye a `current_time( 'timestamp' )`: para comparar con marcas de tiempo hay que usar el reloj
	 * UTC, que es el que devuelven `time()` y `getTimestamp()`.
	 *
	 * @return int
	 */
	public static function ahora(): int {
		// `apply_filters` permite fijar el reloj en las pruebas; en producción no hay quien lo escuche.
		return (int) apply_filters( 'convoca_courses_ahora', time() );
	}
}
