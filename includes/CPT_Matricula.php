<?php
/**
 * CPT: matrícula.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_matricula`.
 *
 * Es un registro privado: una fila por persona y curso. No se navega por URL.
 */
class CPT_Matricula extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_matricula';

	/**
	 * Estados posibles.
	 */
	public const STATUSES = array( 'pendiente', 'activa', 'completada', 'cancelada' );

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'              => self::labels( __( 'Matrícula', 'convoca-courses' ), __( 'Matrículas', 'convoca-courses' ) ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . CPT_Curso::TYPE,
			'show_in_rest'        => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'capabilities'        => self::capabilities(),
			'map_meta_cap'        => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
		);
	}

	/**
	 * Meta de la matrícula.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'curso_id'        => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'user_id'         => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'estado'          => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					$value = (string) $value;
					return in_array( $value, CPT_Matricula::STATUSES, true ) ? $value : 'pendiente';
				},
			),
			'importe'         => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return max( 0.0, (float) $value );
				},
			),
			'moneda'          => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return strtoupper( substr( (string) $value, 0, 3 ) );
				},
			),
			'pago'            => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					$value = (string) $value;
					return in_array( $value, array( 'ninguno', 'manual', 'gateway' ), true ) ? $value : 'ninguno';
				},
			),
			'pago_id'         => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'payment_url'     => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return esc_url_raw( (string) $value );
				},
			),
			'referencia_pago' => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return sanitize_text_field( (string) $value );
				},
			),
			'cert_codigo'     => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) );
				},
			),
			'cert_fecha'      => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return sanitize_text_field( (string) $value );
				},
			),
		);
	}

	/**
	 * Estado de una matrícula.
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return string
	 */
	public static function status( int $enrollment_id ): string {
		$status = self::get_string( $enrollment_id, 'estado', 'pendiente' );
		return in_array( $status, self::STATUSES, true ) ? $status : 'pendiente';
	}

	/**
	 * El estado **tal y como está guardado**, sin normalizar.
	 *
	 * `status()` cae a `pendiente` cuando el valor guardado no está en `STATUSES`, y eso está bien para pintar una
	 * pantalla: siempre enseña algo que el código entiende. Pero para **contar plazas** es un engaño, porque
	 * `pendiente` ocupa: una matrícula con un estado heredado (`baja`, por ejemplo) se quedaba con la plaza de otra
	 * persona y no había forma de verlo (courses#3).
	 *
	 * Este método existe para que quien tenga que decidir sobre datos raros pueda distinguir «pendiente de verdad»
	 * de «un estado que no reconozco».
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return string
	 */
	public static function status_almacenado( int $enrollment_id ): string {
		return self::get_string( $enrollment_id, 'estado', '' );
	}

	/**
	 * ¿El estado guardado es uno de los que el módulo reconoce?
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return bool
	 */
	public static function tiene_estado_conocido( int $enrollment_id ): bool {
		return in_array( self::status_almacenado( $enrollment_id ), self::STATUSES, true );
	}

	/**
	 * Curso de la matrícula.
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return int
	 */
	public static function course_id( int $enrollment_id ): int {
		return self::get_int( $enrollment_id, 'curso_id', 0 );
	}

	/**
	 * Persona matriculada.
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return int
	 */
	public static function user_id( int $enrollment_id ): int {
		return self::get_int( $enrollment_id, 'user_id', 0 );
	}
}
