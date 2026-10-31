<?php
/**
 * Identidad del alumnado, sin usuario de WordPress.
 *
 * El correo es la clave. Se entra con un enlace de un solo uso y, si el enlace no llega o caduca, con un código
 * de seis cifras del mismo correo. No hay contraseñas y no se crea ningún `WP_User`.
 *
 * Las matrículas anteriores siguen con su usuario de WordPress: esta pieza solo atiende a las nuevas.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alta, acceso y sesión.
 */
class Identidad {

	/**
	 * Marca el correo como confirmado.
	 *
	 * @param int $id Identidad.
	 * @return void
	 */
	public static function marcar_verificada( int $id ): void {
		global $wpdb;

		if ( $id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->update(
			self::tabla(),
			array( 'verificado' => current_time( 'mysql', true ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Firma la cookie de sesión.
	 */
	public const COOKIE = 'convoca_identidad';

	/**
	 * Vida de la sesión, en segundos (30 días).
	 */
	public const SESION = 2592000;

	/**
	 * Vida del enlace y del código, en segundos.
	 */
	// 24 horas: 30 minutos dejaba fuera a quien abría el correo por la noche o lo retomaba al día
	// siguiente, y no había forma de pedir otro enlace desde ninguna página (courses#31).
	public const VIDA = 86400;

	/**
	 * Meta de la matrícula con la identidad vinculada.
	 */
	public const META_MATRICULA = '_convoca_identidad_id';

	/**
	 * Nombre de la tabla con prefijo.
	 *
	 * @return string
	 */
	private static function tabla(): string {
		global $wpdb;

		return $wpdb->prefix . Installer::IDENTIDADES_TABLE;
	}

	/**
	 * Secreto para firmar la cookie.
	 *
	 * @return string
	 */
	private static function secreto(): string {
		return hash_hmac( 'sha256', 'identidad', Profesorado::secreto_publico() );
	}

	/**
	 * Alta o recuperación por correo.
	 *
	 * @param string $email  Correo.
	 * @param string $nombre Nombre (opcional).
	 * @return int Identidad, o 0 si el correo no vale.
	 */
	public static function alta( string $email, string $nombre = '' ): int {
		global $wpdb;

		$email = self::normalizar_email( $email );

		if ( '' === $email ) {
			return 0;
		}

		$existente = self::por_email( $email );
		if ( null !== $existente ) {
			return (int) $existente['id'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->insert(
			self::tabla(),
			array(
				'email'  => $email,
				'nombre' => sanitize_text_field( $nombre ),
				'creado' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Correo normalizado, o cadena vacía si no vale.
	 *
	 * @param string $email Correo.
	 * @return string
	 */
	public static function normalizar_email( string $email ): string {
		$email = strtolower( trim( $email ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return '';
		}

		return $email;
	}

	/**
	 * Identidad por correo.
	 *
	 * @param string $email Correo.
	 * @return array<string, mixed>|null
	 */
	public static function por_email( string $email ): ?array {
		global $wpdb;

		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$fila = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::tabla() . ' WHERE email = %s LIMIT 1', $email ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
			'ARRAY_A'
		);

		return is_array( $fila ) ? $fila : null;
	}

	/**
	 * Identidad por id.
	 *
	 * @param int $id Identidad.
	 * @return array<string, mixed>|null
	 */
	public static function por_id( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$fila = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::tabla() . ' WHERE id = %d LIMIT 1', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
			'ARRAY_A'
		);

		return is_array( $fila ) ? $fila : null;
	}

	/**
	 * Emite enlace y código, y guarda solo sus hashes.
	 *
	 * @param int $id Identidad.
	 * @return array{token: string, codigo: string}
	 */
	public static function emitir( int $id ): array {
		global $wpdb;

		$token  = bin2hex( random_bytes( 32 ) );
		$codigo = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$expira = gmdate( 'Y-m-d H:i:s', time() + self::VIDA );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->update(
			self::tabla(),
			array(
				'token_hash'    => hash( 'sha256', $token ),
				'token_expira'  => $expira,
				'codigo_hash'   => hash( 'sha256', $codigo ),
				'codigo_expira' => $expira,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'token'  => $token,
			'codigo' => $codigo,
		);
	}

	/**
	 * ¿El token es válido y no ha caducado?
	 *
	 * @param int    $id    Identidad.
	 * @param string $token Token.
	 * @return bool
	 */
	public static function token_valido( int $id, string $token ): bool {
		$fila = self::por_id( $id );

		if ( null === $fila || '' === (string) ( $fila['token_hash'] ?? '' ) ) {
			return false;
		}

		if ( strtotime( (string) ( $fila['token_expira'] ?? '' ) . ' UTC' ) < time() ) {
			return false;
		}

		return hash_equals( (string) $fila['token_hash'], hash( 'sha256', $token ) );
	}

	/**
	 * ¿El código es válido y no ha caducado?
	 *
	 * @param int    $id     Identidad.
	 * @param string $codigo Código.
	 * @return bool
	 */
	public static function codigo_valido( int $id, string $codigo ): bool {
		$fila = self::por_id( $id );

		if ( null === $fila || '' === (string) ( $fila['codigo_hash'] ?? '' ) ) {
			return false;
		}

		if ( strtotime( (string) ( $fila['codigo_expira'] ?? '' ) . ' UTC' ) < time() ) {
			return false;
		}

		return hash_equals( (string) $fila['codigo_hash'], hash( 'sha256', trim( $codigo ) ) );
	}

	/**
	 * Gasta el acceso: un token o código solo sirve una vez.
	 *
	 * @param int $id Identidad.
	 * @return void
	 */
	public static function consumir( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->update(
			self::tabla(),
			array(
				'token_hash'    => '',
				'token_expira'  => '0000-00-00 00:00:00',
				'codigo_hash'   => '',
				'codigo_expira' => '0000-00-00 00:00:00',
				'verificado'    => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Firma la cookie de sesión.
	 *
	 * @param int $id     Identidad.
	 * @param int $expira Caducidad.
	 * @return string
	 */
	public static function firma_cookie( int $id, int $expira ): string {
		return hash_hmac( 'sha256', $id . '|' . $expira, self::secreto() );
	}

	/**
	 * Identidad de la sesión actual, o 0.
	 *
	 * @return int
	 */
	public static function actual(): int {
		$cookie = isset( $_COOKIE[ self::COOKIE ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) )
			: '';
		$partes = explode( '|', $cookie );

		if ( 3 !== count( $partes ) ) {
			return 0;
		}

		$id     = (int) $partes[0];
		$expira = (int) $partes[1];

		if ( $id <= 0 || $expira < time() ) {
			return 0;
		}

		if ( ! hash_equals( self::firma_cookie( $id, $expira ), (string) $partes[2] ) ) {
			return 0;
		}

		return $id;
	}
}
