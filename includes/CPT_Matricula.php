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
