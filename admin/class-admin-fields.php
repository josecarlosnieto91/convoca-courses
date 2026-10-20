<?php
/**
 * Metaboxes de curso, módulo y lección.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campos del editor: sin ellos no se puede montar un curso desde el panel.
 */
class Admin_Fields {

	/**
	 * Nonce de los campos.
	 */
	public const NONCE = 'convoca_courses_fields';

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'hierarchy_notice' ) );
	}

	/**
	 * Añade las cajas.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'convoca_courses_course',
			__( 'Datos del curso', 'convoca-courses' ),
			array( $this, 'render_course_box' ),
			CPT_Curso::TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'convoca_courses_module',
			__( 'Datos del módulo', 'convoca-courses' ),
			array( $this, 'render_module_box' ),
			CPT_Modulo::TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'convoca_courses_lesson',
			__( 'Datos de la lección', 'convoca-courses' ),
			array( $this, 'render_lesson_box' ),
			CPT_Leccion::TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Caja del curso.
	 *
	 * @param \WP_Post $post Entrada actual.
	 * @return void
	 */
	public function render_course_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$prefix = CPT_Base::META_PREFIX;

		Certificate::render_template(
			'metabox-curso.php',
			array(
				'post_id'      => (int) $post->ID,
				'precio'       => CPT_Curso::price( (int) $post->ID ),
				'moneda'       => CPT_Curso::currency( (int) $post->ID ),
				'plazas'       => CPT_Curso::seats( (int) $post->ID ),
				'certificado'  => CPT_Curso::issues_certificate( (int) $post->ID ),
				'secuencial'   => CPT_Curso::is_sequential( (int) $post->ID ),
				'prefix'       => $prefix,
			)
		);
	}

	/**
	 * Caja del módulo.
	 *
	 * @param \WP_Post $post Entrada actual.
	 * @return void
	 */
	public function render_module_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		Certificate::render_template(
			'metabox-modulo.php',
			array(
				'post_id'    => (int) $post->ID,
				'curso_id'   => CPT_Modulo::course_id( (int) $post->ID ),
				'orden'      => CPT_Base::get_int( (int) $post->ID, 'orden', 0 ),
				'cursos'     => $this->courses_options(),
				'prefix'     => CPT_Base::META_PREFIX,
				'aviso'      => $this->hierarchy_errors_for( (int) $post->ID ),
			)
		);
	}

	/**
	 * Caja de la lección.
	 *
	 * @param \WP_Post $post Entrada actual.
	 * @return void
	 */
	public function render_lesson_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		Certificate::render_template(
			'metabox-leccion.php',
			array(
				'post_id'      => (int) $post->ID,
				'modulo_id'    => CPT_Leccion::module_id( (int) $post->ID ),
				'tipo'         => CPT_Leccion::type( (int) $post->ID ),
				'orden'        => CPT_Base::get_int( (int) $post->ID, 'orden', 0 ),
				'duracion'     => CPT_Base::get_int( (int) $post->ID, 'duracion', 0 ),
				'adjunto_id'   => CPT_Base::get_int( (int) $post->ID, 'adjunto_id', 0 ),
				'video_url'    => CPT_Base::get_string( (int) $post->ID, 'video_url' ),
				'video_key'    => CPT_Base::get_string( (int) $post->ID, 'video_key' ),
				'tipos'        => CPT_Leccion::TYPES,
				'modulos'      => $this->modules_options(),
				'prefix'       => CPT_Base::META_PREFIX,
				'pro'          => License::has_pro(),
				'aviso'        => $this->hierarchy_errors_for( (int) $post->ID ),
			)
		);
	}

	/**
	 * Guarda los campos.
	 *
	 * @param int      $post_id Identificador.
	 * @param \WP_Post $post    Entrada.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'convoca_manage_courses' ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case CPT_Curso::TYPE:
				$this->save_course( $post_id );
				break;
			case CPT_Modulo::TYPE:
				$this->save_module( $post_id );
				break;
			case CPT_Leccion::TYPE:
				$this->save_lesson( $post_id );
				break;
		}
	}

	/**
	 * Guarda los campos del curso.
	 *
	 * @param int $post_id Curso.
	 * @return void
	 */
	private function save_course( int $post_id ): void {
		$prefix = CPT_Base::META_PREFIX;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verificó en save().
		$price     = isset( $_POST[ 'convoca_precio' ] ) ? (float) wp_unslash( (string) $_POST[ 'convoca_precio' ] ) : 0.0;
		$currency  = isset( $_POST[ 'convoca_moneda' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ 'convoca_moneda' ] ) ) : 'EUR';
		$seats     = isset( $_POST[ 'convoca_plazas' ] ) ? (int) $_POST[ 'convoca_plazas' ] : 0;
		$cert      = ! empty( $_POST[ 'convoca_certificado' ] );
		$sequential = ! empty( $_POST[ 'convoca_secuencial' ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, $prefix . 'precio', max( 0.0, $price ) );
		update_post_meta( $post_id, $prefix . 'moneda', '' !== $currency ? strtoupper( substr( $currency, 0, 3 ) ) : 'EUR' );
		update_post_meta( $post_id, $prefix . 'plazas', max( 0, $seats ) );
		update_post_meta( $post_id, $prefix . 'certificado', $cert ? 1 : 0 );
		update_post_meta( $post_id, $prefix . 'desbloqueo_secuencial', $sequential ? 1 : 0 );
	}

	/**
	 * Guarda los campos del módulo y valida la jerarquía.
	 *
	 * @param int $post_id Módulo.
	 * @return void
	 */
	private function save_module( int $post_id ): void {
		$prefix = CPT_Base::META_PREFIX;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verificó en save().
		$course_id = isset( $_POST[ 'convoca_curso_id' ] ) ? (int) $_POST[ 'convoca_curso_id' ] : 0;
		$order     = isset( $_POST[ 'convoca_orden' ] ) ? (int) $_POST[ 'convoca_orden' ] : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, $prefix . 'orden', $order );

		if ( Hierarchy::valid_module_course( $post_id, $course_id ) ) {
			update_post_meta( $post_id, $prefix . 'curso_id', $course_id );
			return;
		}

		delete_post_meta( $post_id, $prefix . 'curso_id' );
		$this->flag_hierarchy_problem( $post_id );
	}

	/**
	 * Guarda los campos de la lección y valida la jerarquía.
	 *
	 * @param int $post_id Lección.
	 * @return void
	 */
	private function save_lesson( int $post_id ): void {
		$prefix = CPT_Base::META_PREFIX;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verificó en save().
		$module_id    = isset( $_POST[ 'convoca_modulo_id' ] ) ? (int) $_POST[ 'convoca_modulo_id' ] : 0;
		$type         = isset( $_POST[ 'convoca_tipo' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ 'convoca_tipo' ] ) ) : 'texto';
		$order        = isset( $_POST[ 'convoca_orden' ] ) ? (int) $_POST[ 'convoca_orden' ] : 0;
		$duration     = isset( $_POST[ 'convoca_duracion' ] ) ? (int) $_POST[ 'convoca_duracion' ] : 0;
		$attachment   = isset( $_POST[ 'convoca_adjunto_id' ] ) ? (int) $_POST[ 'convoca_adjunto_id' ] : 0;
		$video_url    = isset( $_POST[ 'convoca_video_url' ] ) ? esc_url_raw( wp_unslash( (string) $_POST[ 'convoca_video_url' ] ) ) : '';
		$video_key    = isset( $_POST[ 'convoca_video_key' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ 'convoca_video_key' ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, $prefix . 'tipo', in_array( $type, CPT_Leccion::TYPES, true ) ? $type : 'texto' );
		update_post_meta( $post_id, $prefix . 'orden', $order );
		update_post_meta( $post_id, $prefix . 'duracion', max( 0, $duration ) );
		update_post_meta( $post_id, $prefix . 'adjunto_id', max( 0, $attachment ) );
		update_post_meta( $post_id, $prefix . 'video_url', $video_url );
		update_post_meta( $post_id, $prefix . 'video_key', $video_key );

		if ( Hierarchy::valid_lesson_module( $post_id, $module_id ) ) {
			update_post_meta( $post_id, $prefix . 'modulo_id', $module_id );
			return;
		}

		delete_post_meta( $post_id, $prefix . 'modulo_id' );
		$this->flag_hierarchy_problem( $post_id );
	}

	/**
	 * Marca la entrada con un problema de jerarquía y deja el aviso en la sesión del editor.
	 *
	 * @param int $post_id Entrada.
	 * @return void
	 */
	private function flag_hierarchy_problem( int $post_id ): void {
		update_post_meta( $post_id, CPT_Base::META_PREFIX . 'hierarchy_error', '1' );

		$transient = 'convoca_courses_notice_' . get_current_user_id();
		set_transient(
			$transient,
			__( 'Falta la relación de jerarquía: un módulo tiene que colgar de un curso y una lección de un módulo. La entrada se ha guardado sin relación.', 'convoca-courses' ),
			30
		);
	}

	/**
	 * Aviso persistente en el editor.
	 *
	 * @return void
	 */
	public function hierarchy_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( (string) $screen->post_type, array( CPT_Modulo::TYPE, CPT_Leccion::TYPE ), true ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de la pantalla actual.
		$errors  = $this->hierarchy_errors_for( $post_id );
		if ( empty( $errors ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( implode( ' ', $errors ) )
		);
	}

	/**
	 * Errores de jerarquía de una entrada.
	 *
	 * @param int $post_id Entrada.
	 * @return array<int, string>
	 */
	private function hierarchy_errors_for( int $post_id ): array {
		if ( 0 === $post_id ) {
			return array();
		}

		if ( CPT_Leccion::TYPE === get_post_type( $post_id ) ) {
			return Hierarchy::lesson_errors( $post_id );
		}

		if ( CPT_Modulo::TYPE === get_post_type( $post_id ) && 0 === CPT_Modulo::course_id( $post_id ) ) {
			return array( __( 'El módulo no cuelga de ningún curso.', 'convoca-courses' ) );
		}

		return array();
	}

	/**
	 * Cursos para el selector.
	 *
	 * @return array<int, string>
	 */
	private function courses_options(): array {
		$options = array( 0 => __( '— Selecciona un curso —', 'convoca-courses' ) );

		foreach ( get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $course ) {
			$options[ (int) $course->ID ] = (string) $course->post_title;
		}

		return $options;
	}

	/**
	 * Módulos para el selector, agrupados por curso.
	 *
	 * @return array<int, array{curso: string, modulos: array<int, string>}>
	 */
	private function modules_options(): array {
		$groups = array();

		foreach ( $this->courses_options() as $course_id => $course_title ) {
			if ( 0 === $course_id ) {
				continue;
			}

			$modules = array();
			foreach ( CPT_Modulo::of_course( $course_id ) as $module ) {
				$modules[ (int) $module->ID ] = (string) $module->post_title;
			}

			$groups[] = array(
				'curso'   => $course_title,
				'modulos' => $modules,
			);
		}

		// Los módulos sin curso también se pueden asignar (para arreglarlos desde la lección).
		$orphans = get_posts(
			array(
				'post_type'      => CPT_Modulo::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$orphan_list = array();
		foreach ( $orphans as $orphan ) {
			if ( 0 === CPT_Modulo::course_id( (int) $orphan->ID ) ) {
				$orphan_list[ (int) $orphan->ID ] = (string) $orphan->post_title;
			}
		}

		if ( ! empty( $orphan_list ) ) {
			$groups[] = array(
				'curso'   => __( 'Módulos sin curso', 'convoca-courses' ),
				'modulos' => $orphan_list,
			);
		}

		return $groups;
	}
}
