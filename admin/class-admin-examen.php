<?php
/**
 * Panel del examen: configuración y banco de preguntas.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cajas del editor del examen y gestión de sus preguntas.
 *
 * Las preguntas no son entradas de WordPress: se dan de alta desde aquí y se guardan en la tabla del
 * banco. Se hace con `admin-post` y nonce, sin JavaScript, como el resto de formularios del módulo.
 */
class Admin_Examen {

	/**
	 * Nonce de la configuración del examen.
	 */
	public const NONCE = 'convoca_courses_exam';

	/**
	 * Acción de alta y edición de una pregunta.
	 */
	public const ACTION_QUESTION = 'convoca_courses_question_save';

	/**
	 * Acción de borrado de una pregunta.
	 */
	public const ACTION_QUESTION_DELETE = 'convoca_courses_question_delete';

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION_QUESTION, array( $this, 'handle_question_save' ) );
		add_action( 'admin_post_' . self::ACTION_QUESTION_DELETE, array( $this, 'handle_question_delete' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Añade las cajas del examen.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'convoca_courses_exam',
			__( 'Configuración del examen', 'convoca-courses' ),
			array( $this, 'render_config_box' ),
			CPT_Examen::TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'convoca_courses_exam_questions',
			__( 'Preguntas del examen', 'convoca-courses' ),
			array( $this, 'render_questions_box' ),
			CPT_Examen::TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Caja de configuración.
	 *
	 * @param \WP_Post $post Entrada.
	 * @return void
	 */
	public function render_config_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		Certificate::render_template(
			'metabox-examen.php',
			array(
				'post_id'       => (int) $post->ID,
				'curso_id'      => CPT_Examen::course_id( (int) $post->ID ),
				'modulo_id'     => CPT_Examen::module_id( (int) $post->ID ),
				'num_preguntas' => CPT_Examen::questions_per_attempt( (int) $post->ID ),
				'aleatorio'     => CPT_Examen::is_random( (int) $post->ID ),
				'intentos'      => CPT_Examen::max_attempts( (int) $post->ID ),
				'nota_minima'   => CPT_Examen::get_float( (int) $post->ID, 'nota_minima', 0.0 ),
				'tiempo'        => CPT_Examen::time_limit( (int) $post->ID ),
				'retro'         => CPT_Examen::feedback( (int) $post->ID ),
				'peso'          => CPT_Examen::weight( (int) $post->ID ),
				'retros'        => CPT_Examen::RETROALIMENTACION,
				'cursos'        => $this->courses_options(),
				'modulos'       => $this->modules_options(),
				'prefix'        => CPT_Base::META_PREFIX,
			)
		);
	}

	/**
	 * Caja del banco de preguntas.
	 *
	 * @param \WP_Post $post Entrada.
	 * @return void
	 */
	public function render_questions_box( \WP_Post $post ): void {
		$exam_id = (int) $post->ID;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del identificador a editar.
		$editar = isset( $_GET['convoca_pregunta'] ) ? absint( wp_unslash( (string) $_GET['convoca_pregunta'] ) ) : 0;
		$actual = $editar > 0 ? Questions::get( $editar ) : null;

		Certificate::render_template(
			'metabox-preguntas.php',
			array(
				'post_id'       => $exam_id,
				'curso_id'      => CPT_Examen::course_id( $exam_id ),
				'preguntas'     => Questions::all( $exam_id ),
				'editar'        => $actual,
				'tipos'         => Questions::TIPOS,
				'opciones_vf'   => Questions::OPCIONES_VF,
				'action_url'    => esc_url( admin_url( 'admin-post.php' ) ),
				'action'        => self::ACTION_QUESTION,
				'action_delete' => self::ACTION_QUESTION_DELETE,
				'nonce'         => wp_create_nonce( self::ACTION_QUESTION . '_' . $exam_id ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de un identificador.
				'nonce_delete'  => wp_create_nonce( self::ACTION_QUESTION_DELETE . '_' . $exam_id ),
				'aviso'         => $this->notice_text(),
			)
		);
	}

	/**
	 * Guarda la configuración del examen.
	 *
	 * @param int      $post_id Identificador.
	 * @param \WP_Post $post    Entrada.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( CPT_Examen::TYPE !== $post->post_type ) {
			return;
		}

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

		$prefix = CPT_Base::META_PREFIX;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verificó arriba.
		$curso     = isset( $_POST['convoca_curso_id'] ) ? absint( wp_unslash( (string) $_POST['convoca_curso_id'] ) ) : 0;
		$modulo    = isset( $_POST['convoca_modulo_id'] ) ? absint( wp_unslash( (string) $_POST['convoca_modulo_id'] ) ) : 0;
		$num       = isset( $_POST['convoca_num_preguntas'] ) ? absint( wp_unslash( (string) $_POST['convoca_num_preguntas'] ) ) : 0;
		$aleatorio = ! empty( $_POST['convoca_aleatorio'] );
		$intentos  = isset( $_POST['convoca_intentos'] ) ? absint( wp_unslash( (string) $_POST['convoca_intentos'] ) ) : 0;
		$minima    = isset( $_POST['convoca_nota_minima'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_nota_minima'] ) ) : 0.0;
		$tiempo    = isset( $_POST['convoca_tiempo_maximo'] ) ? absint( wp_unslash( (string) $_POST['convoca_tiempo_maximo'] ) ) : 0;
		$retro     = isset( $_POST['convoca_retroalimentacion'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_retroalimentacion'] ) ) : 'tras_enviar';
		$peso      = isset( $_POST['convoca_peso'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_peso'] ) ) : 0.0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, $prefix . 'curso_id', $curso );
		update_post_meta( $post_id, $prefix . 'modulo_id', Hierarchy::is_type( $modulo, CPT_Modulo::TYPE ) ? $modulo : 0 );
		update_post_meta( $post_id, $prefix . 'num_preguntas', $num );
		update_post_meta( $post_id, $prefix . 'aleatorio', $aleatorio ? '1' : '0' );
		update_post_meta( $post_id, $prefix . 'intentos', $intentos );
		update_post_meta( $post_id, $prefix . 'nota_minima', CPT_Curso::normalize_score( $minima ) );
		update_post_meta( $post_id, $prefix . 'tiempo_maximo', $tiempo );
		update_post_meta( $post_id, $prefix . 'retroalimentacion', CPT_Examen::normalize_feedback( $retro ) );
		update_post_meta( $post_id, $prefix . 'peso', round( max( 0.0, min( 100.0, $peso ) ), 2 ) );
	}

	/**
	 * Atiende el alta o la edición de una pregunta.
	 *
	 * @return void
	 */
	public function handle_question_save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica justo debajo.
		$exam_id = isset( $_POST['exam_id'] ) ? absint( wp_unslash( (string) $_POST['exam_id'] ) ) : 0;
		$nonce   = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACTION_QUESTION . '_' . $exam_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permiso para editar este examen.', 'convoca-courses' ) );
		}

		$edit_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( (string) $_POST['question_id'] ) ) : 0;

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verificado; cada opción se limpia en Questions::validate().
		$data = array(
			'id'          => $edit_id,
			'course_id'   => CPT_Examen::course_id( $exam_id ),
			'exam_id'     => $exam_id,
			'tipo'        => isset( $_POST['convoca_tipo'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_tipo'] ) ) : 'unica',
			'enunciado'   => isset( $_POST['convoca_enunciado'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_enunciado'] ) ) : '',
			'opciones'    => isset( $_POST['convoca_opcion'] ) ? wp_unslash( (array) $_POST['convoca_opcion'] ) : array(),
			'correctas'   => isset( $_POST['convoca_correcta'] ) ? wp_unslash( (array) $_POST['convoca_correcta'] ) : array(),
			'puntos'      => isset( $_POST['convoca_puntos'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['convoca_puntos'] ) ) : 1.0,
			'explicacion' => isset( $_POST['convoca_explicacion'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_explicacion'] ) ) : '',
			'orden'       => isset( $_POST['convoca_orden'] ) ? absint( wp_unslash( (string) $_POST['convoca_orden'] ) ) : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$result = $edit_id > 0 ? Questions::update( $edit_id, $data ) : Questions::create( $data );

		$aviso   = is_wp_error( $result ) ? 'error' : ( $edit_id > 0 ? 'updated' : 'created' );
		$detalle = is_wp_error( $result ) ? $result->get_error_message() : '';

		$this->back( $exam_id, $aviso, $detalle );
	}

	/**
	 * Borra una pregunta.
	 *
	 * @return void
	 */
	public function handle_question_delete(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el nonce se verifica justo debajo.
		$exam_id     = isset( $_GET['exam_id'] ) ? absint( wp_unslash( (string) $_GET['exam_id'] ) ) : 0;
		$question_id = isset( $_GET['question_id'] ) ? absint( wp_unslash( (string) $_GET['question_id'] ) ) : 0;
		$nonce       = isset( $_GET['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, self::ACTION_QUESTION_DELETE . '_' . $exam_id ) ) {
			wp_die( esc_html__( 'La sesión ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permiso para editar este examen.', 'convoca-courses' ) );
		}

		$hecho = Questions::delete( $question_id );

		$this->back( $exam_id, $hecho ? 'deleted' : 'error' );
	}

	/**
	 * Vuelve al editor del examen con un aviso.
	 *
	 * @param int    $exam_id Examen.
	 * @param string $aviso   Clave del aviso.
	 * @param string $detalle Detalle del error.
	 * @return void
	 */
	private function back( int $exam_id, string $aviso, string $detalle = '' ): void {
		$args = array(
			'convoca_msg' => $aviso,
		);

		if ( '' !== $detalle ) {
			$args['convoca_detail'] = $detalle;
		}

		wp_safe_redirect( add_query_arg( $args, (string) get_edit_post_link( $exam_id, 'raw' ) ) );
		exit;
	}

	/**
	 * Aviso del resultado de una operación con preguntas.
	 *
	 * @return string
	 */
	private function notice_text(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura del aviso a pintar.
		$key     = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : '';
		$detalle = isset( $_GET['convoca_detail'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['convoca_detail'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		switch ( $key ) {
			case 'created':
				return __( 'Pregunta añadida.', 'convoca-courses' );
			case 'updated':
				return __( 'Pregunta actualizada.', 'convoca-courses' );
			case 'deleted':
				return __( 'Pregunta borrada.', 'convoca-courses' );
			case 'error':
				return '' !== $detalle ? $detalle : __( 'No se ha podido guardar la pregunta.', 'convoca-courses' );
			default:
				return '';
		}
	}

	/**
	 * Pinta el aviso en el editor del examen.
	 *
	 * @return void
	 */
	public function notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || CPT_Examen::TYPE !== (string) $screen->post_type ) {
			return;
		}

		$texto = $this->notice_text();
		if ( '' === $texto ) {
			return;
		}

		printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( $texto ) );
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
	 * Módulos para el selector.
	 *
	 * @return array<int, string>
	 */
	private function modules_options(): array {
		$modules = array( 0 => __( '— Todo el curso —', 'convoca-courses' ) );

		foreach ( get_posts(
			array(
				'post_type'      => CPT_Modulo::TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $module ) {
			$modules[ (int) $module->ID ] = (string) $module->post_title;
		}

		return $modules;
	}
}
