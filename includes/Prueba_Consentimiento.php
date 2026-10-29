<?php
/**
 * Prueba del consentimiento, guardada **aparte** de la matrícula y cinco años.
 *
 * Dos motivos para no dejarla en el `postmeta` de la matrícula:
 *
 *  1. **Durabilidad**: si la matrícula se borra (baja, desistimiento, supresión de datos), la prueba del
 *     consentimiento del artículo 103.m del TRLGDCU desaparecería con ella. Aquí vive en su propia tabla y
 *     solo se va cuando vence su plazo.
 *  2. **Retención**: cinco años desde la aceptación, que es el plazo general de prescripción de las acciones
 *     personales (artículo 1964.2 del Código Civil) y cubre el tiempo en que alguien puede reclamar. Se
 *     conserva por interés legítimo en defender reclamaciones (artículo 6.1.f y artículo 17.3.e del RGPD), y
 *     por eso la supresión de datos no la borra mientras el plazo esté vivo.
 *
 * El borrado es automático: una tarea diaria elimina las filas cuyo plazo ya venció.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

/**
 * Repositorio de las pruebas de consentimiento.
 */
final class Prueba_Consentimiento {

	/**
	 * Años que se conserva cada prueba.
	 */
	public const RETENCION_ANIOS = 5;

	/**
	 * Tarea programada de purga.
	 */
	public const CRON = 'convoca_courses_purgar_pruebas';

	/**
	 * Engancha la purga diaria.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::CRON, array( __CLASS__, 'purgar' ) );

		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Nombre completo de la tabla.
	 *
	 * @return string
	 */
	public static function tabla(): string {
		global $wpdb;

		return $wpdb->prefix . Installer::PRUEBAS_TABLE;
	}

	/**
	 * Cuándo deja de guardarse una prueba aceptada en una fecha dada.
	 *
	 * Se calcula sobre la fecha de la aceptación, no sobre hoy: si alguien acepta hoy, caduca dentro de
	 * cinco años y no dentro de cinco años desde la última vez que se miró la fila.
	 *
	 * @param string $fecha_utc Fecha de la aceptación (ISO-8601 o `Y-m-d H:i:s`).
	 * @return string Fecha `Y-m-d`.
	 */
	public static function conservar_hasta( string $fecha_utc ): string {
		$marca = strtotime( $fecha_utc );

		if ( false === $marca ) {
			$marca = time();
		}

		return gmdate( 'Y-m-d', strtotime( '+' . self::RETENCION_ANIOS . ' years', $marca ) );
	}

	/**
	 * Guarda una prueba.
	 *
	 * @param array<string, mixed> $datos Claves: matricula_id, curso_id, email, prueba (array de
	 *                                    `Condiciones::prueba()`), consentimiento_texto.
	 * @return int Identificador de la fila, o 0 si no se pudo guardar.
	 */
	public static function guardar( array $datos ): int {
		global $wpdb;

		$prueba = isset( $datos['prueba'] ) && is_array( $datos['prueba'] ) ? $datos['prueba'] : array();
		$fecha  = isset( $prueba['fecha'] ) ? (string) $prueba['fecha'] : gmdate( 'c' );

		$fila = array(
			'matricula_id'         => (int) ( $datos['matricula_id'] ?? 0 ),
			'curso_id'             => (int) ( $datos['curso_id'] ?? 0 ),
			'email'                => (string) ( $datos['email'] ?? '' ),
			'consentimiento_texto' => (string) ( $datos['consentimiento_texto'] ?? '' ),
			'renuncia_texto'       => (string) ( $prueba['texto'] ?? '' ),
			'huella'               => (string) ( $prueba['huella'] ?? '' ),
			'fecha_utc'            => self::a_mysql( $fecha ),
			'ip'                   => (string) ( $prueba['ip'] ?? '' ),
			'agente'               => (string) ( $prueba['agente'] ?? '' ),
			'ambito'               => (string) ( $prueba['ambito'] ?? '' ),
			'version'              => (string) ( $prueba['version'] ?? '' ),
			'conservar_hasta'      => self::conservar_hasta( $fecha ),
		);

		$formatos = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$ok = $wpdb->insert( self::tabla(), $fila, $formatos ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- tabla propia, sin caché de objetos.

		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Pasa una fecha ISO-8601 a `Y-m-d H:i:s` en UTC, que es como la entiende MySQL.
	 *
	 * @param string $fecha Fecha ISO-8601.
	 * @return string
	 */
	public static function a_mysql( string $fecha ): string {
		$marca = strtotime( $fecha );

		return false === $marca ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', $marca );
	}

	/**
	 * Deja constancia de que la confirmación del artículo 98.7 salió por correo.
	 *
	 * Es la tercera condición del artículo 103.m: sin confirmación en soporte duradero, la renuncia no
	 * opera. Se guarda cuándo se mandó para poder demostrarlo.
	 *
	 * @param int $prueba_id Fila de la prueba.
	 * @return void
	 */
	public static function marcar_confirmacion( int $prueba_id ): void {
		global $wpdb;

		if ( $prueba_id <= 0 ) {
			return;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia.
			self::tabla(),
			array( 'confirmacion_enviada' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $prueba_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Pruebas archivadas de una matrícula.
	 *
	 * @param int $matricula_id Matrícula.
	 * @return array<int, array<string, mixed>>
	 */
	public static function de_matricula( int $matricula_id ): array {
		global $wpdb;

		if ( $matricula_id <= 0 ) {
			return array();
		}

		$filas = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia.
			$wpdb->prepare( 'SELECT * FROM ' . self::tabla() . ' WHERE matricula_id = %d ORDER BY id ASC', $matricula_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- el nombre de la tabla no es una entrada.
			'ARRAY_A'
		);

		return is_array( $filas ) ? $filas : array();
	}

	/**
	 * Borra las pruebas cuyo plazo de conservación venció.
	 *
	 * @return int Filas borradas.
	 */
	public static function purgar(): int {
		global $wpdb;

		$borradas = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- purga programada.
			$wpdb->prepare( 'DELETE FROM ' . self::tabla() . ' WHERE conservar_hasta < %s', gmdate( 'Y-m-d' ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- el nombre de la tabla no es una entrada.
		);

		return is_int( $borradas ) ? $borradas : 0;
	}
}
