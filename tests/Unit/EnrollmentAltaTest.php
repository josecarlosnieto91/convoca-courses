<?php
/**
 * Alta y baja de matrículas.
 *
 * `Enrollment::enroll()` es la puerta por la que entra todo el mundo: aquí se decide si una persona
 * puede matricularse, si se le reactiva una matrícula cancelada y si hay plaza de verdad con dos
 * personas a la vez. Lo que se prueba es esa decisión, no el formulario.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Utils;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Enrollment;
use PHPUnit\Framework\TestCase;

/**
 * Alta, baja y cambio de estado de las matrículas.
 */
class EnrollmentAltaTest extends TestCase {

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
		require_once dirname( __DIR__, 2 ) . '/includes/Cohorts.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate_QR.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
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
		$GLOBALS['convoca_test_posts']        = array();
		$GLOBALS['convoca_test_meta']         = array();
		$GLOBALS['convoca_test_post_types']   = array();
		$GLOBALS['_cc_posts']                 = array();
		$GLOBALS['convoca_test_insertados']   = array();
		$GLOBALS['convoca_test_insert_error'] = false;
		$GLOBALS['convoca_test_nuevo_id']     = 100;
		$GLOBALS['convoca_courses_usuarios']  = array();
		$GLOBALS['convoca_courses_filtros']   = array();
		$GLOBALS['convoca_courses_lock_ok']   = true;
		$GLOBALS['convoca_courses_opciones']  = array();
		Utils::$tomados                       = array();
		Utils::$soltados                      = array();
	}

	/**
	 * Sin sesión no hay matrícula.
	 */
	public function test_sin_persona_no_hay_matricula(): void {
		$r = Enrollment::enroll( 0, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_no_user', $r->get_error_code() );
	}

	/**
	 * Un curso que no existe, que no está publicado o que no es un curso no vale.
	 */
	public function test_un_curso_que_no_vale_no_permite_matricularse(): void {
		$this->assertSame( 'convoca_courses_no_course', Enrollment::enroll( 9, 404 )->get_error_code() );

		$this->curso( 3, 'draft' );
		$this->assertSame( 'convoca_courses_no_course', Enrollment::enroll( 9, 3 )->get_error_code() );

		$GLOBALS['convoca_test_posts'][5] = (object) array(
			'ID'          => 5,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Una entrada',
		);
		$this->assertSame( 'convoca_courses_no_course', Enrollment::enroll( 9, 5 )->get_error_code() );
	}

	/**
	 * Quien ya está matriculado no se matricula dos veces.
	 */
	public function test_quien_ya_esta_matriculado_no_se_matricula_dos_veces(): void {
		$this->curso( 3 );
		$this->matricula( 44, 3, 9, 'activa' );

		$r = Enrollment::enroll( 9, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_already', $r->get_error_code() );
	}

	/**
	 * Una matrícula cancelada se reactiva en vez de crear otra.
	 */
	public function test_una_matricula_cancelada_se_reactiva(): void {
		$this->curso( 3 );
		$this->matricula( 44, 3, 9, 'cancelada' );

		$r = Enrollment::enroll( 9, 3 );

		$this->assertSame( 44, $r, 'se devuelve la matrícula que ya había' );
		$this->assertSame( 'activa', $GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ] );
		$this->assertSame( array(), $GLOBALS['convoca_test_insertados'], 'no se crea otra' );
	}

	/**
	 * Una matrícula nueva guarda sus datos.
	 */
	public function test_una_matricula_nueva_guarda_sus_datos(): void {
		$this->curso( 3, 'publish', 25.0, 'EUR' );
		$GLOBALS['convoca_courses_usuarios'][9] = (object) array( 'display_name' => 'Una persona' );

		$id = Enrollment::enroll( 9, 3 );

		$this->assertSame( 100, $id );
		$meta = $GLOBALS['convoca_test_meta'][100];
		$this->assertSame( 3, $meta[ CPT_Base::META_PREFIX . 'curso_id' ] );
		$this->assertSame( 9, $meta[ CPT_Base::META_PREFIX . 'user_id' ] );
		$this->assertSame( 'activa', $meta[ CPT_Base::META_PREFIX . 'estado' ] );
		$this->assertSame( 'ninguno', $meta[ CPT_Base::META_PREFIX . 'pago' ] );
		$this->assertSame( 25.0, $meta[ CPT_Base::META_PREFIX . 'importe' ], 'sin importe, el precio del curso' );
		$this->assertSame( 'EUR', $meta[ CPT_Base::META_PREFIX . 'moneda' ] );
		$this->assertSame( 'general', $meta[ CPT_Base::META_PREFIX . 'motivo_precio' ] );
		$this->assertSame( 0, $meta[ CPT_Base::META_PREFIX . 'convocatoria_id' ] );
		$this->assertStringContainsString( 'Una persona', $GLOBALS['convoca_test_insertados'][0]['post_title'] );
	}

	/**
	 * Los datos que llegan en el alta mandan sobre los del curso.
	 */
	public function test_los_datos_del_alta_mandan_sobre_los_del_curso(): void {
		$this->curso( 3, 'publish', 25.0, 'EUR' );

		Enrollment::enroll(
			9,
			3,
			array(
				'estado'          => 'pendiente',
				'pago'            => 'transferencia',
				'importe'         => 10.0,
				'motivo_precio'   => 'beca',
				'referencia_pago' => 'REF-1',
			)
		);

		$meta = $GLOBALS['convoca_test_meta'][100];
		$this->assertSame( 'pendiente', $meta[ CPT_Base::META_PREFIX . 'estado' ] );
		$this->assertSame( 'transferencia', $meta[ CPT_Base::META_PREFIX . 'pago' ] );
		$this->assertSame( 10.0, $meta[ CPT_Base::META_PREFIX . 'importe' ] );
		$this->assertSame( 'beca', $meta[ CPT_Base::META_PREFIX . 'motivo_precio' ] );
		$this->assertSame( 'REF-1', $meta[ CPT_Base::META_PREFIX . 'referencia_pago' ] );
	}

	/**
	 * Si no se puede tomar el cerrojo, no se reserva plaza.
	 */
	public function test_sin_cerrojo_no_se_reserva_plaza(): void {
		$this->curso( 3, 'publish', 0.0, 'EUR', 10 );
		$GLOBALS['convoca_courses_lock_ok'] = false;

		$r = Enrollment::enroll( 9, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_busy', $r->get_error_code() );
		$this->assertSame( array(), $GLOBALS['convoca_test_insertados'] );
	}

	/**
	 * Curso lleno: se suelta el cerrojo y se dice que no hay plazas.
	 */
	public function test_curso_lleno_suelta_el_cerrojo(): void {
		$this->curso( 3, 'publish', 0.0, 'EUR', 1 );
		$this->matricula( 44, 3, 8, 'activa' );

		$r = Enrollment::enroll( 9, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_full', $r->get_error_code() );
		$this->assertCount( 1, Utils::$tomados, 'se intentó reservar' );
		$this->assertSame( Utils::$tomados[0][0], Utils::$soltados[0], 'y se suelta al no poder' );
	}

	/**
	 * Con plaza, el cerrojo se toma y se suelta.
	 */
	public function test_con_plaza_el_cerrojo_se_toma_y_se_suelta(): void {
		$this->curso( 3, 'publish', 0.0, 'EUR', 10 );

		Enrollment::enroll( 9, 3 );

		$this->assertCount( 1, Utils::$tomados );
		$this->assertSame( 'convoca_courses_seats_3', Utils::$tomados[0][0], 'el cerrojo es del curso' );
		$this->assertSame( array( 'convoca_courses_seats_3' ), Utils::$soltados, 'y se suelta al terminar' );
	}

	/**
	 * Si la creación falla, se suelta el cerrojo y se devuelve el error.
	 */
	public function test_si_la_creacion_falla_se_suelta_el_cerrojo(): void {
		$this->curso( 3, 'publish', 0.0, 'EUR', 10 );
		$GLOBALS['convoca_test_insert_error'] = true;

		$r = Enrollment::enroll( 9, 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'insert_fallido', $r->get_error_code() );
		$this->assertCount( 1, Utils::$soltados, 'el cerrojo no se queda colgado' );
	}

	/**
	 * Cancelar algo que no es una matrícula no hace nada.
	 */
	public function test_cancelar_algo_que_no_es_matricula(): void {
		$GLOBALS['convoca_test_post_types'][5] = 'post';

		$this->assertFalse( Enrollment::cancel( 5 ) );
	}

	/**
	 * Cancelar una matrícula le cambia el estado.
	 */
	public function test_cancelar_una_matricula(): void {
		$this->matricula( 44, 3, 9, 'activa' );

		$this->assertTrue( Enrollment::cancel( 44 ) );
		$this->assertSame( 'cancelada', $GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ] );
	}

	/**
	 * Un estado que no existe no se guarda.
	 */
	public function test_un_estado_que_no_existe_no_se_guarda(): void {
		$this->matricula( 44, 3, 9, 'activa' );

		$this->assertFalse( Enrollment::set_status( 44, 'inventado' ) );
		$this->assertSame( 'activa', $GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ], 'el estado no se toca' );
	}

	/**
	 * Marcar una matrícula como completada la guarda.
	 */
	public function test_marcar_completada_guarda_el_estado(): void {
		$this->matricula( 44, 3, 9, 'activa' );

		$this->assertTrue( Enrollment::set_status( 44, 'completada' ) );
		$this->assertSame( 'completada', $GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ] );
	}

	/**
	 * Da de alta un curso publicado.
	 *
	 * @param int    $id       Identificador.
	 * @param string $estado   Estado de publicación.
	 * @param float  $precio   Precio.
	 * @param string $moneda   Moneda.
	 * @param int    $plazas   Plazas (0 = sin límite).
	 */
	private function curso( int $id, string $estado = 'publish', float $precio = 0.0, string $moneda = 'EUR', int $plazas = 0 ): void {
		$GLOBALS['convoca_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => CPT_Curso::TYPE,
			'post_status' => $estado,
			'post_title'  => 'Curso de setas ' . $id,
		);

		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'precio' ] = (string) $precio;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'moneda' ] = $moneda;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'plazas' ] = (string) $plazas;
	}

	/**
	 * Da de alta una matrícula.
	 *
	 * @param int    $id       Identificador de la matrícula.
	 * @param int    $curso    Curso.
	 * @param int    $persona  Persona.
	 * @param string $estado   Estado.
	 */
	private function matricula( int $id, int $curso, int $persona, string $estado ): void {
		$GLOBALS['convoca_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => CPT_Matricula::TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Matrícula ' . $id,
		);

		$GLOBALS['convoca_test_post_types'][ $id ]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'user_id' ]  = (string) $persona;
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . 'estado' ]   = $estado;

		// El aforo se cuenta con WP_Query, que en el arnés lee de _cc_posts.
		$GLOBALS['_cc_posts'][ CPT_Matricula::TYPE ][] = array(
			'id'     => $id,
			'status' => 'publish',
			'meta'   => array(
				CPT_Base::META_PREFIX . 'curso_id' => (string) $curso,
				CPT_Base::META_PREFIX . 'estado'   => $estado,
			),
		);
	}
}
