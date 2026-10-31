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
	 * Opción con la página pública de acceso.
	 */
	public const OPCION_PAGINA = 'convoca_courses_acceso_page';

	/**
	 * Ruta de esa página.
	 */
	public const SLUG_PAGINA = 'acceso-alumnado';

	/**
	 * Engancha el shortcode y el enlace.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'atender_enlace' ) );

		// Antes que nada: si hay identidad, la respuesta no se puede cachear ni compartir. nginx
		// guardaba la lección de un alumno y la servía a quien ya no tenía acceso (courses#32).
		add_action( 'template_redirect', array( __CLASS__, 'marcar_privado' ), 1 );

		// La página de acceso se crea al activar el plugin (ver `Installer::activate`), no en cada
		// petición: escribir en la base de datos en cada carga sería peor que el problema que resuelve.

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
			wp_die(
				esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-courses' ),
				'',
				array( 'response' => 403 )
			);
		}

		$id = Identidad::alta( $email );

		if ( $id <= 0 ) {
			wp_die(
				esc_html__( 'Ese correo no vale.', 'convoca-courses' ),
				'',
				array( 'response' => 400 )
			);
		}

		if ( self::ACCION . '_codigo' === $accion ) {
			if ( ! Identidad::codigo_valido( $id, $codigo ) ) {
				wp_die(
					esc_html__( 'El código no es válido o ha caducado. Pide otro.', 'convoca-courses' ),
					'',
					array( 'response' => 400 )
				);
			}

			Identidad::consumir( $id );
			self::iniciar( $id );
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		self::enviar( $id );

		wp_safe_redirect( add_query_arg( 'convoca_enviado', '1', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Emite y envía el enlace y el código de acceso.
	 *
	 * @param int $id Identidad.
	 * @return void
	 */
	public static function enviar( int $id ): void {
		$fila = Identidad::por_id( $id );

		if ( null === $fila ) {
			return;
		}

		$email = (string) $fila['email'];
		$par   = Identidad::emitir( $id );

		Notifications::send_to_email(
			$email,
			__( 'Tu acceso al curso', 'convoca-courses' ),
			sprintf(
				/* translators: 1: enlace, 2: código. */
				'%1$s' . "\n\n" . '%2$s' . "\n\n" . '%3$s %4$s' . "\n\n" . '%5$s' . "\n" . '%6$s',
				__( 'Entra con este enlace (caduca en 24 horas):', 'convoca-courses' ),
				add_query_arg(
					array(
						self::PARAM_TOKEN => $par['token'],
						'id'              => $id,
					),
					home_url( '/' )
				),
				__( 'O usa este código:', 'convoca-courses' ),
				$par['codigo'],
				__( 'Si el enlace ha caducado, pide otro aquí:', 'convoca-courses' ),
				self::url_acceso()
			),
			'acceso_identidad'
		);
	}

	/**
	 * Atiende el enlace del correo.
	 *
	 * @return void
	 */
	public static function atender_enlace(): void {
		if ( self::intentar_enlace() ) {
			exit;
		}
	}

	/**
	 * Comprueba el enlace del correo y, si vale, abre la sesión.
	 *
	 * Separado del hook para poder probarlo sin `exit`.
	 *
	 * @return bool `true` si ha abierto sesión y hay que salir.
	 */
	public static function intentar_enlace(): bool {
		if ( ! isset( $_GET[ self::PARAM_TOKEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- es el enlace del correo, va firmado.
			return false;
		}

		$token = sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM_TOKEN ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! Identidad::token_valido( $id, $token ) ) {
			// Antes se caía a la portada sin decir nada: el alumnado no sabía si el enlace era malo,
			// si había caducado o si ya estaba dentro (courses#31).
			wp_safe_redirect( add_query_arg( 'convoca_acceso', 'caducado', self::url_acceso() ) );
			exit;
		}

		Identidad::consumir( $id );
		self::iniciar( $id );

		wp_safe_redirect( remove_query_arg( self::PARAM_TOKEN, add_query_arg( 'id', $id ) ) );

		return true;
	}

	/**
	 * Marca la respuesta como privada cuando hay identidad de alumnado.
	 *
	 * `X-Accel-Expires: 0` es la instrucción que nginx entiende para no guardar la respuesta en su
	 * caché FastCGI; `Cache-Control: private, no-store` cubre navegadores y proxies intermedios.
	 *
	 * @return void
	 */
	public static function marcar_privado(): void {
		if ( Identidad::actual() <= 0 || headers_sent() ) {
			return;
		}

		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Accel-Expires: 0' );
		header( 'Vary: Cookie', false );
	}

	/**
	 * Página pública con el formulario de acceso, creada una sola vez.
	 *
	 * @return int Identificador de la página; 0 si no se pudo crear.
	 */
	public static function pagina(): int {
		$guardada = (int) get_option( self::OPCION_PAGINA, -1 );

		if ( $guardada > 0 && 'page' === get_post_type( $guardada ) ) {
			return $guardada;
		}

		$existente = get_page_by_path( self::SLUG_PAGINA, 'OBJECT', 'page' );

		if ( $existente instanceof \WP_Post ) {
			update_option( self::OPCION_PAGINA, (int) $existente->ID );

			return (int) $existente->ID;
		}

		return 0;
	}

	/**
	 * URL pública para pedir el acceso.
	 *
	 * @return string
	 */
	public static function url_acceso(): string {
		$id = self::pagina();

		return $id > 0 ? (string) get_permalink( $id ) : home_url( '/' );
	}

	/**
	 * Crea la página de acceso si no existe. Idempotente.
	 *
	 * @return void
	 */
	public static function asegurar_pagina(): void {
		if ( self::pagina() > 0 ) {
			return;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Acceso al alumnado', 'convoca-courses' ),
				'post_name'    => self::SLUG_PAGINA,
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $id > 0 ) {
			update_option( self::OPCION_PAGINA, (int) $id );
		}
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

		if ( isset( $_GET['convoca_acceso'] ) && 'caducado' === sanitize_text_field( wp_unslash( (string) $_GET['convoca_acceso'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$aviso = '<div class="convoca-alert convoca-alert--warning">'
				. esc_html__( 'Ese enlace ha caducado. Pide uno nuevo con tu correo aquí abajo, o entra con el código de 6 cifras.', 'convoca-courses' )
				. '</div>';
		}

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
