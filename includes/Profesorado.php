<?php
/**
 * Enlace firmado de un solo uso para el profesorado, sin cuenta de WordPress.
 *
 * Ahora mismo el curso corrige solo: el examen es tipo test y la nota se calcula automáticamente. Esta pieza
 * queda preparada para cuando haga falta que una persona corrija: un enlace por correo, con caducidad, que
 * identifica al docente y al curso sin pedirle una cuenta.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tokens de acceso para docentes.
 */
class Profesorado {

	/**
	 * Vida del enlace, en segundos.
	 */
	public const VIDA = 172800; // 48 horas.

	/**
	 * Secreto del token.
	 *
	 * @return string
	 */
	private static function secreto(): string {
		if ( defined( 'CONVOCA_COURSES_DOCENTE_SECRET' ) ) {
			return (string) CONVOCA_COURSES_DOCENTE_SECRET;
		}

		if ( class_exists( '\Convoca\Core\Utils' ) ) {
			return (string) \Convoca\Core\Utils::get_persistent_salt();
		}

		$guardado = (string) get_option( 'convoca_courses_docente_secret', '' );

		if ( '' === $guardado ) {
			$guardado = wp_generate_password( 64, true, true );
			update_option( 'convoca_courses_docente_secret', $guardado, false );
		}

		return $guardado;
	}

	/**
	 * Firma un acceso para un curso y una caducidad.
	 *
	 * @param int $course_id Curso.
	 * @param int $expira    Marca de tiempo de caducidad.
	 * @return string
	 */
	public static function firma( int $course_id, int $expira ): string {
		return hash_hmac( 'sha256', $course_id . '|' . $expira, self::secreto() );
	}

	/**
	 * Genera el token completo.
	 *
	 * @param int $course_id Curso.
	 * @param int $ahora     Marca de tiempo actual (para las pruebas).
	 * @return string
	 */
	public static function token( int $course_id, int $ahora = 0 ): string {
		$ahora  = $ahora > 0 ? $ahora : time();
		$expira = $ahora + self::VIDA;

		return $course_id . '.' . $expira . '.' . self::firma( $course_id, $expira );
	}

	/**
	 * Comprueba un token y devuelve el curso, o 0.
	 *
	 * @param string $token Token.
	 * @param int    $ahora Marca de tiempo actual.
	 * @return int
	 */
	public static function verificar( string $token, int $ahora = 0 ): int {
		$partes = explode( '.', $token );

		if ( 3 !== count( $partes ) ) {
			return 0;
		}

		$course_id = (int) $partes[0];
		$expira    = (int) $partes[1];
		$ahora     = $ahora > 0 ? $ahora : time();

		if ( $course_id <= 0 || $expira < $ahora ) {
			return 0;
		}

		if ( ! hash_equals( self::firma( $course_id, $expira ), (string) $partes[2] ) ) {
			return 0;
		}

		return $course_id;
	}

	/**
	 * URL de acceso para el curso.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	public static function url( int $course_id ): string {
		return add_query_arg(
			array( 'convoca_docente' => rawurlencode( self::token( $course_id ) ) ),
			home_url( '/' )
		);
	}
}
