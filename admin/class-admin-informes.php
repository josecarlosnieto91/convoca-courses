<?php
/**
 * Pantalla de informes por alumno.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Informe de curso con descargas por alumno.
 */
class Admin_Informes {

	/**
	 * Slug de la pantalla.
	 */
	public const PAGE = 'convoca-courses-informes';

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
			__( 'Informes por alumno', 'convoca-courses' ),
			__( 'Informes', 'convoca-courses' ),
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
			wp_die( esc_html__( 'No tienes permiso para ver informes.', 'convoca-courses' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de filtro.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$cursos    = get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Informes por alumno', 'convoca-courses' ) . '</h1>';

		if ( ! Pro::enabled( 'informes_alumno' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Los informes son una función PRO.', 'convoca-courses' ) . '</p></div></div>';
			return;
		}

		echo '<form method="get"><input type="hidden" name="post_type" value="' . esc_attr( CPT_Curso::TYPE ) . '" /><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '" />';
		echo '<select name="course_id"><option value="0">' . esc_html__( 'Elige un curso', 'convoca-courses' ) . '</option>';
		foreach ( $cursos as $curso ) {
			echo '<option value="' . esc_attr( (string) $curso->ID ) . '" ' . selected( $course_id, (int) $curso->ID, false ) . '>' . esc_html( $curso->post_title ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Ver informe', 'convoca-courses' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( $course_id <= 0 ) {
			echo '</div>';
			return;
		}

		$filas = Reports::rows( $course_id );
		$csv   = add_query_arg(
			array(
				'action'         => Reports::ACTION_CSV,
				'course_id'      => $course_id,
				'_convoca_nonce' => wp_create_nonce( Reports::ACTION_CSV . '_' . $course_id ),
			),
			admin_url( 'admin-post.php' )
		);

		echo '<p><a class="button button-primary" href="' . esc_url( $csv ) . '">' . esc_html__( 'Descargar CSV del curso', 'convoca-courses' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . CPT_Curso::TYPE . '&page=' . Admin_Registro::PAGE ) ) . '">' . esc_html__( 'Ver registro de conexiones', 'convoca-courses' ) . '</a></p>';

		if ( array() === $filas ) {
			echo '<p>' . esc_html__( 'Este curso no tiene alumnado.', 'convoca-courses' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Alumno', 'convoca-courses' ), __( 'Correo', 'convoca-courses' ), __( 'Estado', 'convoca-courses' ), __( 'Progreso', 'convoca-courses' ), __( 'Nota', 'convoca-courses' ), __( 'Conexiones', 'convoca-courses' ), __( 'Última', 'convoca-courses' ), __( 'SCORM', 'convoca-courses' ), __( 'Encuesta', 'convoca-courses' ), '' ) as $columna ) {
			echo '<th>' . esc_html( $columna ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $filas as $fila ) {
			$pdf = add_query_arg(
				array(
					'action'         => Reports::ACTION_PDF,
					'course_id'      => $course_id,
					'user_id'        => (int) $fila['user_id'],
					'identidad_id'   => (int) $fila['identidad_id'],
					'_convoca_nonce' => wp_create_nonce( Reports::ACTION_PDF . '_' . $course_id . '_' . (int) $fila['user_id'] . '_' . (int) $fila['identidad_id'] ),
				),
				admin_url( 'admin-post.php' )
			);

			$nota  = $fila['notas']['nota'];
			$resp  = $fila['encuesta'];
			$media = '';
			if ( is_array( $resp ) && isset( $resp['answers'] ) && is_array( $resp['answers'] ) && array() !== $resp['answers'] ) {
				$media = (string) round( array_sum( array_map( 'intval', $resp['answers'] ) ) / count( $resp['answers'] ), 2 );
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) $fila['nombre'] ) . '</strong></td>';
			echo '<td>' . esc_html( (string) $fila['correo'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['estado'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['progreso'] ) . ' %</td>';
			echo '<td>' . esc_html( null === $nota ? '—' : (string) $nota ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['conexiones']['total'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['conexiones']['ultima'] ) . '</td>';
			echo '<td>' . esc_html( (string) count( (array) $fila['scorm'] ) ) . '</td>';
			echo '<td>' . esc_html( '' === $media ? '—' : $media . ' / 5' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $pdf ) . '">PDF</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$resultados = Survey::results( $course_id );
		$config     = Survey::config( $course_id );
		echo '<h2>' . esc_html__( 'Encuesta de calidad', 'convoca-courses' ) . '</h2>';
		echo '<p>' . esc_html( sprintf( /* translators: %d: respuestas. */ __( '%d respuestas.', 'convoca-courses' ), (int) $resultados['total'] ) ) . '</p>';

		if ( $resultados['total'] > 0 ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Pregunta', 'convoca-courses' ) . '</th><th>' . esc_html__( 'Media', 'convoca-courses' ) . '</th></tr></thead><tbody>';
			foreach ( $config['preguntas'] as $i => $pregunta ) {
				echo '<tr><td>' . esc_html( $pregunta ) . '</td><td>' . esc_html( (string) ( $resultados['medias'][ $i ] ?? '—' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';

			if ( array() !== $resultados['comentarios'] ) {
				echo '<ul>';
				foreach ( $resultados['comentarios'] as $comentario ) {
					echo '<li>' . esc_html( $comentario ) . '</li>';
				}
				echo '</ul>';
			}
		}

		echo '</div>';
	}
}
