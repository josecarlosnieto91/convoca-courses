<?php
/**
 * Compra de un curso de punta a punta contra un mock de Convoca Gateway.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Integration;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Course_Payment;
use Convoca\Courses\Enrollment;
use PHPUnit\Framework\TestCase;

/**
 * Recorrido de compra: matrícula → pago → cobro confirmado / rechazado.
 *
 * Cubre los tres caminos que pide el contrato: **aprobado**, **rechazado** y **confirmación por
 * webhook** (incluido el webhook duplicado y el de otro origen). El gateway es un mock con su misma
 * firma y sus mismos ganchos; su código no se toca.
 */
class CoursePurchaseTest extends TestCase {

	/**
	 * Curso de pago de las pruebas.
	 *
	 * @var int
	 */
	private $course_id = 0;

	/**
	 * Persona de las pruebas.
	 *
	 * @var int
	 */
	private $user_id = 7;

	/**
	 * Prepara un curso de 25 EUR y limpia el estado del mock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['cc_posts']   = array();
		$GLOBALS['cc_meta']    = array();
		$GLOBALS['cc_actions'] = array();
		$GLOBALS['cc_next_id'] = 100;
		$GLOBALS['cc_gateway'] = array(
			'available'     => true,
			'calls'         => array(),
			'fail_next'     => false,
			'core_calls'    => 0,
			'receipt'       => '',
			'receipt_calls' => 0,
		);

		$this->course_id = wp_insert_post(
			array(
				'post_type'   => CPT_Curso::TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Curso de pago',
			)
		);

		update_post_meta( $this->course_id, '_convoca_precio', 25.0 );
		update_post_meta( $this->course_id, '_convoca_moneda', 'EUR' );
		update_post_meta( $this->course_id, '_convoca_certificado', 0 );
	}

	/**
	 * Da de alta la matrícula pendiente, como hace el formulario cuando el curso se paga.
	 *
	 * @return int
	 */
	private function enroll_pending(): int {
		$enrollment = Enrollment::enroll( $this->user_id, $this->course_id, array( 'estado' => 'pendiente' ) );

		$this->assertNotInstanceOf( \WP_Error::class, $enrollment, 'la matrícula pendiente se crea' );

		return (int) $enrollment;
	}

	/**
	 * Cuenta las acciones apuntadas por el mock.
	 *
	 * @param string $hook Hook.
	 * @return int
	 */
	private function count_actions( string $hook ): int {
		$total = 0;
		foreach ( $GLOBALS['cc_actions'] as $action ) {
			if ( $hook === $action['hook'] ) {
				++$total;
			}
		}
		return $total;
	}

	/**
	 * CAMINO FELIZ: el cobro se confirma y la matrícula se activa.
	 *
	 * @return void
	 */
	public function test_approved_payment_activates_the_enrollment(): void {
		$enrollment_id = $this->enroll_pending();

		$this->assertSame( 'pendiente', CPT_Matricula::status( $enrollment_id ) );
		$this->assertFalse( Access::is_enrolled( $this->user_id, $this->course_id ), 'pendiente no da acceso' );

		$payment = Course_Payment::create( $enrollment_id, $this->course_id );
		$this->assertNotInstanceOf( \WP_Error::class, $payment, 'el pago se crea' );
		$this->assertGreaterThan( 0, $payment['pago_id'] );

		// El gateway recibe lo que tiene que recibir.
		$this->assertCount( 1, $GLOBALS['cc_gateway']['calls'], 'se pide un pago' );
		$sent = $GLOBALS['cc_gateway']['calls'][0];
		$this->assertSame( 'courses', $sent['origin'] );
		$this->assertSame( $enrollment_id, (int) $sent['origin_id'] );
		$this->assertSame( 2500, (int) $sent['amount_cents'], 'el importe viaja en céntimos' );
		$this->assertSame( 1, $GLOBALS['cc_gateway']['core_calls'], 'se cobra por la fachada del núcleo' );

		// Llega la confirmación del TPV.
		\Convoca\Gateway\mock_webhook_completed(
			$payment['pago_id'],
			'courses',
			$enrollment_id,
			array( 'order_id' => 'PRUEBA-OK' )
		);

		$this->assertSame( 'activa', CPT_Matricula::status( $enrollment_id ), 'el cobro confirmado activa la matrícula' );
		$this->assertTrue( Access::is_enrolled( $this->user_id, $this->course_id ), 'con el pago hecho hay acceso' );
		$this->assertSame( 'PRUEBA-OK', CPT_Matricula::get_string( $enrollment_id, 'referencia_pago' ) );
		$this->assertSame( 1, $this->count_actions( 'convoca_courses_enrollment_paid' ) );
	}

