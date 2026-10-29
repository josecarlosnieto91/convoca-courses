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

		add_meta_box(
			'convoca_courses_task',
			__( 'Evaluación de la tarea', 'convoca-courses' ),
			array( $this, 'render_task_box' ),
			CPT_Tarea::TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Caja de la tarea: si se corrige con un examen, la nota es automática.
	 *
	 * @param \WP_Post $post Entrada actual.
	 * @return void
	 */
	public function render_task_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$course_id = CPT_Tarea::course_id( (int) $post->ID );
		$examen_id = CPT_Tarea::exam_id( (int) $post->ID );
		$examenes  = $course_id > 0 ? CPT_Examen::of_course( $course_id ) : array();

		echo '<p><label for="convoca-task-exam"><strong>' . esc_html__( 'Examen que la corrige', 'convoca-courses' ) . '</strong></label></p>';
		echo '<select name="convoca_task_exam" id="convoca-task-exam" style="width:100%">';
		echo '<option value="0">' . esc_html__( '— Corrección manual —', 'convoca-courses' ) . '</option>';

		foreach ( $examenes as $examen ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $examen->ID,
				selected( $examen_id, (int) $examen->ID, false ),
				esc_html( (string) $examen->post_title )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Si eliges un examen, la tarea es tipo test y la nota sale del examen: nadie tiene que corregirla a mano.', 'convoca-courses' ) . '</p>';

		if ( $course_id <= 0 ) {
			echo '<p class="description">' . esc_html__( 'Guarda primero la tarea con su curso para poder elegir examen.', 'convoca-courses' ) . '</p>';
		}
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
				'post_id'       => (int) $post->ID,
				'precio'        => CPT_Curso::price( (int) $post->ID ),
				'moneda'        => CPT_Curso::currency( (int) $post->ID ),
				'plazas'        => CPT_Curso::seats( (int) $post->ID ),
				'certificado'   => CPT_Curso::issues_certificate( (int) $post->ID ),
				'secuencial'    => CPT_Curso::is_sequential( (int) $post->ID ),
				'apertura'      => CPT_Curso::enrollment_open_date( (int) $post->ID ),
				'cierre'        => CPT_Curso::enrollment_close_date( (int) $post->ID ),
				'fecha_fin'     => CPT_Curso::end_date( (int) $post->ID ),
				'caducidad'     => CPT_Curso::expiry_days( (int) $post->ID ),
				'nota_minima'   => CPT_Curso::min_score( (int) $post->ID ),
				'acceso'        => CPT_Curso::exam_access( (int) $post->ID ),
				'acceso_pct'    => CPT_Curso::exam_access_percent( (int) $post->ID ),
				'precio_socio'  => CPT_Curso::get_float( (int) $post->ID, 'precio_socio', -1.0 ),
				'descuento'     => CPT_Curso::member_discount( (int) $post->ID ),
				'gratis_socios' => CPT_Curso::members_free( (int) $post->ID ),
				'pro_precio'    => Pro::enabled( 'precio_socio' ) && Members_Bridge::is_available(),
				'modos'         => CPT_Curso::ACCESO_EXAMEN,
				'prefix'        => $prefix,
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
				'post_id'  => (int) $post->ID,
				'curso_id' => CPT_Modulo::course_id( (int) $post->ID ),
				'orden'    => CPT_Base::get_int( (int) $post->ID, 'orden', 0 ),
				'cursos'   => $this->courses_options(),
				'prefix'   => CPT_Base::META_PREFIX,
				'aviso'    => $this->hierarchy_errors_for( (int) $post->ID ),
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
				'post_id'    => (int) $post->ID,
				'modulo_id'  => CPT_Leccion::module_id( (int) $post->ID ),
				'tipo'       => CPT_Leccion::type( (int) $post->ID ),
				'orden'      => CPT_Base::get_int( (int) $post->ID, 'orden', 0 ),
				'duracion'   => CPT_Base::get_int( (int) $post->ID, 'duracion', 0 ),
				'adjunto_id' => CPT_Base::get_int( (int) $post->ID, 'adjunto_id', 0 ),
				'video_url'  => CPT_Base::get_string( (int) $post->ID, 'video_url' ),
				'video_key'  => CPT_Base::get_string( (int) $post->ID, 'video_key' ),
				'tipos'      => CPT_Leccion::TYPES,
				'modulos'    => $this->modules_options(),
				'prefix'     => CPT_Base::META_PREFIX,
				'pro'        => License::has_pro(),
				'aviso'      => $this->hierarchy_errors_for( (int) $post->ID ),
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
			case CPT_Tarea::TYPE:
				$this->save_task( $post_id );
				break;
		}
	}

	/**
	 * Guarda los campos del curso.
	 *
	 * @param int $post_id Curso.
	 * @return void
	 */
	/**
	 * Guarda el examen que corrige una tarea.
	 *
	 * @param int $post_id Tarea.
	 * @return void
	 */
	private function save_task( int $post_id ): void {
		// El nonce y los permisos se comprueban en save(), antes de llegar aquí.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado en save().
		if ( ! isset( $_POST['convoca_task_exam'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado en save().
		$examen_id = (int) sanitize_text_field( wp_unslash( (string) $_POST['convoca_task_exam'] ) );

		if ( $examen_id > 0 && CPT_Examen::TYPE !== get_post_type( $examen_id ) ) {
			$examen_id = 0;
		}

		update_post_meta( $post_id, CPT_Base::META_PREFIX . 'examen_id', $examen_id );
	}

	/**
	 * Entero del formulario que **no admite negativos**.
	 *
	 * `absint()` está pensado para identificadores y quita el signo: `absint( -5 )` es 5, así que el `max( 0, ... )`
	 * de después no tenía nada que hacer y un aforo de «-5 plazas» acababa en 5 plazas de más, y un descuento de
	 * -20 en un 20 % (courses#5). Aquí el signo se mira **antes** de acotar.
	 *
	 * @param string $campo Nombre del campo en `$_POST`.
	 * @return int
	 */
	private static function entero_no_negativo( string $campo ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se verificó en save().
		if ( ! isset( $_POST[ $campo ] ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea aquí mismo: `(int)` conserva el signo y `max( 0, ... )` lo acota.
		return max( 0, (int) wp_unslash( (string) $_POST[ $campo ] ) );
	}

	/**
	 * Guarda los campos del curso.
	 *
	 * @param int $post_id Identificador del curso.
	 * @return void
	 */
	private function save_course( int $post_id ): void {
		$prefix = CPT_Base::META_PREFIX;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verificó en save().
		// `absint()` **quita el signo**, así que `absint( -5 )` es 5 y el `max( 0, ... )` de después ya no sirve:
		// un aforo de «-5 plazas» por un formulario manipulado acababa en **5 plazas**, y un descuento de -20 en
		// **20** (members/courses#5). `(int)` conserva el signo y `max( 0, ... )` lo deja en cero.
		$price      = isset( $_POST['convoca_precio'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_precio'] ) ) : 0.0;
		$currency   = isset( $_POST['convoca_moneda'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_moneda'] ) ) : 'EUR';
		$seats      = self::entero_no_negativo( 'convoca_plazas' );
		$cert       = ! empty( $_POST['convoca_certificado'] );
		$sequential = ! empty( $_POST['convoca_secuencial'] );

		$apertura   = isset( $_POST['convoca_apertura_matricula'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_apertura_matricula'] ) ) : '';
		$cierre     = isset( $_POST['convoca_cierre_matricula'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_cierre_matricula'] ) ) : '';
		$fin        = isset( $_POST['convoca_fecha_fin'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_fecha_fin'] ) ) : '';
		$dias       = self::entero_no_negativo( 'convoca_caducidad_dias' );
		$minima     = isset( $_POST['convoca_nota_minima'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_nota_minima'] ) ) : 50.0;
		$acceso     = isset( $_POST['convoca_acceso_examen'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_acceso_examen'] ) ) : 'todas';
		$acceso_pct = isset( $_POST['convoca_acceso_examen_porcentaje'] ) ? self::entero_no_negativo( 'convoca_acceso_examen_porcentaje' ) : 100;
		$socio      = isset( $_POST['convoca_precio_socio'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_precio_socio'] ) ) : -1.0;
		$descuento  = self::entero_no_negativo( 'convoca_descuento_socio' );
		$gratis     = ! empty( $_POST['convoca_gratis_socios'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, $prefix . 'precio', max( 0.0, $price ) );
		update_post_meta( $post_id, $prefix . 'moneda', '' !== $currency ? strtoupper( substr( $currency, 0, 3 ) ) : 'EUR' );
		update_post_meta( $post_id, $prefix . 'plazas', max( 0, $seats ) );
		update_post_meta( $post_id, $prefix . 'certificado', $cert ? 1 : 0 );
		update_post_meta( $post_id, $prefix . 'desbloqueo_secuencial', $sequential ? 1 : 0 );
		update_post_meta( $post_id, $prefix . 'fecha_apertura_matricula', CPT_Curso::normalize_date( $apertura ) );
		update_post_meta( $post_id, $prefix . 'fecha_cierre_matricula', CPT_Curso::normalize_date( $cierre ) );
		update_post_meta( $post_id, $prefix . 'fecha_fin', CPT_Curso::normalize_date( $fin ) );
		update_post_meta( $post_id, $prefix . 'caducidad_dias', max( 0, $dias ) );
		update_post_meta( $post_id, $prefix . 'nota_minima', CPT_Curso::normalize_score( $minima ) );
		update_post_meta( $post_id, $prefix . 'acceso_examen', CPT_Curso::normalize_exam_access( $acceso ) );
		update_post_meta( $post_id, $prefix . 'acceso_examen_porcentaje', max( 0, min( 100, $acceso_pct ) ) );
		update_post_meta( $post_id, $prefix . 'precio_socio', $socio < 0 ? -1.0 : round( $socio, 2 ) );
		update_post_meta( $post_id, $prefix . 'descuento_socio', max( 0, min( 100, $descuento ) ) );
		update_post_meta( $post_id, $prefix . 'gratis_socios', $gratis ? '1' : '0' );
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
		$course_id = self::entero_no_negativo( 'convoca_curso_id' );
		$order     = self::entero_no_negativo( 'convoca_orden' );
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
		$module_id  = self::entero_no_negativo( 'convoca_modulo_id' );
		$type       = isset( $_POST['convoca_tipo'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_tipo'] ) ) : 'texto';
		$order      = self::entero_no_negativo( 'convoca_orden' );
		$duration   = self::entero_no_negativo( 'convoca_duracion' );
		$attachment = self::entero_no_negativo( 'convoca_adjunto_id' );
		$video_url  = isset( $_POST['convoca_video_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['convoca_video_url'] ) ) : '';
		$video_key  = isset( $_POST['convoca_video_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_video_key'] ) ) : '';
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de la pantalla actual.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( (string) $_GET['post'] ) ) : 0;
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
