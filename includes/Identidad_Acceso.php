<?php
/**
 * Acceso del alumnado con identidad propia: correo, enlace y código.
 *
 * `[convoca_acceso]` pinta el formulario. No hay contraseñas ni usuarios de WordPress.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulario y enlaces de acceso.
 */
class Identidad_Acceso {

	/**
	 * Shortcode.
	 */
	public const SHORTCODE = 'convoca_acceso';

	/**
	 * Acción del formulario.
	 */
	public const ACCION = 'convoca_acceso';

	/**
	 * Parámetro del enlace.
	 */
	public const PARAM_TOKEN = 'convoca_acceso';

	/**
	 * Engancha el shortcode y el enlace.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'atender_enlace' ) );

		foreach ( array( self::ACCION, self::ACCION . '_codigo' ) as $convoca_accion ) {
			add_action( 'admin_post_nopriv_' . $convoca_accion, array( __CLASS__, 'procesar' ) );
			add_action( 'admin_post_' . $convoca_accion, array( __CLASS__, 'procesar' ) );
		}
	}

	/**
	 * Pone la cookie de sesión y sale.
	 *
	 * @param int $id Identidad.
	 * @return void
	 */
	private static function iniciar( int $id ): void {
		$expira = time() + Identidad::SESION;

		setcookie(
			Identidad::COOKIE,
			$id . '|' . $expira . '|' . Identidad::firma_cookie( $id, $expira ),
			array(
				'expires'  => $expira,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Procesa el formulario: enlace + código por correo, o entrada con el código.
	 *
	 * @return void
	 */
	public static function procesar(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- se comprueba justo debajo.
		$accion = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['action'] ) ) : '';
		$nonce  = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$codigo = isset( $_POST['codigo'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['codigo'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, $accion ) ) {
			wp_die( esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-courses' ) );
		}

		$id = Identidad::alta( $email );

		if ( $id <= 0 ) {
			wp_die( esc_html__( 'Ese correo no vale.', 'convoca-courses' ) );
		}

		if ( self::ACCION . '_codigo' === $accion ) {
			if ( ! Identidad::codigo_valido( $id, $codigo ) ) {
				wp_die( esc_html__( 'El código no es válido o ha caducado. Pide otro.', 'convoca-courses' ) );
			}

			Identidad::consumir( $id );
			self::iniciar( $id );
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$par = Identidad::emitir( $id );

		wp_mail(
			$email,
			__( 'Tu acceso al curso', 'convoca-courses' ),
			sprintf(
				/* translators: 1: enlace, 2: código. */
				'%1$s' . "\n\n" . '%2$s' . "\n\n" . '%3$s %4$s',
				__( 'Entra con este enlace (caduca en 30 minutos):', 'convoca-courses' ),
				add_query_arg(
					array(
						self::PARAM_TOKEN => $par['token'],
						'id'              => $id,
					),
					home_url( '/' )
				),
				__( 'O usa este código:', 'convoca-courses' ),
				$par['codigo']
			)
		);

		wp_safe_redirect( add_query_arg( 'convoca_enviado', '1', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Atiende el enlace del correo.
	 *
	 * @return void
	 */
	public static function atender_enlace(): void {
		if ( ! isset( $_GET[ self::PARAM_TOKEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- es el enlace del correo, va firmado.
			return;
		}

		$token = sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM_TOKEN ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! Identidad::token_valido( $id, $token ) ) {
			return;
		}

		Identidad::consumir( $id );

		$expira = time() + Identidad::SESION;
		setcookie(
			Identidad::COOKIE,
			$id . '|' . $expira . '|' . Identidad::firma_cookie( $id, $expira ),
			array(
				'expires'  => $expira,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		wp_safe_redirect( remove_query_arg( self::PARAM_TOKEN, add_query_arg( 'id', $id ) ) );
		exit;
	}

	/**
	 * Pinta el formulario o el saludo.
	 *
	 * @return string
	 */
	public static function render(): string {
		$id = Identidad::actual();

		if ( $id > 0 ) {
			$fila = Identidad::por_id( $id );
			$mail = null !== $fila ? (string) $fila['email'] : '';

			return '<div class="convoca-acceso"><p>'
				. esc_html( sprintf( /* translators: %s: correo. */ __( 'Has entrado como %s.', 'convoca-courses' ), $mail ) )
				. '</p></div>';
		}

		$aviso = '';

		if ( isset( $_GET['convoca_enviado'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$aviso = '<div class="convoca-alert convoca-alert--info">'
				. esc_html__( 'Te hemos enviado un enlace y un código. Revisa el correo.', 'convoca-courses' )
				. '</div>';
		}

		return $aviso . self::formulario();
	}

	/**
	 * Formulario: correo para recibir el enlace y código de respaldo.
	 *
	 * @return string
	 */
	private static function formulario(): string {
		$accion = esc_url( admin_url( 'admin-post.php' ) );

		$html  = '<form class="convoca-acceso__form" method="post" action="' . $accion . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACCION ) . '" />';
		$html .= wp_nonce_field( self::ACCION, '_convoca_nonce', true, false );
		$html .= '<p><label>' . esc_html__( 'Tu correo', 'convoca-courses' ) . '</label>';
		$html .= '<input type="email" name="email" required autocomplete="email" /></p>';
		$html .= '<p><button type="submit" class="convoca-boton">' . esc_html__( 'Enviarme el acceso', 'convoca-courses' ) . '</button></p>';
		$html .= '</form>';

		$html .= '<form class="convoca-acceso__form" method="post" action="' . $accion . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACCION . '_codigo' ) . '" />';
		$html .= wp_nonce_field( self::ACCION . '_codigo', '_convoca_nonce', true, false );
		$html .= '<p><label>' . esc_html__( '¿Ya tienes un código?', 'convoca-courses' ) . '</label>';
		$html .= '<input type="email" name="email" required autocomplete="email" />';
		$html .= '<input type="text" name="codigo" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required />';
		$html .= '</p><p><button type="submit" class="convoca-boton">' . esc_html__( 'Entrar con el código', 'convoca-courses' ) . '</button></p>';
		$html .= '</form>';

		return '<div class="convoca-acceso">' . $html . '</div>';
	}
}