	/**
	 * CAMINO RECHAZADO: el cobro no se confirma y la matrícula sigue pendiente, sin acceso.
	 *
	 * @return void
	 */
	public function test_rejected_payment_leaves_the_enrollment_pending(): void {
		$enrollment_id = $this->enroll_pending();
		$payment       = Course_Payment::create( $enrollment_id, $this->course_id );

		\Convoca\Gateway\mock_webhook_failed( $payment['pago_id'], 'SIS0051' );

		$this->assertSame( 'pendiente', CPT_Matricula::status( $enrollment_id ), 'un rechazo no activa nada' );
		$this->assertFalse( Access::is_enrolled( $this->user_id, $this->course_id ), 'sin pagar no hay acceso' );
		$this->assertSame( '', CPT_Matricula::get_string( $enrollment_id, 'referencia_pago' ), 'no se guarda referencia' );
		$this->assertSame( 0, $this->count_actions( 'convoca_courses_enrollment_paid' ) );
		$this->assertSame( 1, $this->count_actions( 'log:warning' ), 'el rechazo queda anotado' );

		// Después de un rechazo se puede reintentar el cobro: el enlace se renueva.
		$retry = Course_Payment::create( $enrollment_id, $this->course_id );
		$this->assertNotInstanceOf( \WP_Error::class, $retry );
		$this->assertCount( 2, $GLOBALS['cc_gateway']['calls'], 'el reintento pide un pago nuevo' );
	}

	/**
	 * WEBHOOK duplicado: el TPV puede avisar dos veces; la segunda no debe duplicar nada.
	 *
	 * @return void
	 */
	public function test_duplicate_webhook_is_idempotent(): void {
		$enrollment_id = $this->enroll_pending();
		$payment       = Course_Payment::create( $enrollment_id, $this->course_id );

		\Convoca\Gateway\mock_webhook_completed( $payment['pago_id'], 'courses', $enrollment_id, array( 'order_id' => 'PRUEBA-1' ) );
		\Convoca\Gateway\mock_webhook_completed( $payment['pago_id'], 'courses', $enrollment_id, array( 'order_id' => 'PRUEBA-1' ) );

		$this->assertSame( 'activa', CPT_Matricula::status( $enrollment_id ) );
		$this->assertSame( 'PRUEBA-1', CPT_Matricula::get_string( $enrollment_id, 'referencia_pago' ) );
		$this->assertSame( 1, Enrollment::occupied_seats( $this->course_id ), 'sigue habiendo una sola matrícula' );
	}

	/**
	 * WEBHOOK de otro origen: no se toca ninguna matrícula de cursos.
	 *
	 * @return void
	 */
	public function test_webhook_from_another_origin_is_ignored(): void {
		$enrollment_id = $this->enroll_pending();

		\Convoca\Gateway\mock_webhook_completed( 4242, 'members', $enrollment_id, array( 'order_id' => 'CUOTA-1' ) );
		\Convoca\Gateway\mock_webhook_completed( 4243, 'enroll', $enrollment_id, array() );

		$this->assertSame( 'pendiente', CPT_Matricula::status( $enrollment_id ), 'un pago de cuota o inscripción no activa el curso' );
		$this->assertFalse( Access::is_enrolled( $this->user_id, $this->course_id ) );
	}

