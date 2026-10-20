<?php
/**
 * Arranque del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Punto único de enganche.
 */
final class Plugin {

	/**
	 * Instancia única.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado: se usa instance().
	 */
	private function __construct() {}

	/**
	 * Engancha todo lo del módulo.
	 *
	 * @return void
	 */
	public function boot(): void {
		Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'register_types' ) );
		add_action( 'init', array( Verification::class, 'register_rewrite' ) );
		add_filter( 'query_vars', array( Verification::class, 'query_vars' ) );
		add_action( 'template_redirect', array( Verification::class, 'maybe_serve' ) );

		Shortcodes::register();
		Handlers::register();
		Assets::register();

		$fields = new Admin_Fields();
		$fields->register();

		$columns = new Admin_Columns();
		$columns->register();

		if ( is_admin() ) {
			Settings::register();
		}

		add_action( 'before_delete_post', array( $this, 'cleanup_on_delete' ) );
	}

	/**
	 * Registra los tipos de contenido del módulo.
	 *
	 * @return void
	 */
	public function register_types(): void {
		CPT_Curso::register();
		CPT_Modulo::register();
		CPT_Leccion::register();
		CPT_Matricula::register();
	}

	/**
	 * Limpia los datos dependientes al borrar definitivamente un contenido del módulo.
	 *
	 * @param int $post_id Identificador del post borrado.
	 * @return void
	 */
	public function cleanup_on_delete( int $post_id ): void {
		$type = get_post_type( $post_id );

		if ( CPT_Leccion::TYPE === $type ) {
			Progress::delete_for_lesson( $post_id );
			return;
		}

		if ( CPT_Curso::TYPE === $type ) {
			Progress::delete_for_course( $post_id );
		}
	}
}
