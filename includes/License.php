<?php
/**
 * Licencia PRO del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puerta de las funciones PRO.
 *
 * Todo lo PRO del módulo pasa por aquí. Sin Convoca Core no hay licencia y el módulo degrada a
 * gratis sin error fatal: el producto tiene que poder instalarse en un WordPress mínimo.
 */
class License {

	/**
	 * Feature del módulo dentro del circuito de licencias del ecosistema.
	 */
	public const FEATURE = 'courses';

	/**
	 * Días de gracia tras caducar la licencia antes de cortar el vídeo.
	 */
	public const GRACE_DAYS = 30;

	/**
	 * ¿Hay licencia PRO activa para el módulo?
	 *
	 * @return bool
	 */
	public static function has_pro(): bool {
		if ( ! class_exists( '\Convoca\Core\License_Manager' ) ) {
			return false;
		}

		if ( ! method_exists( '\Convoca\Core\License_Manager', 'has_pro' ) ) {
			return false;
		}

		$has = \Convoca\Core\License_Manager::has_pro( self::FEATURE );

		/**
		 * Permite forzar o retirar el PRO (pruebas, entornos gestionados).
		 *
		 * @param bool $has Resultado de la licencia.
		 */
		return (bool) apply_filters( 'convoca_courses_has_pro', (bool) $has );
	}

	/**
	 * ¿Estamos dentro del periodo de gracia desde una fecha de caducidad?
	 *
	 * Función pura para poder probarla sin WordPress.
	 *
	 * @param string $expires Fecha de caducidad en formato Y-m-d o vacío.
	 * @param int    $now     Marca de tiempo actual.
	 * @return bool
	 */
	public static function in_grace( string $expires, int $now ): bool {
		if ( '' === $expires ) {
			return false;
		}

		$timestamp = strtotime( $expires );
		if ( false === $timestamp ) {
			return false;
		}

		return $now <= ( $timestamp + ( self::GRACE_DAYS * DAY_IN_SECONDS ) );
	}

	/**
	 * ¿Se puede reproducir vídeo ahora mismo?
	 *
	 * Con licencia activa sí. Dentro de la gracia tras caducar, sí. Fuera, no: el texto y el PDF
	 * siguen disponibles, el vídeo deja de ofrecerse.
	 *
	 * @return bool
	 */
	public static function video_allowed(): bool {
		if ( self::has_pro() ) {
			return true;
		}

		$expires = '';
		if ( class_exists( '\Convoca\Core\License_Manager' ) && method_exists( '\Convoca\Core\License_Manager', 'get_license' ) ) {
			$license = \Convoca\Core\License_Manager::get_license();
			if ( isset( $license['expires'] ) ) {
				$expires = (string) $license['expires'];
			}
		}

		return self::in_grace( $expires, time() );
	}
}