	/**
	 * WEBHOOK con una matrícula que no existe: se anota el error y no se rompe nada.
	 *
	 * @return void
	 */
	public function test_webhook_for_missing_enrollment_does_not_crash(): void {
		\Convoca\Gateway\mock_webhook_completed( 4244, 'courses', 999999, array( 'order_id' => 'X' ) );

		$this->assertSame( 1, $this->count_actions( 'log:error' ), 'se anota el pago huérfano' );
	}

	/**
	 * La pasarela caída no deja la matrícula con un enlace falso.
	 *
	 * @return void
	 */
	public function test_gateway_failure_does_not_store_a_payment_link(): void {
		$enrollment_id = $this->enroll_pending();

		$GLOBALS['cc_gateway']['fail_next'] = true;
		$payment                            = Course_Payment::create( $enrollment_id, $this->course_id );

		$this->assertInstanceOf( \WP_Error::class, $payment, 'se propaga el error del gateway' );
		$this->assertSame( '', Course_Payment::payment_url( $enrollment_id ), 'no se guarda enlace' );
		$this->assertSame( 0, (int) CPT_Matricula::get_int( $enrollment_id, 'pago_id', 0 ), 'no se guarda pago_id' );
		$this->assertSame( 'pendiente', CPT_Matricula::status( $enrollment_id ), 'la matrícula sigue pendiente' );
	}

	/**
	 * Sin gateway activo no se ofrece compra y no se crea nada.
	 *
	 * @return void
	 */
	public function test_without_gateway_the_course_is_not_charged(): void {
		$GLOBALS['cc_gateway']['available'] = false;

		$this->assertFalse( Course_Payment::gateway_available() );
		$this->assertFalse( Course_Payment::course_needs_payment( $this->course_id ) );

		$payment = Course_Payment::create( $this->enroll_pending(), $this->course_id );
		$this->assertInstanceOf( \WP_Error::class, $payment );
		$this->assertCount( 0, $GLOBALS['cc_gateway']['calls'], 'no se llama a la pasarela' );
	}

	/**
	 * El recibo se lee por la fachada del núcleo, no llamando al gateway.
	 *
	 * @return void
	 */
	public function test_receipt_url_comes_from_the_core_facade(): void {
		$enrollment_id = $this->enroll_pending();
		$payment       = Course_Payment::create( $enrollment_id, $this->course_id );

		$GLOBALS['cc_gateway']['receipt'] = 'https://pago.example.org/recibo/' . $payment['pago_id'];

		$this->assertSame(
			'https://pago.example.org/recibo/' . $payment['pago_id'],
			Course_Payment::receipt_url( $enrollment_id ),
			'el recibo sale de la fachada del núcleo'
		);
		$this->assertSame( 1, $GLOBALS['cc_gateway']['receipt_calls'], 'se pregunta una vez' );
	}

	/**
	 * Sin pago no hay recibo que pedir.
	 *
	 * @return void
	 */
	public function test_no_receipt_without_payment(): void {
		$enrollment_id = $this->enroll_pending();

		$this->assertSame( '', Course_Payment::receipt_url( $enrollment_id ) );
		$this->assertSame( 0, $GLOBALS['cc_gateway']['receipt_calls'] ?? 0, 'no se consulta la fachada' );
	}

	/**
	 * Curso gratuito: la matrícula se activa al momento y nunca se llama a la pasarela.
	 *
	 * @return void
	 */
	public function test_free_course_never_calls_the_gateway(): void {
		update_post_meta( $this->course_id, '_convoca_precio', 0 );

		$this->assertFalse( Course_Payment::course_needs_payment( $this->course_id ) );

		$enrollment = Enrollment::enroll( $this->user_id, $this->course_id, array( 'estado' => 'activa' ) );

		$this->assertSame( 'activa', CPT_Matricula::status( (int) $enrollment ) );
		$this->assertTrue( Access::is_enrolled( $this->user_id, $this->course_id ) );
		$this->assertCount( 0, $GLOBALS['cc_gateway']['calls'] );
	}
}
