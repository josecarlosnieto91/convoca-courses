<?php
/**
 * Plugin Name:       Convoca Courses
 * Plugin URI:        https://getconvoca.app
 * Description:       Cursos online con módulos, lecciones, matrícula, progreso y certificado. Funciona solo con Convoca Core.
 * Version:           0.2.3
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.1.3
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://getconvoca.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-courses
 * Domain Path:       /languages
 * Requires Plugins:  convoca-core
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Carga de traducciones ───────────────────────────────────────────── */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'convoca-courses', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/* ── Autoload (Composer si está; si no, cargador propio) ─────────────── */
$convoca_courses_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $convoca_courses_autoload ) ) {
	require_once $convoca_courses_autoload;
}

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'Convoca\\Courses\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}

		// Clases del panel: se nombran al estilo de WordPress (class-admin-fields.php) y no siguen PSR-4.
		$legacy = __DIR__ . '/admin/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( file_exists( $legacy ) ) {
			require_once $legacy;
		}
	}
);

/* ── Constantes ──────────────────────────────────────────────────────── */
if ( ! defined( 'CONVOCA_COURSES_FILE' ) ) {
	define( 'CONVOCA_COURSES_FILE', __FILE__ );
}
if ( ! defined( 'CONVOCA_COURSES_DIR' ) ) {
	define( 'CONVOCA_COURSES_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_COURSES_URL' ) ) {
	define( 'CONVOCA_COURSES_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_COURSES_DB_VERSION' ) ) {
	define( 'CONVOCA_COURSES_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'CONVOCA_COURSES_VERSION' ) ) {
	// Cache-buster de los assets: se lee de la cabecera para que nunca quede fija.
	$convoca_courses_header = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
	define( 'CONVOCA_COURSES_VERSION', ! empty( $convoca_courses_header['Version'] ) ? $convoca_courses_header['Version'] : '0.0.0' );
}

/* ── Activación / desactivación ──────────────────────────────────────── */
register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );

/* ── Convoca Core es la única dependencia obligatoria ────────────────── */
if ( ! class_exists( '\Convoca\Core\Utils' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Convoca Courses necesita el plugin Convoca Core activo para funcionar.', 'convoca-courses' )
			);
		}
	);
	return;
}

/* ── Arranque ────────────────────────────────────────────────────────── */
add_action(
	'plugins_loaded',
	function () {
		Plugin::instance()->boot();
	},
	5
);
