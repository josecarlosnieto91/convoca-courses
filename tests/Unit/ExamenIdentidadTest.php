<?php
/**
 * Examen y certificado con identidad propia.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\Attempts;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Exam_View;
use Convoca\Courses\Identidad;
use Convoca\Courses\Progress;
use PHPUnit\Framework\TestCase;

/**
 * El alumnado sin cuenta también examina y certifica.
 */
final class ExamenIdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de pruebas.
		$GLOBALS['wpdb'] = new WpdbDoble();
	}

	/**
	 * Restaura el estado.
	 */
	protected function tearDown(): void {
		if ( null === $this->wpdb_previo ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura.
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Base de datos previa.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Sin usuario y sin identidad no se abre un examen.
	 */
	public function test_sin_usuario_ni_identidad_no_abre(): void {
		$resultado = Attempts::open( 0, 99 );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'convoca_courses_no_user', $resultado->get_error_code() );
	}

	/**
	 * El intento ajeno no se puede enviar.
	 */
	public function test_intento_ajeno_no_se_envia(): void {
		$resultado = Attempts::submit( 1, 0, array(), 5 );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
	}

	/**
	 * El progreso por identidad de un curso sin lecciones es cero.
	 */
	public function test_progreso_de_curso_vacio(): void {
		$progreso = Progress::for_course_identidad( 0, 10 );

		$this->assertSame( 0, $progreso['total'] );
		$this->assertSame( 0, $progreso['done'] );
	}

	/**
	 * La nota del examen se resuelve por identidad.
	 */
	public function test_nota_por_identidad(): void {
		$this->assertSame( array(), Attempts::best_by_exam( 0, 10 ) );
		$this->assertSame( 0, Attempts::count_for( 0, 99 ) );
	}

	/**
	 * Con identidad se consultan los intentos de esa identidad.
	 */
	public function test_intentos_por_identidad(): void {
		$this->assertSame( 0, Attempts::count_for( 0, 99, 4 ) );
		$this->assertSame( array(), Attempts::best_by_exam( 0, 10, 4 ) );
	}

	/**
	 * Guardar un intento ajeno devuelve error también con identidad.
	 */
	public function test_intento_ajeno_con_identidad(): void {
		$resultado = Attempts::submit( 1, 0, array(), 4 );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'convoca_courses_no_attempt', $resultado->get_error_code() );
	}

	/**
	 * Siembra curso, módulo, examen y matrícula por identidad.
	 *
	 * @return void
	 */
	private function sembrar_examen(): void {
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_post_types'] = array();

		$GLOBALS['convoca_test_post_types'][3441] = CPT_Curso::TYPE;
		$GLOBALS['convoca_test_meta'][3441][ CPT_Base::META_PREFIX . 'desbloqueo_secuencial' ]    = '0';
		$GLOBALS['convoca_test_meta'][3441][ CPT_Base::META_PREFIX . 'acceso_examen' ]            = 'porcentaje';
		$GLOBALS['convoca_test_meta'][3441][ CPT_Base::META_PREFIX . 'acceso_examen_porcentaje' ] = '0';

		$GLOBALS['convoca_test_post_types'][400]                                 = CPT_Modulo::TYPE;
		$GLOBALS['convoca_test_meta'][400][ CPT_Base::META_PREFIX . 'curso_id' ] = '3441';

		$GLOBALS['convoca_test_post_types'][3457]                                  = CPT_Examen::TYPE;
		$GLOBALS['convoca_test_meta'][3457][ CPT_Base::META_PREFIX . 'modulo_id' ] = '400';
		$GLOBALS['convoca_test_meta'][3457][ CPT_Base::META_PREFIX . 'curso_id' ]  = '3441';

		$GLOBALS['convoca_test_post_types'][900]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][900][ Identidad::META_MATRICULA ]          = '7';
		$GLOBALS['convoca_test_meta'][900][ CPT_Base::META_PREFIX . 'curso_id' ] = '3441';
		$GLOBALS['convoca_test_meta'][900][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
	}

	/**
	 * Con identidad y matrícula, la vista del examen no dice «inicia sesión».
	 */
	public function test_la_vista_abre_para_quien_va_por_identidad(): void {
		$this->sembrar_examen();

		$expira = time() + 3600;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cookie de prueba construida aquí.
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$datos = Exam_View::data( 3457, 0 );

		$this->assertSame( 7, Access::identidad_actual() );
		$this->assertTrue( $datos['logged_in'], 'no puede decir «inicia sesión»' );
		$this->assertTrue( $datos['enrolled'] );
		$this->assertTrue( $datos['can_take'] );

		unset( $_COOKIE[ Identidad::COOKIE ] );
	}

	/**
	 * Sin identidad, el examen sigue pidiendo acceso.
	 */
	public function test_la_vista_sin_identidad_no_abre(): void {
		$this->sembrar_examen();
		unset( $_COOKIE[ Identidad::COOKIE ] );

		$datos = Exam_View::data( 3457, 0 );

		$this->assertFalse( $datos['logged_in'] );
		$this->assertFalse( $datos['enrolled'] );
		$this->assertFalse( $datos['can_take'] );
	}
}
