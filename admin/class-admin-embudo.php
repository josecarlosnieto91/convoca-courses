<?php
/**
 * Informe del embudo de matrícula.
 *
 * Enseña, por curso, cuántos empiezan la matrícula, cuántos pagan y en qué acaban. Son los datos que el
 * plugin ya recoge: no hay analítica de terceros ni nada que cargar en el frontal.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de administración del embudo.
 */
class Admin_Embudo {

	/**
	 * Capacidad que hace falta para verlo.
	 */
	public const CAP = 'edit_posts';

	/**
	 * Engancha la pantalla.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page(
					'edit.php?post_type=' . CPT_Curso::TYPE,
					__( 'Embudo', 'convoca-courses' ),
					__( 'Embudo', 'convoca-courses' ),
					self::CAP,
					'convoca-courses-embudo',
					array( __CLASS__, 'render' )
				);
			}
		);
	}

	/**
	 * Cursos publicados, para el informe.
	 *
	 * @return array<int,string> ID => título.
	 */
	private static function cursos(): array {
		$posts = get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$salida = array();

		foreach ( (array) $posts as $post ) {
			$salida[ (int) $post->ID ] = (string) $post->post_title;
		}

		return $salida;
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'No tienes permiso para ver el embudo.', 'convoca-courses' ) );
		}

		$cursos = self::cursos();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Embudo de matrícula', 'convoca-courses' ) . '</h1>';
		echo '<p>' . esc_html__( 'Cuántos empiezan, cuántos pagan y en qué acaban. Se cuenta cuando ocurre; no hay cookies de terceros.', 'convoca-courses' ) . '</p>';

		if ( array() === $cursos ) {
			echo '<p>' . esc_html__( 'Todavía no hay cursos publicados.', 'convoca-courses' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Curso', 'convoca-courses' ),
			__( 'Matrículas', 'convoca-courses' ),
			__( 'Pagos', 'convoca-courses' ),
			__( 'Tasa de pago', 'convoca-courses' ),
			__( 'Activas', 'convoca-courses' ),
			__( 'Completadas', 'convoca-courses' ),
			__( 'Canceladas', 'convoca-courses' ),
		) as $columna ) {
			echo '<th>' . esc_html( $columna ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $cursos as $curso_id => $titulo ) {
			$fila = Embudo::resumen( $curso_id );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $titulo ) . '</strong></td>';
			echo '<td>' . esc_html( (string) $fila['matricula'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['pago'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $fila['tasa_pago'], 1 ) ) . '%</td>';
			echo '<td>' . esc_html( (string) $fila['activas'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['completadas'] ) . '</td>';
			echo '<td>' . esc_html( (string) $fila['canceladas'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><small>' . esc_html__( 'Matrículas y pagos son contadores de evento. Activas, completadas y canceladas salen del estado actual de cada matrícula.', 'convoca-courses' ) . '</small></p>';
		echo '</div>';
	}
}
