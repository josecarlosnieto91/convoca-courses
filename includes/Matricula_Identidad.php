<?php
/**
 * Matrícula sin cuenta de WordPress.
 *
 * `[convoca_matricula course="123"]` pide correo y nombre, crea la identidad y matricula. En un curso de pago la
 * matrícula queda a la espera del cobro, igual que siempre; en uno gratuito entra directa.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulario de matrícula por identidad.
 */
class Matricula_Identidad {

	/**
	 * Shortcode.
	 */
	public const SHORTCODE = 'convoca_matricula';

	/**
	 * Acción del formulario.
	 */
	public const ACCION = 'convoca_matricula';

	/**
	 * Engancha el shortcode y el formulario.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACCION, array( __CLASS__, 'procesar' ) );
		add_action( 'admin_post_' . self::ACCION, array( __CLASS__, 'procesar' ) );
	}

	/**
	 * Pinta el formulario o el estado de la matrícula.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'course' => '0',
			),
			$atts,
			self::SHORTCODE
		);

		$course_id = (int) $atts['course'];

		if ( $course_id <= 0 ) {
			return '';
		}

		$identidad_id = Identidad::actual();

		if ( $identidad_id > 0 ) {
			$matricula = Enrollment::find_por_identidad( $identidad_id, $course_id );

			if ( $matricula > 0 ) {
				$estado = CPT_Matricula::status( $matricula );

				return '<div class="convoca-matricula convoca-alert convoca-alert--info">'
					. esc_html(
						sprintf(
							/* translators: %s: estado de la matrícula. */
							__( 'Tu matrícula está en estado: %s.', 'convoca-courses' ),
							$estado
						)
					)
					. '</div>';
			}
		}

		$resultado = isset( $_GET['convoca_matricula'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['convoca_matricula'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'ok' === $resultado ) {
			return '<div class="convoca-matricula convoca-alert convoca-alert--success">'
				. esc_html__( 'Matrícula hecha. Revisa tu correo para entrar al curso.', 'convoca-courses' )
				. '</div>';
		}

		if ( 'error' === $resultado ) {
			return '<div class="convoca-matricula convoca-alert convoca-alert--warning">'
				. esc_html__( 'No se ha podido completar la matrícula. Revisa el correo.', 'convoca-courses' )
				. '</div>';
		}

		return self::formulario( $course_id );
	}

	/**
	 * Formulario: correo y nombre.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	private static function formulario( int $course_id ): string {
		$html  = '<form class="convoca-matricula__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACCION ) . '" />';
		$html .= '<input type="hidden" name="course" value="' . esc_attr( (string) $course_id ) . '" />';
		$html .= wp_nonce_field( self::ACCION, '_convoca_nonce', true, false );
		$html .= '<p><label>' . esc_html__( 'Tu correo', 'convoca-courses' ) . '</label>';
		$html .= '<input type="email" name="email" required autocomplete="email" /></p>';
		$html .= '<p><label>' . esc_html__( 'Tu nombre', 'convoca-courses' ) . '</label>';
		$html .= '<input type="text" name="nombre" required autocomplete="name" /></p>';
		$html .= '<p><button type="submit" class="convoca-boton">' . esc_html__( 'Matricularme', 'convoca-courses' ) . '</button></p>';
		$html .= '</form>';

		return '<div class="convoca-matricula">' . $html . '</div>';
	}

	/**
	 * Procesa el formulario.
	 *
	 * @return void
	 */
	public static function procesar(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- se comprueba justo debajo.
		$nonce  = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nombre'] ) ) : '';
		$course = isset( $_POST['course'] ) ? (int) $_POST['course'] : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACCION ) ) {
			wp_die( esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		$resultado = Enrollment::enroll_por_identidad( $email, $nombre, $course );
		$estado    = is_wp_error( $resultado ) ? 'error' : 'ok';

		// Matriculado, se le manda el acceso: sin esto tendría que descubrir por su cuenta que existe
		// un formulario para pedirlo.
		if ( 'ok' === $estado ) {
			$identidad = Identidad::por_email( $email );

			if ( null !== $identidad ) {
				Identidad_Acceso::enviar( (int) $identidad['id'] );
			}
		}

		$destino = $course > 0 ? get_permalink( $course ) : home_url( '/' );

		wp_safe_redirect( add_query_arg( 'convoca_matricula', $estado, $destino ? $destino : home_url( '/' ) ) );
		exit;
	}
}
