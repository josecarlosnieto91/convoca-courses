<?php
/**
 * Stubs de las clases de Convoca Core que usa este módulo.
 *
 * El núcleo no se clona en el CI de este repositorio (es la dependencia, no el proyecto), así que
 * PHPStan no conocería sus símbolos. Se declara solo lo que se usa, con la firma real.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Core;

if ( class_exists( 'Convoca\Core\Utils', false ) ) {
	return;
}

/**
 * Utilidades del núcleo.
 */
class Utils {

	/**
	 * Toma un lock con caducidad.
	 *
	 * @param string $key Clave.
	 * @param int    $ttl Segundos de vida.
	 * @return bool
	 *
	 * @phpstan-impure
	 */
	public static function acquire_lock( string $key, int $ttl = 60 ): bool {
		return true;
	}

	/**
	 * Libera un lock.
	 *
	 * @param string $key Clave.
	 * @return bool
	 *
	 * @phpstan-impure
	 */
	public static function release_lock( string $key ): bool {
		return true;
	}

	/**
	 * Sal persistente del sitio.
	 *
	 * @return string
	 */
	public static function get_persistent_salt(): string {
		return '';
	}

	/**
	 * ¿Está activa una función del ecosistema?
	 *
	 * @param string $feature Función.
	 * @return bool
	 */
	public static function has_feature( string $feature ): bool {
		return false;
	}
}

/**
 * Gestor de licencias del núcleo.
 */
class License_Manager {

	/**
	 * ¿La licencia cubre esta función?
	 *
	 * @param string $feature Función.
	 * @return bool
	 */
	public static function has_pro( string $feature ): bool {
		return false;
	}

	/**
	 * Licencia guardada.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_license(): array {
		return array();
	}
}

/**
 * Registro del núcleo.
 */
class Logger {

	/**
	 * Aviso de error.
	 *
	 * @param string   $message   Mensaje.
	 * @param string   $context   Contexto.
	 * @param int|null $object_id Objeto.
	 * @return void
	 */
	public static function error( string $message, string $context = 'General', ?int $object_id = null ): void {}
}
