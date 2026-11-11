<?php
/**
 * Pantalla del registro inalterable de conexiones.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consulta y verificación de la cadena de eventos.
 */
class Admin_Registro {

	/**
	 * Slug de la pantalla.
	 */
	public const PAGE = 'convoca-courses-registro';

	/**
	 * Engancha el menú.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Añade la pantalla.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . CPT_Curso::TYPE,
			__( 'Registro de conexiones', 'convoca-courses' ),
			__( 'Conexiones', 'convoca-courses' ),
			'convoca_manage_courses',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permiso para ver el registro.', 'convoca-courses' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Registro de conexiones', 'convoca-courses' ) . '</h1>';

		if ( ! Pro::enabled( 'registro_conexiones' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'El registro inalterable es una función PRO.', 'convoca-courses' ) . '</p></div></div>';
			return;
		}

		$valido = Access_Log::verify();
		$clase  = $valido ? 'notice-success' : 'notice-error';
		$texto  = $valido ? __( 'Cadena verificada: no se ha detectado ninguna alteración.', 'convoca-courses' ) : __( 'La cadena NO se puede verificar. Revisa la base de datos y el secreto del sitio.', 'convoca-courses' );
		echo '<div class="notice ' . esc_attr( $clase ) . '"><p>' . esc_html( $texto ) . '</p></div>';

		global $wpdb;
		$tabla = $wpdb->prefix . Installer::ACCESS_LOG_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$filas = $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY id DESC LIMIT 200", 'ARRAY_A' );

		if ( ! is_array( $filas ) || array() === $filas ) {
			echo '<p>' . esc_html__( 'Todavía no hay eventos.', 'convoca-courses' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Fecha (UTC)', 'convoca-courses' ), __( 'Evento', 'convoca-courses' ), __( 'Curso', 'convoca-courses' ), __( 'Lección', 'convoca-courses' ), __( 'Usuario', 'convoca-courses' ), __( 'Identidad', 'convoca-courses' ), __( 'IP', 'convoca-courses' ), __( 'Hash', 'convoca-courses' ) ) as $columna ) {
			echo '<th>' . esc_html( $columna ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $filas as $fila ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $fila['created_utc'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $fila['event'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $fila['course_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['lesson_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['user_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['identidad_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['ip'] ) . '</td>';
			echo '<td><code>' . esc_html( substr( (string) $fila['hash'], 0, 12 ) ) . '…</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
