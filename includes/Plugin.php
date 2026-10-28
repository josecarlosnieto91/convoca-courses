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
		Installer::maybe_upgrade_capabilities();

		add_action( 'init', array( $this, 'register_types' ) );
		// Las capacidades por entrada del módulo necesitan su traducción (ver CPT_Base).
		add_filter( 'map_meta_cap', array( CPT_Base::class, 'bridge_meta_caps' ), 10, 2 );
		add_action( 'init', array( Verification::class, 'register_rewrite' ) );
		add_filter( 'query_vars', array( Verification::class, 'query_vars' ) );
		add_action( 'template_redirect', array( Verification::class, 'maybe_serve' ) );

		Shortcodes::register();
		Handlers::register();

		// Aprobar el examen tiene que cerrar el círculo: completar las lecciones pide el certificado
		// antes de que exista nota, y hasta ahora nadie volvía a preguntar después.
		add_action( 'convoca_courses_exam_submitted', array( Certificate::class, 'on_exam_submitted' ), 10, 4 );
		Assets::register();

		$fields = new Admin_Fields();
		$fields->register();

		$columns = new Admin_Columns();
		$columns->register();

		$exam_admin = new Admin_Examen();
		$exam_admin->register();

		$entregas = new Admin_Entregas();
		$entregas->register();

		Reminders::register();
		Lesson_Links::register();

		if ( is_admin() ) {
			Settings::register();
		}

		// Resultado del cobro: los ganchos que ya dispara Convoca Gateway. Si el gateway no está, no
		// existen y no pasa nada; la matrícula se queda como esté.
		// Los nombres vigentes: los viejos (`convoca_payment_*`) los sigue disparando el gateway por
		// compatibilidad, y escuchar ahí suelta un aviso de hook deprecado en CADA pago. Lo cazó el
		// chequeo diario de contratos de Andromeda (21/10), no el CI de este repo.
		add_action( 'convoca_gateway_payment_completed', array( Course_Payment::class, 'on_payment_completed' ), 10, 4 );
		add_action( 'convoca_gateway_payment_failed', array( Course_Payment::class, 'on_payment_failed' ), 10, 2 );

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
		CPT_Examen::register();
		CPT_Tarea::register();
		Cohorts::register();
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
			return;
		}

		if ( CPT_Examen::TYPE === $type ) {
			Questions::delete_for_exam( $post_id );
			Attempts::delete_for_exam( $post_id );
			return;
		}

		if ( CPT_Tarea::TYPE === $type ) {
			Task::delete_for_task( $post_id );
		}
	}
}
