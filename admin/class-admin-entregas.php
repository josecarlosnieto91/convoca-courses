<?php
/**
 * Panel de entregas: corregir tareas del alumnado.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de entregas de un curso.
 *
 * Vive bajo el menú de Cursos porque es donde se busca: el profesorado entra al curso y corrige. La
 * corrección va por `admin-post` con nonce, igual que el resto de formularios del módulo.
 */
class Admin_Entregas {

	/**
	 * Slug de la pantalla.
	 */
	public const PAGE = 'convoca-courses-entregas';

	/**
	 * Registra el menú.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Añade la pantalla bajo el menú de cursos.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . CPT_Curso::TYPE,
			__( 'Entregas', 'convoca-courses' ),
			__( 'Entregas', 'convoca-courses' ),
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
		if ( ! Pro::can_grade() ) {
			wp_die( esc_html__( 'No tienes permiso para corregir entregas.', 'convoca-courses' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura de filtros de pantalla.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$aviso     = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$cursos = get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$course_title = '';
		foreach ( $cursos as $curso ) {
			if ( (int) $curso->ID === $course_id ) {
				$course_title = (string) $curso->post_title;
			}
		}

		Certificate::render_template(
			'entregas.php',
			array(
				'page'         => self::PAGE,
				'course_id'    => $course_id,
				'course_title' => $course_title,
				'cursos'       => $cursos,
				'entregas'     => $course_id > 0 ? Task::of_course( $course_id ) : array(),
				'notice'       => '' === $aviso ? '' : Handlers::message( $aviso ),
				'action_url'   => esc_url( admin_url( 'admin-post.php' ) ),
				'action'       => Handlers::ACTION_TASK_GRADE,
				'bulk'         => Pro::enabled( 'tutores' ),
			)
		);
	}
}
