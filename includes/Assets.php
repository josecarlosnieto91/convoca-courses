<?php
/**
 * Assets del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carga de CSS y JS con cache-buster por versión.
 */
class Assets {

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
	}

	/**
	 * Assets públicos: solo donde hacen falta.
	 *
	 * @return void
	 */
	public static function front(): void {
		$needed = is_singular( CPT_Curso::TYPE ) || self::has_shortcode_on_screen();

		if ( ! $needed ) {
			return;
		}

		wp_enqueue_style(
			'convoca-courses',
			CONVOCA_COURSES_URL . 'assets/css/courses.css',
			array(),
			CONVOCA_COURSES_VERSION
		);

		wp_enqueue_script(
			'convoca-courses',
			CONVOCA_COURSES_URL . 'assets/js/courses.js',
			array(),
			CONVOCA_COURSES_VERSION,
			true
		);

		wp_localize_script(
			'convoca-courses',
			'convocaCourses',
			array(
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( Handlers::ACTION_VIDEO ),
				'action'  => Handlers::ACTION_VIDEO,
			)
		);
	}

	/**
	 * Assets del editor y de las pantallas del módulo.
	 *
	 * @param string $hook Hook de la pantalla actual.
	 * @return void
	 */
	public static function admin( string $hook ): void {
		unset( $hook );

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( (string) $screen->post_type, array( CPT_Curso::TYPE, CPT_Modulo::TYPE, CPT_Leccion::TYPE, CPT_Matricula::TYPE ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'convoca-courses-admin',
			CONVOCA_COURSES_URL . 'assets/css/admin.css',
			array(),
			CONVOCA_COURSES_VERSION
		);
	}

	/**
	 * ¿La pantalla actual contiene algún shortcode del módulo?
	 *
	 * @return bool
	 */
	private static function has_shortcode_on_screen(): bool {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$content = (string) $post->post_content;

		return has_shortcode( $content, 'convoca_curso' )
			|| has_shortcode( $content, 'convoca_curso_lecciones' )
			|| has_shortcode( $content, 'convoca_curso_leccion' );
	}
}
