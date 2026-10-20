<?php
/**
 * Ciclo de vida: tablas propias, capabilities y reescrituras.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instalador del módulo.
 */
class Installer {

	/**
	 * Opción con la versión de esquema ya aplicada.
	 */
	public const DB_OPTION = 'convoca_courses_db_version';

	/**
	 * Opción que decide si al desinstalar se conservan los datos.
	 */
	public const KEEP_DATA_OPTION = 'convoca_courses_keep_data';

	/**
	 * Nombre de la tabla de progreso (sin prefijo).
	 */
	public const PROGRESS_TABLE = 'convoca_course_progress';

	/**
	 * Activación: crea tablas, capabilities y reescribe reglas.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::add_capabilities();

		// Los tipos de contenido tienen que existir ANTES de reescribir las reglas: si no, el flush
		// de la activación se guarda sin las URLs de /cursos/ y el archivo del curso da 404 hasta que
		// alguien vuelve a guardar los enlaces permanentes.
		Plugin::instance()->register_types();

		Verification::register_rewrite();
		flush_rewrite_rules();

		update_option( self::DB_OPTION, CONVOCA_COURSES_DB_VERSION, false );
	}

	/**
	 * Desactivación: solo se limpian las reglas de reescritura. Los datos se quedan.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Crea o actualiza las tablas propias con dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . self::PROGRESS_TABLE;
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			completed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id,lesson_id),
			KEY course_user (course_id,user_id)
		) {$collate};";

		dbDelta( $sql );
	}

	/**
	 * Aplica el esquema si la versión guardada no coincide con la del código.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_OPTION, '' );
		if ( CONVOCA_COURSES_DB_VERSION === $installed ) {
			return;
		}

		self::create_tables();
		update_option( self::DB_OPTION, CONVOCA_COURSES_DB_VERSION, false );
	}

	/**
	 * Capabilities propias. Se dan al rol administrador y se pueden repartir con un plugin de roles.
	 *
	 * @return void
	 */
	public static function add_capabilities(): void {
		$caps = array(
			'convoca_manage_courses',
			'convoca_enroll_courses',
			'convoca_view_certificates',
		);

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Retira las capabilities propias.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		$caps = array(
			'convoca_manage_courses',
			'convoca_enroll_courses',
			'convoca_view_certificates',
		);

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Nombre completo de la tabla de progreso.
	 *
	 * @return string
	 */
	public static function progress_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::PROGRESS_TABLE;
	}
}
