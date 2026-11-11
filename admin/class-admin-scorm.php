<?php
/**
 * Subida de paquetes SCORM.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de administración de paquetes SCORM.
 */
class Admin_SCORM {

	/**
	 * Slug de la pantalla.
	 */
	public const PAGE = 'convoca-courses-scorm';

	/**
	 * Engancha el menú y la subida.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . SCORM::ACTION_UPLOAD, array( $this, 'upload' ) );
	}

	/**
	 * Añade la pantalla.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . CPT_Curso::TYPE,
			__( 'Paquetes SCORM', 'convoca-courses' ),
			__( 'SCORM', 'convoca-courses' ),
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
			wp_die( esc_html__( 'No tienes permiso para gestionar paquetes SCORM.', 'convoca-courses' ) );
		}

		$pro     = Pro::enabled( 'scorm' );
		$mensaje = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Paquetes SCORM', 'convoca-courses' ) . '</h1>';

		if ( ! $pro ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'SCORM es una función PRO. Activa la licencia para subir paquetes.', 'convoca-courses' ) . '</p></div>';
			echo '</div>';
			return;
		}

		if ( 'ok' === $mensaje ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Paquete instalado en la lección.', 'convoca-courses' ) . '</p></div>';
		} elseif ( '' !== $mensaje ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $this->mensaje_error( $mensaje ) ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Sube un ZIP con imsmanifest.xml (SCORM 1.2 o SCORM 2004 4ª edición). Se sirve desde este sitio; no se ejecuta código del paquete.', 'convoca-courses' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="' . esc_attr( SCORM::ACTION_UPLOAD ) . '" />';
		wp_nonce_field( SCORM::ACTION_UPLOAD, '_convoca_nonce' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="convoca-scorm-lesson">' . esc_html__( 'Lección', 'convoca-courses' ) . '</label></th><td>';
		echo '<select id="convoca-scorm-lesson" name="lesson_id" required><option value="">' . esc_html__( 'Elige una lección', 'convoca-courses' ) . '</option>';

		foreach ( $this->lecciones() as $leccion ) {
			$meta = SCORM::paquete( (int) $leccion->ID );
			$txt  = $leccion->post_title . ( null !== $meta ? ' — ' . $meta['title'] . ' (' . $meta['version'] . ')' : '' );
			echo '<option value="' . esc_attr( (string) $leccion->ID ) . '">' . esc_html( $txt ) . '</option>';
		}

		echo '</select></td></tr><tr><th scope="row"><label for="convoca-scorm-file">' . esc_html__( 'Paquete ZIP', 'convoca-courses' ) . '</label></th><td>';
		echo '<input type="file" id="convoca-scorm-file" name="scorm_zip" accept=".zip,application/zip" required /></td></tr></table>';
		submit_button( __( 'Subir e instalar', 'convoca-courses' ) );
		echo '</form></div>';
	}

	/**
	 * Lecciones disponibles.
	 *
	 * @return array<int, \WP_Post>
	 */
	private function lecciones(): array {
		return get_posts(
			array(
				'post_type'      => CPT_Leccion::TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Procesa la subida.
	 *
	 * @return void
	 */
	public function upload(): void {
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( (string) $_POST['lesson_id'] ) ) : 0;
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';

		if ( ! Pro::enabled( 'scorm' ) || ! Access::can_manage() || ! wp_verify_nonce( $nonce, SCORM::ACTION_UPLOAD ) ) {
			wp_die( esc_html__( 'No tienes permiso para subir paquetes SCORM.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		if ( empty( $_FILES['scorm_zip']['tmp_name'] ) ) {
			$this->volver( 'sin_archivo' );
		}

		$subida = wp_handle_upload(
			$_FILES['scorm_zip'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- lo valida wp_handle_upload.
			array(
				'test_form' => false,
				'mimes'     => array( 'zip' => 'application/zip' ),
			)
		);

		if ( isset( $subida['error'] ) ) {
			$this->volver( 'subida' );
		}

		$resultado = SCORM::instalar( (string) $subida['file'], $lesson_id );
		@unlink( (string) $subida['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- temporal propio.

		if ( is_wp_error( $resultado ) ) {
			$this->volver( $resultado->get_error_code() );
		}

		$this->volver( 'ok' );
	}

	/**
	 * Vuelve a la pantalla.
	 *
	 * @param string $mensaje Clave.
	 * @return void
	 */
	private function volver( string $mensaje ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE,
					'convoca_msg' => $mensaje,
				),
				admin_url( 'edit.php?post_type=' . CPT_Curso::TYPE )
			)
		);
		exit;
	}

	/**
	 * Mensaje legible de un error.
	 *
	 * @param string $clave Clave.
	 * @return string
	 */
	private function mensaje_error( string $clave ): string {
		$mensajes = array(
			'sin_archivo'      => __( 'No has elegido un ZIP.', 'convoca-courses' ),
			'subida'           => __( 'No se ha podido subir el ZIP.', 'convoca-courses' ),
			'scorm_vacio'      => __( 'El paquete está vacío.', 'convoca-courses' ),
			'scorm_ruta'       => __( 'El paquete contiene una ruta no permitida.', 'convoca-courses' ),
			'scorm_ejecutable' => __( 'El paquete contiene un fichero ejecutable y se ha rechazado.', 'convoca-courses' ),
			'scorm_manifiesto' => __( 'El paquete no contiene un imsmanifest.xml válido.', 'convoca-courses' ),
		);

		return (string) ( $mensajes[ $clave ] ?? __( 'No se ha podido instalar el paquete.', 'convoca-courses' ) );
	}
}
