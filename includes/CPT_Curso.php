<?php
/**
 * CPT: curso.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipo de contenido `convoca_curso`.
 */
class CPT_Curso extends CPT_Base {

	/**
	 * Slug del tipo de contenido.
	 */
	public const TYPE = 'convoca_curso';

	/**
	 * Meta keys del curso, sin prefijo.
	 */
	public const META_KEYS = array(
		'precio',
		'moneda',
		'plazas',
		'certificado',
		'desbloqueo_secuencial',
	);

	/**
	 * Argumentos del registro.
	 *
	 * @return array<string, mixed>
	 */
	protected static function args(): array {
		return array(
			'labels'             => self::labels( __( 'Curso', 'convoca-courses' ), __( 'Cursos', 'convoca-courses' ) ),
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'menu_position'      => 26,
			'has_archive'        => true,
			'rewrite'            => array( 'slug' => 'cursos' ),
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'capability_type'    => 'post',
			'capabilities'       => self::capabilities(),
			'map_meta_cap'       => true,
			'publicly_queryable' => true,
		);
	}

	/**
	 * Meta del curso.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	protected static function meta(): array {
		return array(
			'precio'                 => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return max( 0.0, (float) $value );
				},
			),
			'moneda'                 => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					$value = strtoupper( substr( (string) $value, 0, 3 ) );
					return '' === $value ? 'EUR' : $value;
				},
			),
			'plazas'                 => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'certificado'            => array(
				'type'     => 'boolean',
				'sanitize' => static function ( $value ) {
					return (bool) $value;
				},
			),
			'desbloqueo_secuencial'  => array(
				'type'     => 'boolean',
				'sanitize' => static function ( $value ) {
					return (bool) $value;
				},
			),
		);
	}

	/**
	 * Precio del curso.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return float
	 */
	public static function price( int $course_id ): float {
		return self::get_float( $course_id, 'precio', 0.0 );
	}

	/**
	 * Moneda del curso (ISO 4217, tres letras).
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function currency( int $course_id ): string {
		return self::get_string( $course_id, 'moneda', 'EUR' );
	}

	/**
	 * Plazas disponibles. 0 significa sin límite.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return int
	 */
	public static function seats( int $course_id ): int {
		return self::get_int( $course_id, 'plazas', 0 );
	}

	/**
	 * Si el curso emite certificado.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return bool
	 */
	public static function issues_certificate( int $course_id ): bool {
		return self::get_bool( $course_id, 'certificado', false );
	}

	/**
	 * Si las lecciones se desbloquean en orden.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return bool
	 */
	public static function is_sequential( int $course_id ): bool {
		return self::get_bool( $course_id, 'desbloqueo_secuencial', false );
	}

	/**
	 * Precio con formato, listo para pintar.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function formatted_price( int $course_id ): string {
		return self::format_amount( self::price( $course_id ), self::currency( $course_id ) );
	}

	/**
	 * Formatea un importe. Separado para poder probarlo sin WordPress.
	 *
	 * @param float  $amount   Importe.
	 * @param string $currency Moneda ISO 4217.
	 * @return string
	 */
	public static function format_amount( float $amount, string $currency ): string {
		$formatted = number_format_i18n( $amount, 2 );
		return trim( $formatted . ' ' . strtoupper( $currency ) );
	}
}
