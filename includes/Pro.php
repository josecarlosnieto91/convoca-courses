<?php
/**
 * Puertas de las funciones PRO del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reparto gratis / PRO.
 *
 * La evaluación es gratuita: exámenes, tareas, notas, certificado con nota y fechas. Lo que se paga
 * es la capa de organización. Cada puerta se comprueba **en el servidor**: esconder el botón no
 * basta, y por eso todas las funciones PRO preguntan aquí antes de hacer nada.
 */
class Pro {

	/**
	 * Funciones PRO y su nombre legible.
	 */
	public const FEATURES = array(
		'precio_socio'     => 'Precio distinto para socios',
		'convocatorias'    => 'Convocatorias con plazas y fechas',
		'recordatorios'    => 'Recordatorios automáticos',
		'tutores'          => 'Varios correctores y corrección en lote',
		'certificado_qr'   => 'Certificado con QR y plantilla propia',
		'banco_compartido' => 'Banco de preguntas compartido e importación CSV',
		'correo_grupo'     => 'Correo al grupo de alumnado',
	);

	/**
	 * ¿Está activa una función PRO?
	 *
	 * @param string $feature Clave de `FEATURES`.
	 * @return bool
	 */
	public static function enabled( string $feature ): bool {
		if ( ! array_key_exists( $feature, self::FEATURES ) ) {
			return false;
		}

		return License::has_pro();
	}

	/**
	 * Listado de funciones PRO con su estado.
	 *
	 * @return array<string, array{nombre: string, activa: bool}>
	 */
	public static function features(): array {
		$salida = array();

		foreach ( self::FEATURES as $clave => $nombre ) {
			$salida[ $clave ] = array(
				'nombre' => $nombre,
				'activa' => self::enabled( $clave ),
			);
		}

		return $salida;
	}

	/**
	 * Funciones PRO que están apagadas.
	 *
	 * @return array<int, string>
	 */
	public static function locked(): array {
		$apagadas = array();

		foreach ( self::FEATURES as $clave => $nombre ) {
			if ( ! self::enabled( $clave ) ) {
				$apagadas[] = $nombre;
			}
		}

		return $apagadas;
	}

	/**
	 * ¿Puede esta persona corregir entregas?
	 *
	 * Sin PRO solo corrige quien gestiona el módulo. Con PRO entran los tutores: usuarios a los que
	 * se les ha dado la capability `convoca_grade_courses`, sin tocar el resto del módulo.
	 *
	 * @return bool
	 */
	public static function can_grade(): bool {
		if ( Access::can_manage() ) {
			return true;
		}

		if ( ! self::enabled( 'tutores' ) ) {
			return false;
		}

		return current_user_can( 'convoca_grade_courses' );
	}

	/**
	 * ¿Se permite más de un corrector?
	 *
	 * @return bool
	 */
	public static function multiple_graders(): bool {
		return self::enabled( 'tutores' );
	}
}
