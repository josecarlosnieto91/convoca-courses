<?php
/**
 * Datos estructurados de la ficha del curso.
 *
 * Un `Course` en JSON-LD es lo que permite que un buscador enseñe el curso con su precio, su entidad y su
 * disponibilidad en vez de un enlace suelto. Va apagado si la ficha no está publicada.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON-LD schema.org/Course.
 */
class Course_SEO {

	/**
	 * Engancha la salida.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( __CLASS__, 'json_ld' ), 20 );
	}

	/**
	 * ¿Es esta petición la de un curso publicado?
	 *
	 * @return bool
	 */
	public static function es_ficha(): bool {
		return function_exists( 'is_singular' ) && is_singular( CPT_Curso::TYPE );
	}

	/**
	 * Pinta el JSON-LD.
	 *
	 * @return void
	 */
	public static function json_ld(): void {
		if ( ! self::es_ficha() ) {
			return;
		}

		$datos = self::datos( (int) get_queried_object_id() );

		if ( array() === $datos ) {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD ya escapado por wp_json_encode.
	}

	/**
	 * Datos del curso para el JSON-LD.
	 *
	 * @param int $course_id Curso.
	 * @return array<string, mixed>
	 */
	public static function datos( int $course_id ): array {
		if ( $course_id <= 0 || 'publish' !== get_post_status( $course_id ) ) {
			return array();
		}

		$entidad = Entidad::datos();
		$precio  = (float) CPT_Curso::price( $course_id );
		$gratis  = 0.0 >= $precio;
		$url     = (string) get_permalink( $course_id );

		$datos = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Course',
			'name'        => (string) get_the_title( $course_id ),
			'description' => wp_strip_all_tags( (string) get_the_excerpt( $course_id ) ),
			'url'         => $url,
			'inLanguage'  => (string) get_bloginfo( 'language' ),
			'provider'    => array(
				'@type' => 'Organization',
				'name'  => '' !== (string) $entidad['nombre'] ? (string) $entidad['nombre'] : (string) get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'offers'      => array(
				'@type'         => 'Offer',
				'category'      => $gratis ? 'Free' : 'Paid',
				'price'         => $gratis ? '0' : number_format( $precio, 2, '.', '' ),
				'priceCurrency' => (string) CPT_Curso::currency( $course_id ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
			),
		);

		if ( '' !== (string) $entidad['nombre'] && '' !== (string) $entidad['nif'] ) {
			$datos['provider']['taxID'] = (string) $entidad['nif'];
		}

		$imagen = get_the_post_thumbnail_url( $course_id, 'large' );
		if ( false !== $imagen && '' !== $imagen ) {
			$datos['image'] = $imagen;
		}

		return $datos;
	}
}
