<?php
/**
 * Cobro de la matrícula con Convoca Gateway (opcional).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puente entre la matrícula del curso y el circuito de pagos del ecosistema.
 *
 * El módulo **no** reimplementa el cobro: crea un pago con la API publicada del gateway y se entera
 * del resultado por los ganchos que el gateway ya dispara. Si el gateway no está activo, el curso se
 * comporta como siempre: gratuito o cobrado fuera y marcado a mano.
 */
class Course_Payment {

	/**
	 * Origen del pago. Debe estar en la lista que admite el gateway.
	 */
	public const ORIGIN = 'courses';

	/**
	 * ¿Se puede cobrar en este sitio?
	 *
	 * Se comprueban las dos cosas: que el núcleo dé el gateway por activo y que la función pública de
	 * creación de pagos exista de verdad. Sin las dos, no se ofrece cobro.
	 *
	 * @return bool
	 */
	public static function gateway_available(): bool {
		if ( ! class_exists( '\Convoca\Core\Features' ) ) {
			return false;
		}

		if ( ! \Convoca\Core\Features::is_gateway_active() ) {
			return false;
		}

		return function_exists( '\Convoca\Gateway\convoca_gateway_create_payment' );
	}

	/**
	 * ¿Este curso necesita cobro?
	 *
	 * Función pura (precio y disponibilidad del gateway) para poder probarla sin WordPress.
	 *
	 * @param float $price             Precio del curso.
	 * @param bool  $gateway_available Si el gateway está operativo.
	 * @return bool
	 */
	public static function needs_payment( float $price, bool $gateway_available ): bool {
		return $price > 0 && $gateway_available;
	}

	/**
	 * Importe en céntimos, redondeado como lo espera el gateway.
	 *
	 * Función pura: el redondeo de un precio con decimales es justo lo que no se puede dejar al azar
	 * cuando el cliente cobra (19,99 € tiene que ser 1999, no 1998).
	 *
	 * @param float $price Precio en la moneda del curso.
	 * @return int
	 */
	public static function amount_cents( float $price ): int {
		return (int) round( max( 0.0, $price ) * 100 );
	}

