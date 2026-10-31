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
			$matricula = Enrollment::find_por_identidad( $identidad_id, $course_id, true );

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
			$clave    = isset( $_GET['convoca_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mensajes = array(
				'condiciones' => __( 'Hay que aceptar las condiciones de venta para matricularte.', 'convoca-courses' ),
				'edad'        => __( 'Para matricularte tienes que tener 14 años o más.', 'convoca-courses' ),
				'factura'     => __( 'Para la factura hacen falta el NIF y la razón social.', 'convoca-courses' ),
				'nif'         => __( 'Ese NIF no es válido. Revisa el número y la letra.', 'convoca-courses' ),
				'correo'      => __( 'Ese correo no es válido.', 'convoca-courses' ),
			);
			$mensaje  = $mensajes[ $clave ] ?? __( 'No se ha podido completar la matrícula. Revisa los datos.', 'convoca-courses' );

			return '<div class="convoca-matricula convoca-alert convoca-alert--warning">'
				. esc_html( $mensaje )
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
		$html .= '<p><label for="convoca-email-' . esc_attr( (string) $course_id ) . '">' . esc_html__( 'Tu correo', 'convoca-courses' ) . '</label>';
		$html .= '<input type="email" id="convoca-email-' . esc_attr( (string) $course_id ) . '" name="email" required autocomplete="email" /></p>';
		$html .= '<p><label for="convoca-nombre-' . esc_attr( (string) $course_id ) . '">' . esc_html__( 'Tu nombre', 'convoca-courses' ) . '</label>';
		$html .= '<input type="text" id="convoca-nombre-' . esc_attr( (string) $course_id ) . '" name="nombre" required autocomplete="name" /></p>';

		// Consentimiento informado: sin marcarlo no hay matrícula, y sin él no se puede invocar el 103.m.
		$html .= '<p><label><input type="checkbox" name="consentimiento" value="1" required /> ';
		$html .= esc_html( Condiciones::texto_consentimiento() ) . '</label></p>';

		// La casilla va aparte de la de condiciones, sin marcar y solo en cursos cien por cien digitales: la
		// excepción del 103.m no cubre directos, tutorías ni material físico.
		if ( Condiciones::renuncia_disponible( $course_id ) ) {
			$html .= '<p><label><input type="checkbox" name="renuncia" value="1" /> ';
			$html .= esc_html( Condiciones::texto_renuncia() ) . '</label></p>';
		}

		// Mayores de 14: es la edad mínima con la que se puede contratar el curso.
		$html .= '<p><label><input type="checkbox" name="mayor_14" value="1" required /> ';
		$html .= esc_html__( 'Tengo 14 años o más.', 'convoca-courses' ) . '</label></p>';

		// Factura: opcional. Los datos solo se piden si se marca, y el servidor los exige entonces.
		$html .= '<p><label><input type="checkbox" name="factura" value="1" /> ';
		$html .= esc_html__( 'Necesito factura', 'convoca-courses' ) . '</label></p>';
		$html .= '<p><label for="convoca-nif-' . esc_attr( (string) $course_id ) . '">' . esc_html__( 'NIF / CIF', 'convoca-courses' ) . '</label>';
		$html .= '<input type="text" id="convoca-nif-' . esc_attr( (string) $course_id ) . '" name="factura_nif" autocomplete="off" /></p>';
		$html .= '<p><label for="convoca-razon-' . esc_attr( (string) $course_id ) . '">' . esc_html__( 'Razón social', 'convoca-courses' ) . '</label>';
		$html .= '<input type="text" id="convoca-razon-' . esc_attr( (string) $course_id ) . '" name="factura_razon" autocomplete="organization" /></p>';
		$html .= '<p><label for="convoca-direccion-' . esc_attr( (string) $course_id ) . '">' . esc_html__( 'Dirección fiscal', 'convoca-courses' ) . '</label>';
		$html .= '<input type="text" id="convoca-direccion-' . esc_attr( (string) $course_id ) . '" name="factura_direccion" autocomplete="street-address" /></p>';

		$html .= '<p><button type="submit" class="convoca-boton">' . esc_html__( 'Matricularme', 'convoca-courses' ) . '</button></p>';
		$html .= '</form>';

		// Quien ya se matriculó antes y perdió el correo necesita una puerta de vuelta (courses#31).
		// Si la página de acceso no existe, mejor no poner enlace que mandar a la portada (courses#38).
		if ( Identidad_Acceso::pagina() > 0 ) {
			$html .= '<p class="convoca-matricula__acceso"><a href="' . esc_url( Identidad_Acceso::url_acceso() ) . '">'
				. esc_html__( '¿Ya te has matriculado? Entra aquí', 'convoca-courses' )
				. '</a></p>';
		}

		return '<div class="convoca-matricula">' . $html . '</div>';
	}

	/**
	 * Vuelve al formulario con un aviso, sin morir en una página de error.
	 *
	 * @param int    $course Curso.
	 * @param string $clave  Clave del aviso (condiciones, edad, factura, nif).
	 * @return void
	 */
	private static function volver_con_error( int $course, string $clave ): void {
		$destino = wp_get_referer();

		if ( ! $destino ) {
			$destino = $course > 0 ? (string) get_permalink( $course ) : home_url( '/' );
		}

		$destino = remove_query_arg( array( 'convoca_matricula', 'convoca_error' ), $destino );
		$destino = add_query_arg(
			array(
				'convoca_matricula' => 'error',
				'convoca_error'     => $clave,
			),
			$destino
		);

		wp_safe_redirect( $destino );
		exit;
	}

	/**
	 * Procesa el formulario.
	 *
	 * @return void
	 */
	public static function procesar(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- se comprueba justo debajo.
		$nonce          = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$nombre         = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nombre'] ) ) : '';
		$course         = isset( $_POST['course'] ) ? (int) $_POST['course'] : 0;
		$consentimiento = ! empty( $_POST['consentimiento'] );
		$renuncia       = ! empty( $_POST['renuncia'] );
		$mayor_14       = ! empty( $_POST['mayor_14'] );
		$factura        = ! empty( $_POST['factura'] );
		$factura_nif    = isset( $_POST['factura_nif'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['factura_nif'] ) ) ) : '';
		$factura_razon  = isset( $_POST['factura_razon'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['factura_razon'] ) ) : '';
		$factura_dir    = isset( $_POST['factura_direccion'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['factura_direccion'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::ACCION ) ) {
			// Un nonce caducado es una petición inválida del cliente, no una caída: 403 y no 500
			// (courses#33).
			wp_die(
				esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-courses' ),
				'',
				array( 'response' => 403 )
			);
		}

		// Sin aceptar las condiciones no hay matrícula: es la prueba de que se informó. Se vuelve al
		// formulario con el aviso, no a una página de error en texto plano (courses#41).
		if ( ! $consentimiento ) {
			self::volver_con_error( $course, 'condiciones' );
		}

		if ( ! $mayor_14 ) {
			self::volver_con_error( $course, 'edad' );
		}

		// Si pide factura, el NIF y la razón social son obligatorios, y el NIF tiene que ser válido:
		// una factura con «123» no vale para Hacienda (courses#40).
		if ( $factura && ( '' === $factura_nif || '' === $factura_razon ) ) {
			self::volver_con_error( $course, 'factura' );
		}

		if ( $factura && ! Documento::valido( $factura_nif ) ) {
			self::volver_con_error( $course, 'nif' );
		}

		$resultado = Enrollment::enroll_por_identidad( $email, $nombre, $course );

		if ( ! is_wp_error( $resultado ) && $resultado > 0 ) {
			Embudo::registrar( (int) $course, 'matricula' );
		}

		if ( is_wp_error( $resultado ) ) {
			$estado = 'error';
		} else {
			$estado       = 'ok';
			$matricula_id = (int) $resultado;

			// Se guarda lo que se aceptó y, si se marcó, la renuncia con su prueba: texto exacto, fecha, IP y
			// navegador. Sin eso, la excepción del 103.m no se puede sostener si alguien la discute.
			$renuncia_efectiva = $renuncia && Condiciones::renuncia_disponible( $course );
			$prueba_id         = Condiciones::registrar_aceptacion( $matricula_id, $renuncia_efectiva, $course, $email );

			update_post_meta( $matricula_id, '_convoca_mayor_14', '1' );

			if ( $factura ) {
				update_post_meta( $matricula_id, '_convoca_factura_nif', $factura_nif );
				update_post_meta( $matricula_id, '_convoca_factura_razon', $factura_razon );
				update_post_meta( $matricula_id, '_convoca_factura_direccion', $factura_dir );
			}

			// Y se manda la confirmación en soporte duradero (art. 98.7): es la tercera condición del
			// 103.m. Sin correo de confirmación, la renuncia no opera aunque la casilla esté marcada.
			Condiciones::confirmar( $matricula_id, $course, $email, $nombre, $renuncia_efectiva, $prueba_id );

			// Matriculado, se le manda el acceso: sin esto tendría que descubrir por su cuenta que
			// existe un formulario para pedirlo.
			$identidad = Identidad::por_email( $email );

			if ( null !== $identidad ) {
				Identidad_Acceso::enviar( (int) $identidad['id'] );
			}
		}

		// Se vuelve a la página donde estaba el formulario, no al archivo del curso: allí está el aviso
		// («te hemos enviado el acceso») y allí puede seguir el recorrido. Antes aterrizaba en el listado
		// del CPT y el alumno no veía qué había pasado (courses#27).
		$destino = wp_get_referer();

		if ( ! $destino ) {
			$destino = $course > 0 ? (string) get_permalink( $course ) : home_url( '/' );
		}

		$destino = remove_query_arg( 'convoca_matricula', $destino );

		wp_safe_redirect( add_query_arg( 'convoca_matricula', $estado, $destino ) );
		exit;
	}
}
