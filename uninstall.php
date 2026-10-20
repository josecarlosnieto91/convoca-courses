<?php
/**
 * Desinstalación: se borra todo salvo que el sitio haya pedido conservar los datos.
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$convoca_courses_keep = (bool) get_option( 'convoca_courses_keep_data', false );

if ( ! $convoca_courses_keep ) {
	global $wpdb;

	// Entradas del módulo y su meta.
	$convoca_courses_types = array( 'convoca_curso', 'convoca_modulo', 'convoca_leccion', 'convoca_matricula' );
	$convoca_courses_posts = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( %s, %s, %s, %s )",
			$convoca_courses_types[0],
			$convoca_courses_types[1],
			$convoca_courses_types[2],
			$convoca_courses_types[3]
		)
	);

	foreach ( (array) $convoca_courses_posts as $convoca_courses_post_id ) {
		wp_delete_post( (int) $convoca_courses_post_id, true );
	}

	// Tabla de progreso.
	$convoca_courses_table = $wpdb->prefix . 'convoca_course_progress';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla propio y desinstalación explícita.
	$wpdb->query( "DROP TABLE IF EXISTS {$convoca_courses_table}" );

	// Opciones.
	delete_option( 'convoca_courses_db_version' );
	delete_option( 'convoca_courses_video' );
	delete_option( 'convoca_courses_keep_data' );

	// Capabilities.
	foreach ( array( 'administrator', 'editor' ) as $convoca_courses_role_name ) {
		$convoca_courses_role = get_role( $convoca_courses_role_name );
		if ( ! $convoca_courses_role ) {
			continue;
		}
		foreach ( array( 'convoca_manage_courses', 'convoca_enroll_courses', 'convoca_view_certificates' ) as $convoca_courses_cap ) {
			$convoca_courses_role->remove_cap( $convoca_courses_cap );
		}
	}
}
