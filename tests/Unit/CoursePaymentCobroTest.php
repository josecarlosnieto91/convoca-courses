<?php
/**
 * Cobro de la matrícula: crear el pago, los enlaces y lo que pasa cuando el pago vuelve.
 *
 * Completa `CoursePaymentTest`, que ya cubre «¿hay que cobrar?» y los céntimos. Aquí va el resto: que
 * se cobre **el importe guardado en la matrícula** —el que se le prometió a la persona— y que un pago
 * confirmado de otro origen no toque nada.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Payments;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Course_Payment;
use PHPUnit\Framework\TestCase;

/**
 * Creación del pago, enlaces y retorno de la pasarela.
 */
class CoursePaymentCobroTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pricing.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate_QR.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Course_Payment.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		$GLOBALS['convoca_test_meta']                   = array();
		$GLOBALS['convoca_test_post_types']             = array();
		$GLOBALS['convoca_test_posts']                  = array();
		$GLOBALS['convoca_courses_usuarios']            = array();
		$GLOBALS['convoca_courses_gateway_activo']      = false;
		$GLOBALS['convoca_courses_payments_disponible'] = false;
		$GLOBALS['convoca_courses_pago_error']          = false;
		$GLOBALS['convoca_courses_pago_id']             = 555;
		$GLOBALS['convoca_courses_pago_url']            = 'https://pagos.example.test/555';
		$GLOBALS['convoca_courses_acciones']            = array();
		Payments::$creados                              = array();
	}

	/**
	 * Crear el pago sin pasarela devuelve error.
	 */
	public function test_crear_sin_pasarela_devuelve_error(): void {
		$r = Course_Payment::create( 44, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_gateway', $r->get_error_code() );
	}

	/**
	 * Con pasarela pero sin importe, tampoco se crea el pago.
	 */
	public function test_sin_importe_no_se_crea_el_pago(): void {
		$this->pasarela_activa();
		$this->matricula( 44, 3, 9, 'pendiente', 0.0 );

		$r = Course_Payment::create( 44, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_amount', $r->get_error_code() );
	}

	/**
	 * Se cobra el importe guardado en la matrícula, no el precio de tarifa.
	 */
	public function test_se_cobra_el_importe_de_la_matricula(): void {
		$this->pasarela_activa();
		$this->curso( 3, 50.0 );
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		$r = Course_Payment::create( 44, 3, 'tarjeta' );

		$this->assertSame( 555, $r['pago_id'] );
		$this->assertSame( 'https://pagos.example.test/555', $r['payment_url'] );
		$this->assertSame( 1000, Payments::$creados[0]['amount_cents'], 'se cobra lo prometido (10 €), no la tarifa (50 €)' );
		$this->assertSame( 44, Payments::$creados[0]['origin_id'] );
		$this->assertSame( 'courses', Payments::$creados[0]['origin'] );
		$this->assertSame( 'tarjeta', Payments::$creados[0]['method'] );
		$this->assertStringContainsString( 'Matrícula del curso', Payments::$creados[0]['product_desc'] );
		$this->assertSame( 'persona@example.test', Payments::$creados[0]['payer_email'] );
	}

	/**
	 * Sin método de pago, no se envía el campo.
	 */
	public function test_sin_metodo_no_se_envia_el_campo(): void {
		$this->pasarela_activa();
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::create( 44, 3 );

		$this->assertArrayNotHasKey( 'method', Payments::$creados[0] );
	}

	/**
	 * Al crear el pago, la matrícula queda marcada con su enlace.
	 */
	public function test_al_crear_el_pago_la_matricula_queda_marcada(): void {
		$this->pasarela_activa();
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::create( 44, 3 );

		$this->assertSame( 'gateway', $this->meta( 44, 'pago' ) );
		$this->assertSame( 555, $this->meta( 44, 'pago_id' ) );
		$this->assertSame( 'https://pagos.example.test/555', $this->meta( 44, 'payment_url' ) );
	}

	/**
	 * Si la pasarela falla, no se marca nada en la matrícula.
	 */
	public function test_si_la_pasarela_falla_no_se_marca_nada(): void {
		$this->pasarela_activa();
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );
		$GLOBALS['convoca_courses_pago_error'] = true;

		$r = Course_Payment::create( 44, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertNull( $this->meta( 44, 'pago_id' ), 'sin pago no hay identificador guardado' );
	}

	/**
	 * El enlace de pago guardado se devuelve tal cual.
	 */
	public function test_el_enlace_de_pago_guardado(): void {
		$this->assertSame( '', Course_Payment::payment_url( 44 ) );

		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'payment_url' ] = 'https://pagos.example.test/9';

		$this->assertSame( 'https://pagos.example.test/9', Course_Payment::payment_url( 44 ) );
	}

	/**
	 * El recibo necesita un pago; sin él no hay enlace.
	 */
	public function test_el_recibo_necesita_un_pago(): void {
		$this->assertSame( '', Course_Payment::receipt_url( 44 ) );

		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'pago_id' ] = '555';
		$GLOBALS['convoca_courses_payments_disponible']                        = true;

		$this->assertSame( 'https://pagos.example.test/recibo/555', Course_Payment::receipt_url( 44 ) );
	}

	/**
	 * Un pago confirmado de otro origen no toca la matrícula.
	 */
	public function test_un_pago_de_otro_origen_no_toca_nada(): void {
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::on_payment_completed( 555, 'shifts', 44 );

		$this->assertSame( 'pendiente', $this->meta( 44, 'estado' ), 'una inscripción no activa una matrícula' );
	}

	/**
	 * Un pago confirmado con una matrícula que no existe no revienta.
	 */
	public function test_un_pago_con_matricula_inexistente_no_revienta(): void {
		Course_Payment::on_payment_completed( 555, 'courses', 999 );

		// 999 no es una matrícula: no se le escribe nada encima.
		$this->assertNull( $this->meta( 999, 'referencia_pago' ), 'no se anota la referencia en algo que no es una matrícula' );
		$this->assertNull( $this->meta( 999, 'estado' ), 'ni se le cambia el estado' );
	}

	/**
	 * Un identificador que no es una matrícula (una entrada normal) tampoco se toca.
	 */
	public function test_un_pago_sobre_otro_tipo_de_contenido_no_toca_nada(): void {
		$GLOBALS['convoca_test_post_types'][77] = 'post';

		Course_Payment::on_payment_completed( 555, 'courses', 77 );

		$this->assertNull( $this->meta( 77, 'referencia_pago' ) );
	}

	/**
	 * Un pago confirmado activa la matrícula pendiente y guarda la referencia del pedido.
	 */
	public function test_un_pago_confirmado_activa_la_matricula(): void {
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::on_payment_completed( 555, 'courses', 44, array( 'order_id' => 'PED-9' ) );

		$this->assertSame( 'activa', $this->meta( 44, 'estado' ) );
		$this->assertSame( 'PED-9', $this->meta( 44, 'referencia_pago' ), 'se guarda la referencia del pedido' );
	}

	/**
	 * Sin referencia del pedido, se guarda el identificador del pago.
	 */
	public function test_sin_referencia_se_guarda_el_pago(): void {
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::on_payment_completed( 555, 'courses', 44 );

		$this->assertSame( '555', $this->meta( 44, 'referencia_pago' ) );
	}

	/**
	 * Un pago confirmado no retrocede una matrícula ya completada.
	 */
	public function test_un_pago_confirmado_no_toca_una_matricula_completada(): void {
		$this->matricula( 44, 3, 9, 'completada', 10.0 );

		Course_Payment::on_payment_completed( 555, 'courses', 44 );

		$this->assertSame( 'completada', $this->meta( 44, 'estado' ) );
	}

	/**
	 * Un pago fallido deja la matrícula como estaba.
	 */
	public function test_un_pago_fallido_no_toca_la_matricula(): void {
		$this->matricula( 44, 3, 9, 'pendiente', 10.0 );

		Course_Payment::on_payment_failed( 555, 'SIS0051' );

		$this->assertSame( 'pendiente', $this->meta( 44, 'estado' ) );
	}

	/**
	 * El texto del estado de cobro: el método si no hay pago, y el número si lo hay.
	 */
	public function test_el_texto_del_estado_de_cobro(): void {
		$this->assertSame( 'ninguno', Course_Payment::label( 44 ) );

		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'pago' ]    = 'transferencia';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'pago_id' ] = '555';

		$this->assertSame( 'pago #555', Course_Payment::label( 44 ) );
	}

	/**
	 * Deja la pasarela activa.
	 */
	private function pasarela_activa(): void {
		$GLOBALS['convoca_courses_gateway_activo']      = true;
		$GLOBALS['convoca_courses_payments_disponible'] = true;
	}

	/**
	 * Lee la meta de una matrícula.
	 *
	 * @param int    $id    Matrícula.
	 * @param string $clave Clave sin prefijo.
	 * @return mixed
	 */
	private function meta( int $id, string $clave ) {
		return $GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . $clave ] ?? null;
	}

	/**
	 * Da de alta un curso con su precio de tarifa.
	 *
	 * @param int   $id     Curso.
	 * @param float $precio Precio.
	 */
	private function curso( int $id, float $precio ): void {
		$GLOBALS['convoca_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => CPT_Curso::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Curso ' . $id,
		);

		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'precio' ] = (string) $precio;
	}

	/**
	 * Da de alta una matrícula con su importe.
	 *
	 * @param int    $id      Matrícula.
	 * @param int    $curso   Curso.
	 * @param int    $persona Persona.
	 * @param string $estado  Estado.
	 * @param float  $importe Importe guardado.
	 */
	private function matricula( int $id, int $curso, int $persona, string $estado, float $importe ): void {
		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = $estado;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'importe' ]  = (string) $importe;
		$GLOBALS['convoca_courses_usuarios'][ $persona ]                           = (object) array(
			'ID'         => $persona,
			'user_email' => 'persona@example.test',
		);
	}
}
