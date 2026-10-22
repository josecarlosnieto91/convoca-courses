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
		'fecha_apertura_matricula',
		'fecha_cierre_matricula',
		'fecha_fin',
		'caducidad_dias',
		'nota_minima',
		'acceso_examen',
		'acceso_examen_porcentaje',
	);

	/**
	 * Modos de acceso al examen: todas las lecciones, las de su módulo, o un porcentaje.
	 */
	public const ACCESO_EXAMEN = array( 'todas', 'modulo', 'porcentaje' );

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
			'precio'                   => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return max( 0.0, (float) $value );
				},
			),
			'moneda'                   => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					$value = strtoupper( substr( (string) $value, 0, 3 ) );
					return '' === $value ? 'EUR' : $value;
				},
			),
			'plazas'                   => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'certificado'              => array(
				'type'     => 'boolean',
				'sanitize' => static function ( $value ) {
					return (bool) $value;
				},
			),
			'desbloqueo_secuencial'    => array(
				'type'     => 'boolean',
				'sanitize' => static function ( $value ) {
					return (bool) $value;
				},
			),
			'fecha_apertura_matricula' => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return self::normalize_date( (string) $value );
				},
			),
			'fecha_cierre_matricula'   => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return self::normalize_date( (string) $value );
				},
			),
			'fecha_fin'                => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return self::normalize_date( (string) $value );
				},
			),
			'caducidad_dias'           => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			),
			'nota_minima'              => array(
				'type'     => 'number',
				'sanitize' => static function ( $value ) {
					return self::normalize_score( (float) $value );
				},
			),
			'acceso_examen'            => array(
				'type'     => 'string',
				'sanitize' => static function ( $value ) {
					return self::normalize_exam_access( (string) $value );
				},
			),
			'acceso_examen_porcentaje' => array(
				'type'     => 'integer',
				'sanitize' => static function ( $value ) {
					return max( 0, min( 100, (int) $value ) );
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
	 * Por defecto **sí**: un curso nuevo se crea secuencial. Los cursos que ya existían antes de la
	 * 0.3.0 se marcan como no secuenciales en la migración, para no encerrar a quien está a mitad.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return bool
	 */
	public static function is_sequential( int $course_id ): bool {
		return self::get_bool( $course_id, 'desbloqueo_secuencial', true );
	}

	/**
	 * Fecha de apertura de matrícula (`Y-m-d`), vacía si no hay ventana.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function enrollment_open_date( int $course_id ): string {
		return self::get_string( $course_id, 'fecha_apertura_matricula', '' );
	}

	/**
	 * Fecha de cierre de matrícula (`Y-m-d`), vacía si no hay ventana.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function enrollment_close_date( int $course_id ): string {
		return self::get_string( $course_id, 'fecha_cierre_matricula', '' );
	}

	/**
	 * Fecha de fin del curso (`Y-m-d`), vacía si el curso es indefinido.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function end_date( int $course_id ): string {
		return self::get_string( $course_id, 'fecha_fin', '' );
	}

	/**
	 * Días de acceso desde la matrícula. 0 = acceso indefinido.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return int
	 */
	public static function expiry_days( int $course_id ): int {
		return max( 0, self::get_int( $course_id, 'caducidad_dias', 0 ) );
	}

	/**
	 * Nota mínima para aprobar (0-100). Por defecto 50.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return float
	 */
	public static function min_score( int $course_id ): float {
		return self::normalize_score( self::get_float( $course_id, 'nota_minima', 50.0 ) );
	}

	/**
	 * Modo de acceso al examen: `todas`, `modulo` o `porcentaje`.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return string
	 */
	public static function exam_access( int $course_id ): string {
		return self::normalize_exam_access( self::get_string( $course_id, 'acceso_examen', 'todas' ) );
	}

	/**
	 * Porcentaje de lecciones exigido cuando el modo es `porcentaje`.
	 *
	 * @param int $course_id Identificador del curso.
	 * @return int
	 */
	public static function exam_access_percent( int $course_id ): int {
		return max( 0, min( 100, self::get_int( $course_id, 'acceso_examen_porcentaje', 100 ) ) );
	}

	/**
	 * Fecha normalizada a `Y-m-d`, o cadena vacía si no se puede interpretar.
	 *
	 * Función pura: la usan los saneadores de metadatos y se prueba sin WordPress.
	 *
	 * @param string $value Fecha escrita por la persona.
	 * @return string
	 */
	public static function normalize_date( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$ts = strtotime( $value );
		if ( false === $ts ) {
			return '';
		}

		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Nota acotada a la escala del módulo (0-100).
	 *
	 * Función pura.
	 *
	 * @param float $value Nota.
	 * @return float
	 */
	public static function normalize_score( float $value ): float {
		return round( max( 0.0, min( 100.0, $value ) ), 2 );
	}

	/**
	 * Modo de acceso al examen válido; si no se reconoce, `todas`.
	 *
	 * Función pura.
	 *
	 * @param string $value Modo escrito por la persona.
	 * @return string
	 */
	public static function normalize_exam_access( string $value ): string {
		$value = strtolower( trim( $value ) );

		return in_array( $value, self::ACCESO_EXAMEN, true ) ? $value : 'todas';
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
