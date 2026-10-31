<?php
/**
 * Acceso del alumnado sin usuario de WordPress (courses#27).
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Una matrícula por correo tiene que abrir las lecciones igual que una con usuario.
 */
final class AccesoIdentidadTest extends TestCase {

	/**
	 * Curso.
	 */
	private const CURSO = 3441;

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta']       = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$_COOKIE                            = array();
	}

	/**
	 * Siembra curso, módulo, lección y matrícula por identidad.
	 *
	 * @param string $estado Estado de la matrícula.
	 * @return void
	 */
	private function sembrar( string $estado = 'activa' ): void {
		$GLOBALS['convoca_test_post_types'][ self::CURSO ] = CPT_Curso::TYPE;
		$GLOBALS['convoca_test_meta'][ self::CURSO ][ CPT_Base::META_PREFIX . 'desbloqueo_secuencial' ] = '0';

		$GLOBALS['convoca_test_post_types'][400]                                 = CPT_Modulo::TYPE;
		$GLOBALS['convoca_test_meta'][400][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) self::CURSO;

		$GLOBALS['convoca_test_post_types'][500]                                  = CPT_Leccion::TYPE;
		$GLOBALS['convoca_test_meta'][500][ CPT_Base::META_PREFIX . 'modulo_id' ] = '400';

		$GLOBALS['convoca_test_post_types'][900]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][900][ Identidad::META_MATRICULA ]          = '7';
		$GLOBALS['convoca_test_meta'][900][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) self::CURSO;
		$GLOBALS['convoca_test_meta'][900][ CPT_Base::META_PREFIX . 'estado' ]   = $estado;
	}

	/**
	 * Pone la cookie de identidad 7.
	 *
	 * @return void
	 */
	private function cookie(): void {
		$expira = time() + 3600;

		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );
	}

	/**
	 * Con identidad y matrícula activa, la lección se abre.
	 */
	public function test_la_matricula_por_identidad_abre_la_leccion(): void {
		$this->sembrar();
		$this->cookie();

		$this->assertSame( 7, Access::identidad_actual(), 'la cookie se lee' );
		$this->assertSame( 900, Access::matricula_actual( self::CURSO ), 'la matrícula se resuelve por identidad' );
		$this->assertTrue( Access::is_enrolled( 0, self::CURSO ) );
		$this->assertTrue( Access::can_view_lesson( 0, 500 ), 'sin usuario de WordPress se puede abrir la lección' );

		$resumen = Access::summary( 0, self::CURSO );
		$this->assertTrue( $resumen['enrolled'] );
	}

	/**
	 * Sin identidad no se abre: el defecto no puede convertirse en un acceso abierto.
	 */
	public function test_sin_identidad_no_hay_acceso(): void {
		$this->sembrar();

		$this->assertSame( 0, Access::identidad_actual() );
		$this->assertSame( 0, Access::matricula_actual( self::CURSO ) );
		$this->assertFalse( Access::can_view_lesson( 0, 500 ) );
		$this->assertFalse( Access::can_take_exam( 0, self::CURSO ) );
		$this->assertFalse( Access::can_submit_task( 0, self::CURSO ) );
	}

	/**
	 * Una matrícula cancelada no da acceso aunque la cookie sea válida.
	 */
	public function test_una_cancelada_no_abre(): void {
		$this->sembrar( 'cancelada' );
		$this->cookie();

		$this->assertSame( 0, Access::matricula_actual( self::CURSO ) );
		$this->assertFalse( Access::can_view_lesson( 0, 500 ) );
	}

	/**
	 * Una cookie caducada no vale.
	 */
	public function test_una_cookie_caducada_no_vale(): void {
		$this->sembrar();

		$expira                       = time() - 60;
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 0, Access::identidad_actual() );
		$this->assertFalse( Access::can_view_lesson( 0, 500 ) );
	}
}