	/**
	 * ¿Se cobra este curso concreto?
	 *
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function course_needs_payment( int $course_id ): bool {
		return self::needs_payment( CPT_Curso::price( $course_id ), self::gateway_available() );
	}

	/**
	 * Crea el pago de una matrícula y deja el enlace guardado.
	 *
	 * @param int    $enrollment_id Matrícula.
	 * @param int    $course_id     Curso.
	 * @param string $method        Método de pago (tarjeta, bizum, transferencia) o vacío.
	 * @return array{pago_id: int, payment_url: string}|\WP_Error
	 */
	public static function create( int $enrollment_id, int $course_id, string $method = '' ) {
		if ( ! self::gateway_available() ) {
			return new \WP_Error( 'convoca_courses_no_gateway', __( 'El cobro no está disponible en este sitio.', 'convoca-courses' ) );
		}

		$price = CPT_Curso::price( $course_id );
		$cents = self::amount_cents( $price );

		if ( $cents <= 0 ) {
			return new \WP_Error( 'convoca_courses_no_amount', __( 'El curso no tiene importe que cobrar.', 'convoca-courses' ) );
		}

		$user = get_userdata( CPT_Matricula::user_id( $enrollment_id ) );

		$args = array(
			'amount_cents' => $cents,
			'origin'       => self::ORIGIN,
			'origin_id'    => $enrollment_id,
			'product_desc' => mb_substr(
				sprintf(
					/* translators: 1: site name, 2: course title. */
					__( '%1$s — Matrícula del curso %2$s', 'convoca-courses' ),
					strtoupper( (string) get_bloginfo( 'name' ) ),
					(string) get_the_title( $course_id )
				),
				0,
				125
			),
			'payer_email'  => $user ? (string) $user->user_email : '',
		);

		if ( '' !== $method ) {
			$args['method'] = $method;
		}

		// Se cobra por la fachada del núcleo cuando está: así el módulo no depende de las clases de
		// otro plugin, y el día que el gateway cambie no hay que tocar esto. El respaldo directo a la
		// función del gateway es para un núcleo anterior a `Convoca\Core\Payments`.
		if ( class_exists( '\Convoca\Core\Payments' ) && \Convoca\Core\Payments::is_available() ) {
			$payment = \Convoca\Core\Payments::create( $args );
		} else {
			$payment = \Convoca\Gateway\convoca_gateway_create_payment( $args );
		}

		if ( is_wp_error( $payment ) ) {
			if ( class_exists( '\Convoca\Core\Logger' ) ) {
				\Convoca\Core\Logger::error(
					'No se ha podido crear el pago de la matrícula: ' . $payment->get_error_message(),
					'Convoca Courses',
					$enrollment_id
				);
			}
			return $payment;
		}

		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'pago', 'gateway' );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'pago_id', (int) $payment['pago_id'] );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'payment_url', (string) $payment['payment_url'] );

		return array(
			'pago_id'     => (int) $payment['pago_id'],
			'payment_url' => (string) $payment['payment_url'],
		);
	}

	/**
	 * Enlace de pago guardado en una matrícula.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function payment_url( int $enrollment_id ): string {
		return CPT_Matricula::get_string( $enrollment_id, 'payment_url' );
	}

	/**
	 * URL del recibo del pago de una matrícula.
	 *
	 * Se lee por la fachada del núcleo (`Convoca\Core\Payments::receipt_url()`), y solo si no está
	 * se llama al generador del gateway. Sin pago, o sin recibo, devuelve cadena vacía: la plantilla
	 * simplemente no pinta el enlace.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function receipt_url( int $enrollment_id ): string {
		$pago_id = CPT_Matricula::get_int( $enrollment_id, 'pago_id', 0 );

		if ( $pago_id <= 0 ) {
			return '';
		}

		if ( class_exists( '\Convoca\Core\Payments' ) && method_exists( '\Convoca\Core\Payments', 'receipt_url' ) ) {
			return (string) \Convoca\Core\Payments::receipt_url( $pago_id );
		}

		if ( class_exists( '\Convoca\Gateway\Receipt_Generator' ) && method_exists( '\Convoca\Gateway\Receipt_Generator', 'receipt_url' ) ) {
			return (string) \Convoca\Gateway\Receipt_Generator::receipt_url( $pago_id );
		}

		return '';
	}

	/**
	 * Pago completado: activa la matrícula.
	 *
	 * El gateway llama aquí por `convoca_payment_completed`. Solo se atiende el origen propio: si el
	 * pago es de una inscripción o de una cuota, este módulo no toca nada.
	 *
	 * @param int                  $pago_id   Identificador del pago.
	 * @param string               $origin    Origen del pago.
	 * @param int                  $origin_id Identificador en el origen (aquí, la matrícula).
	 * @param array<string, mixed> $meta Meta del pago.
	 * @return void
	 */
	public static function on_payment_completed( $pago_id, $origin, $origin_id, $meta = array() ): void {
		if ( self::ORIGIN !== (string) $origin ) {
			return;
		}

		$enrollment_id = (int) $origin_id;
		if ( $enrollment_id <= 0 || CPT_Matricula::TYPE !== get_post_type( $enrollment_id ) ) {
			if ( class_exists( '\Convoca\Core\Logger' ) ) {
				\Convoca\Core\Logger::error( 'Pago completado con una matrícula que no existe: #' . $enrollment_id, 'Convoca Courses', 0 );
			}
			return;
		}

		$reference = isset( $meta['order_id'] ) ? (string) $meta['order_id'] : '';

		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'referencia_pago', '' !== $reference ? $reference : (string) $pago_id );

		$status = CPT_Matricula::status( $enrollment_id );
		if ( in_array( $status, array( 'pendiente', 'cancelada' ), true ) ) {
			Enrollment::set_status( $enrollment_id, 'activa' );
		}

		/**
		 * Matrícula activada por pago confirmado.
		 *
		 * @param int $enrollment_id Matrícula.
		 * @param int $pago_id       Identificador del pago.
		 */
		do_action( 'convoca_courses_enrollment_paid', $enrollment_id, (int) $pago_id );
	}

	/**
	 * Pago fallido: la matrícula se queda pendiente y queda anotado.
	 *
	 * @param int         $pago_id Identificador del pago.
	 * @param string|null $code    Código de respuesta.
	 * @return void
	 */
	public static function on_payment_failed( $pago_id, $code = null ): void {
		if ( class_exists( '\Convoca\Core\Logger' ) ) {
			\Convoca\Core\Logger::warning(
				sprintf( 'Pago fallido #%d (%s). La matrícula sigue pendiente.', (int) $pago_id, (string) $code ),
				'Convoca Courses',
				0
			);
		}
	}

	/**
	 * Texto del estado de cobro para el panel.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function label( int $enrollment_id ): string {
		$payment = CPT_Matricula::get_int( $enrollment_id, 'pago_id', 0 );

		if ( 0 === $payment ) {
			return CPT_Matricula::get_string( $enrollment_id, 'pago', 'ninguno' );
		}

		return sprintf(
			/* translators: %d: payment identifier. */
			__( 'pago #%d', 'convoca-courses' ),
			$payment
		);
	}
}
