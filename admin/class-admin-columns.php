<?php
/**
 * Columnas de las listas del panel.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Columnas legibles en las listas de matrículas y lecciones.
 */
class Admin_Columns {

	/**
	 * Registra los enganches.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_' . CPT_Matricula::TYPE . '_posts_columns', array( $this, 'matricula_columns' ) );
		add_action( 'manage_' . CPT_Matricula::TYPE . '_posts_custom_column', array( $this, 'matricula_cell' ), 10, 2 );

		add_filter( 'manage_' . CPT_Leccion::TYPE . '_posts_columns', array( $this, 'leccion_columns' ) );
		add_action( 'manage_' . CPT_Leccion::TYPE . '_posts_custom_column', array( $this, 'leccion_cell' ), 10, 2 );

		add_filter( 'manage_' . CPT_Curso::TYPE . '_posts_columns', array( $this, 'curso_columns' ) );
		add_action( 'manage_' . CPT_Curso::TYPE . '_posts_custom_column', array( $this, 'curso_cell' ), 10, 2 );
	}

	/**
	 * Columnas de matrícula.
	 *
	 * @param array<string, string> $columns Columnas.
	 * @return array<string, string>
	 */
	public function matricula_columns( array $columns ): array {
		$date = isset( $columns['date'] ) ? $columns['date'] : '';

		unset( $columns['date'] );

		$columns['convoca_persona'] = __( 'Persona', 'convoca-courses' );
		$columns['convoca_curso']   = __( 'Curso', 'convoca-courses' );
		$columns['convoca_estado']  = __( 'Estado', 'convoca-courses' );
		$columns['convoca_cert']    = __( 'Certificado', 'convoca-courses' );

		if ( '' !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Contenido de las celdas de matrícula.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id Matrícula.
	 * @return void
	 */
	public function matricula_cell( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'convoca_persona':
				$user = get_userdata( CPT_Matricula::user_id( $post_id ) );
				echo esc_html( $user ? $user->display_name : '—' );
				break;
			case 'convoca_curso':
				$course_id = CPT_Matricula::course_id( $post_id );
				echo esc_html( $course_id > 0 ? (string) get_the_title( $course_id ) : '—' );
				break;
			case 'convoca_estado':
				$label = CPT_Matricula::status( $post_id );
				$pay   = Course_Payment::label( $post_id );
				echo 'gateway' === CPT_Matricula::get_string( $post_id, 'pago' )
					? esc_html( $label . ' · ' . $pay )
					: esc_html( $label );
				break;
			case 'convoca_cert':
				$code = CPT_Matricula::get_string( $post_id, 'cert_codigo' );
				echo '' !== $code ? esc_html( $code ) : '—';
				break;
		}
	}

	/**
	 * Columnas de lección.
	 *
	 * @param array<string, string> $columns Columnas.
	 * @return array<string, string>
	 */
	public function leccion_columns( array $columns ): array {
		$columns['convoca_tipo']   = __( 'Tipo', 'convoca-courses' );
		$columns['convoca_modulo'] = __( 'Módulo', 'convoca-courses' );

		return $columns;
	}

	/**
	 * Contenido de las celdas de lección.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id Lección.
	 * @return void
	 */
	public function leccion_cell( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'convoca_tipo':
				echo esc_html( CPT_Leccion::type( $post_id ) );
				break;
			case 'convoca_modulo':
				$module_id = CPT_Leccion::module_id( $post_id );
				echo esc_html( $module_id > 0 ? (string) get_the_title( $module_id ) : '—' );
				break;
		}
	}

	/**
	 * Columnas de curso.
	 *
	 * @param array<string, string> $columns Columnas.
	 * @return array<string, string>
	 */
	public function curso_columns( array $columns ): array {
		$columns['convoca_precio']     = __( 'Precio', 'convoca-courses' );
		$columns['convoca_matriculas'] = __( 'Matrículas', 'convoca-courses' );

		return $columns;
	}

	/**
	 * Contenido de las celdas de curso.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id Curso.
	 * @return void
	 */
	public function curso_cell( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'convoca_precio':
				$price = CPT_Curso::price( $post_id );
				echo esc_html( $price > 0 ? CPT_Curso::formatted_price( $post_id ) : __( 'Gratis', 'convoca-courses' ) );
				break;
			case 'convoca_matriculas':
				echo esc_html( (string) Enrollment::occupied_seats( $post_id ) );
				break;
		}
	}
}
