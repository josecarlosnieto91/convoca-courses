<?php
/**
 * Precio efectivo del curso según la condición de socio.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Precio que se le aplica a una persona concreta.
 *
 * La prioridad es una decisión de producto cerrada: **gratis para socios > precio de socio >
 * descuento de socio > precio general**. La parte que decide es pura; la que lee el curso y pregunta
 * a `members` es la de WordPress.
 *
 * Todo esto es PRO: sin licencia, aquí se devuelve siempre el precio general.
 */
class Pricing {

	/**
	 * Motivos posibles del precio aplicado.
	 */
	public const MOTIVOS = array( 'general', 'socio_gratis', 'socio_precio', 'socio_descuento' );

	/**
	 * Precio efectivo, puro.
	 *
	 * @param float      $general   Precio general del curso.
	 * @param float|null $socio     Precio especial de socio; `null` si no hay.
	 * @param float      $descuento Descuento de socio en porcentaje (0-100).
	 * @param bool       $gratis    Si los socios no pagan nada.
	 * @param bool       $es_socio  Si la persona es socio activo.
	 * @return array{precio: float, motivo: string}
	 */
	public static function effective( float $general, ?float $socio, float $descuento, bool $gratis, bool $es_socio ): array {
		$general = round( max( 0.0, $general ), 2 );

		if ( ! $es_socio ) {
			return array(
				'precio' => $general,
				'motivo' => 'general',
			);
		}

		if ( $gratis ) {
			return array(
				'precio' => 0.0,
				'motivo' => 'socio_gratis',
			);
		}

		if ( null !== $socio ) {
			return array(
				'precio' => round( max( 0.0, $socio ), 2 ),
				'motivo' => 'socio_precio',
			);
		}

		$descuento = max( 0.0, min( 100.0, $descuento ) );

		if ( $descuento > 0.0 ) {
			return array(
				'precio' => round( $general * ( 1 - ( $descuento / 100 ) ), 2 ),
				'motivo' => 'socio_descuento',
			);
		}

		return array(
			'precio' => $general,
			'motivo' => 'general',
		);
	}

	/**
	 * Precio y motivo de una persona en un curso.
	 *
	 * @param int $course_id Curso.
	 * @param int $user_id   Persona.
	 * @return array{precio: float, motivo: string}
	 */
	public static function for_user( int $course_id, int $user_id ): array {
		$general = CPT_Curso::price( $course_id );

		// Puerta PRO: sin licencia no hay precio de socio, ni siquiera para quien lo es.
		if ( ! Pro::enabled( 'precio_socio' ) ) {
			return array(
				'precio' => round( $general, 2 ),
				'motivo' => 'general',
			);
		}

		return self::effective(
			$general,
			CPT_Curso::member_price( $course_id ),
			CPT_Curso::member_discount( $course_id ),
			CPT_Curso::members_free( $course_id ),
			Members_Bridge::es_socio_activo( $user_id )
		);
	}

	/**
	 * Etiqueta legible de un motivo.
	 *
	 * Función pura.
	 *
	 * @param string $motivo Motivo.
	 * @return string
	 */
	public static function label( string $motivo ): string {
		switch ( $motivo ) {
			case 'socio_gratis':
				return __( 'gratis por ser socio', 'convoca-courses' );
			case 'socio_precio':
				return __( 'precio de socio', 'convoca-courses' );
			case 'socio_descuento':
				return __( 'descuento de socio', 'convoca-courses' );
			default:
				return __( 'precio general', 'convoca-courses' );
		}
	}
}
