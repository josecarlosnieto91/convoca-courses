<?php
/**
 * El aforo de un curso: plazas ocupadas, plazas libres y qué cuenta como ocupar.
 *
 * `Enrollment` estaba al 0 % (126 statements) y es el corazón del módulo: si el aforo se cuenta mal,
 * se vende una plaza que no existe. Lo que se prueba aquí es la aritmética y, sobre todo, el caso que
 * rompe las matrículas de verdad: **una sobreventa no puede dar plazas libres negativas**.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Enrollment;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';

/**
 * Aforo y ocupación de plazas.
 */
final class EnrollmentAforoTest extends TestCase {

	/**
	 * Cada prueba parte de un centro vacío: ni cursos ni matrículas.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_cc_posts']         = array();
		$GLOBALS['convoca_test_meta'] = array();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_cc_posts']         = array();
		$GLOBALS['convoca_test_meta'] = array();
	}

	/**
	 * Siembra un curso con sus plazas.
	 *
	 * @param int $curso  Identificador del curso.
	 * @param int $plazas Plazas ofertadas (0 = sin límite).
	 * @return void
	 */
	private function curso( int $curso, int $plazas ): void {
		$GLOBALS['convoca_test_meta'][ $curso ][ CPT_Base::META_PREFIX . 'plazas' ] = $plazas;
	}

	/**
	 * Siembra una matrícula con su estado.
	 *
	 * @param int          $id     Identificador de la matrícula.
	 * @param int          $curso  Curso.
	 * @param string|array $estado Estado, o lista de estados (uno por matrícula).
	 * @return void
	 */
	private function matricula( int $id, int $curso, $estado ): void {
		if ( is_array( $estado ) ) {
			foreach ( $estado as $cada ) {
				$this->matricula( $id++, $curso, $cada );
			}
			return;
		}
		$GLOBALS['_cc_posts'][ CPT_Matricula::TYPE ][] = array(
			'id'     => $id,
			'status' => 'publish',
			'meta'   => array(
				CPT_Base::META_PREFIX . 'curso_id' => $curso,
				CPT_Base::META_PREFIX . 'estado'   => $estado,
			),
		);
	}

	/**
	 * Sin matrículas no hay plazas ocupadas.
	 *
	 * @return void
	 */
	public function test_sin_matriculas_no_hay_plazas_ocupadas(): void {
		$this->curso( 1, 10 );

		$this->assertSame( 0, Enrollment::occupied_seats( 1 ) );
	}

	/**
	 * Se cuentan las matrículas de ese curso, y solo las que ocupan plaza.
	 *
	 * @return void
	 */
	public function test_se_cuentan_las_que_ocupan_plaza_de_ese_curso(): void {
		$this->curso( 1, 10 );
		$this->matricula( 11, 1, 'activa' );
		$this->matricula( 12, 1, 'pendiente' );
		$this->matricula( 13, 1, 'cancelada' );
		$this->matricula( 14, 2, 'activa' );

		$this->assertSame( 2, Enrollment::occupied_seats( 1 ), 'una cancelada no ocupa y otro curso no cuenta' );
	}

	/**
	 * Todos los estados que ocupan plaza ocupan, y ninguno de los demás cuenta.
	 *
	 * @return void
	 */
	public function test_todos_los_estados_que_ocupan_cuentan(): void {
		$this->curso( 1, 20 );
		$this->matricula( 30, 1, Enrollment::OCCUPYING );

		$this->assertSame(
			count( Enrollment::OCCUPYING ),
			Enrollment::occupied_seats( 1 ),
			'cada estado de la lista tiene que contar: si uno deja de contar, se vende una plaza de más'
		);
	}

	/**
	 * Un curso con plazas y ninguna matrícula las tiene todas libres.
	 *
	 * @return void
	 */
	public function test_un_curso_vacio_tiene_todas_sus_plazas_libres(): void {
		$this->curso( 1, 10 );

		$this->assertSame( 10, Enrollment::free_seats( 1 ) );
	}

	/**
	 * Con plazas ocupadas, las libres son la resta.
	 *
	 * @return void
	 */
	public function test_las_plazas_libres_son_la_resta(): void {
		$this->curso( 1, 10 );
		$this->matricula( 11, 1, 'activa' );
		$this->matricula( 12, 1, 'pendiente' );
		$this->matricula( 13, 1, 'completada' );

		$this->assertSame( 7, Enrollment::free_seats( 1 ) );
	}

	/**
	 * Un curso lleno no tiene plazas, y no da error.
	 *
	 * @return void
	 */
	public function test_un_curso_lleno_no_tiene_plazas_libres(): void {
		$this->curso( 1, 2 );
		$this->matricula( 11, 1, 'activa' );
		$this->matricula( 12, 1, 'activa' );

		$this->assertSame( 0, Enrollment::free_seats( 1 ) );
	}

	/**
	 * El caso que rompe matrículas: una sobreventa no puede dar plazas negativas.
	 *
	 * @return void
	 */
	public function test_una_sobreventa_no_da_plazas_negativas(): void {
		$this->curso( 1, 2 );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->matricula( 20 + $i, 1, 'activa' );
		}

		$this->assertSame( 5, Enrollment::occupied_seats( 1 ), 'la cuenta real son cinco' );
		$this->assertSame( 0, Enrollment::free_seats( 1 ), 'nunca -3: la interfaz mostraría plazas libres de menos' );
	}

	/**
	 * Un curso sin límite de plazas devuelve −1, que significa «sin aforo», no «lleno».
	 *
	 * @return void
	 */
	public function test_un_curso_sin_aforo_devuelve_menos_uno(): void {
		$this->curso( 1, 0 );

		$this->assertSame( -1, Enrollment::free_seats( 1 ), '-1 es «sin límite», distinto de 0 que es «lleno»' );
	}

	/**
	 * Y sin límite, llenarlo de matrículas sigue dando −1.
	 *
	 * @return void
	 */
	public function test_un_curso_sin_aforo_sigue_sin_aforo_tras_matricular(): void {
		$this->curso( 1, 0 );
		$this->matricula( 11, 1, 'activa' );

		$this->assertSame( -1, Enrollment::free_seats( 1 ) );
	}
}
