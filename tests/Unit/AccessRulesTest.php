<?php
/**
 * Pruebas de las reglas de acceso: ventana de matrícula, caducidad y desbloqueo del examen.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Curso;
use PHPUnit\Framework\TestCase;

/**
 * Todo lo que se decide aquí está en funciones puras a propósito: son las reglas que abren o cierran el
 * acceso a un curso, y un error de un día deja a alguien fuera o le abre lo que no pagó.
 *
 * Las claves de los arrays van sin acentos: la alineación de PHPCS cuenta bytes y deja de ser
 * reproducible entre el runner y local.
 */
class AccessRulesTest extends TestCase {

	/**
	 * Casos de la ventana de matrícula.
	 *
	 * @return array<string, array{0: int, 1: int, 2: int, 3: bool}>
	 */
	public static function ventana_cases(): array {
		$dia = 86400;

		return array(
			'sin limites'       => array( 0, 0, 1000, true ),
			'antes de abrir'    => array( 1000, 2000, 999, false ),
			'justo al abrir'    => array( 1000, 2000, 1000, true ),
			'dentro'            => array( 1000, 2000, 1500, true ),
			'justo al cerrar'   => array( 1000, 2000, 2000, true ),
			'despues de cerrar' => array( 1000, 2000, 2001, false ),
			'solo apertura'     => array( 1000, 0, 1000 + $dia, true ),
			'solo cierre'       => array( 0, 2000, 2001, false ),
		);
	}

	/**
	 * La ventana de matrícula.
	 *
	 * @dataProvider ventana_cases
	 *
	 * @param int  $apertura Apertura.
	 * @param int  $cierre   Cierre.
	 * @param int  $ahora    Momento.
	 * @param bool $esperado Resultado.
	 */
	public function test_ventana_de_matricula( int $apertura, int $cierre, int $ahora, bool $esperado ): void {
		$this->assertSame( $esperado, Access::enrollment_window_open( $apertura, $cierre, $ahora ) );
	}

	/**
	 * Casos de caducidad del acceso.
	 *
	 * @return array<string, array{0: int, 1: int, 2: int, 3: bool}>
	 */
	public static function caducidad_cases(): array {
		$inicio = 1000000;
		$dia    = 86400;

		return array(
			'indefinido'          => array( $inicio, 0, $inicio + ( 999 * $dia ), false ),
			'sin fecha de inicio' => array( 0, 30, $inicio, false ),
			'un dia antes'        => array( $inicio, 30, $inicio + ( 29 * $dia ), false ),
			'justo al cumplir'    => array( $inicio, 30, $inicio + ( 30 * $dia ), true ),
			'mucho despues'       => array( $inicio, 30, $inicio + ( 400 * $dia ), true ),
			'dias negativos'      => array( $inicio, -5, $inicio + $dia, false ),
		);
	}

	/**
	 * La caducidad del acceso.
	 *
	 * @dataProvider caducidad_cases
	 *
	 * @param int  $inicio   Inicio del acceso.
	 * @param int  $dias     Días contratados.
	 * @param int  $ahora    Momento.
	 * @param bool $esperado Resultado.
	 */
	public function test_caducidad( int $inicio, int $dias, int $ahora, bool $esperado ): void {
		$this->assertSame( $esperado, Access::access_expired( $inicio, $dias, $ahora ) );
	}

	/**
	 * Casos del porcentaje exigido.
	 *
	 * @return array<string, array{0: string, 1: int, 2: float}>
	 */
	public static function exigido_cases(): array {
		return array(
			'todas exige el 100'     => array( 'todas', 0, 100.0 ),
			'modulo exige el 100'    => array( 'modulo', 40, 100.0 ),
			'porcentaje configurado' => array( 'porcentaje', 75, 75.0 ),
			'porcentaje por encima'  => array( 'porcentaje', 150, 100.0 ),
			'porcentaje negativo'    => array( 'porcentaje', -10, 0.0 ),
			'modo desconocido'       => array( 'loquesea', 30, 100.0 ),
		);
	}

	/**
	 * Porcentaje exigido según el modo.
	 *
	 * @dataProvider exigido_cases
	 *
	 * @param string $modo       Modo.
	 * @param int    $porcentaje Porcentaje configurado.
	 * @param float  $esperado   Porcentaje exigido.
	 */
	public function test_porcentaje_exigido( string $modo, int $porcentaje, float $esperado ): void {
		$this->assertSame( $esperado, Access::required_percent( $modo, $porcentaje ) );
	}

	/**
	 * Casos de progreso suficiente.
	 *
	 * @return array<string, array{0: string, 1: int, 2: float, 3: bool}>
	 */
	public static function progreso_cases(): array {
		return array(
			'todas con 99,9'        => array( 'todas', 0, 99.9, false ),
			'todas con 100'         => array( 'todas', 0, 100.0, true ),
			'porcentaje justo'      => array( 'porcentaje', 60, 60.0, true ),
			'porcentaje por debajo' => array( 'porcentaje', 60, 59.9, false ),
			'porcentaje cero'       => array( 'porcentaje', 0, 0.0, true ),
		);
	}

	/**
	 * El progreso abre o no el examen.
	 *
	 * @dataProvider progreso_cases
	 *
	 * @param string $modo       Modo.
	 * @param int    $porcentaje Porcentaje configurado.
	 * @param float  $real       Progreso real.
	 * @param bool   $esperado   Si debe permitir.
	 */
	public function test_progreso_permite_examen( string $modo, int $porcentaje, float $real, bool $esperado ): void {
		$this->assertSame( $esperado, Access::progress_satisfied( $modo, $porcentaje, $real ) );
	}

	/**
	 * Casos de normalización del modo de acceso.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function modo_cases(): array {
		return array(
			'minusculas'  => array( 'todas', 'todas' ),
			'mayusculas'  => array( 'TODAS', 'todas' ),
			'modulo'      => array( ' Modulo ', 'modulo' ),
			'porcentaje'  => array( 'porcentaje', 'porcentaje' ),
			'desconocido' => array( 'examen-final', 'todas' ),
			'vacio'       => array( '', 'todas' ),
		);
	}

	/**
	 * El modo de acceso se normaliza y nunca queda fuera de la lista.
	 *
	 * @dataProvider modo_cases
	 *
	 * @param string $entrada  Lo escrito.
	 * @param string $esperado Modo válido.
	 */
	public function test_normalizar_modo( string $entrada, string $esperado ): void {
		$this->assertSame( $esperado, CPT_Curso::normalize_exam_access( $entrada ) );
		$this->assertContains( $esperado, CPT_Curso::ACCESO_EXAMEN );
	}

	/**
	 * Casos de normalización de nota.
	 *
	 * @return array<string, array{0: float, 1: float}>
	 */
	public static function nota_cases(): array {
		return array(
			'negativa'        => array( -5.0, 0.0 ),
			'por encima'      => array( 150.0, 100.0 ),
			'dos decimales'   => array( 33.333, 33.33 ),
			'justo el minimo' => array( 50.0, 50.0 ),
		);
	}

	/**
	 * La nota se acota a la escala 0-100.
	 *
	 * @dataProvider nota_cases
	 *
	 * @param float $entrada  Nota escrita.
	 * @param float $esperado Nota válida.
	 */
	public function test_normalizar_nota( float $entrada, float $esperado ): void {
		$this->assertSame( $esperado, CPT_Curso::normalize_score( $entrada ) );
	}

	/**
	 * Casos de normalización de fecha.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function fecha_cases(): array {
		return array(
			'iso'      => array( '2026-10-22', '2026-10-22' ),
			'con hora' => array( '2026-10-22 18:30:00', '2026-10-22' ),
			'vacia'    => array( '', '' ),
			'espacios' => array( '   ', '' ),
			'basura'   => array( 'no es una fecha', '' ),
		);
	}

	/**
	 * La fecha se normaliza a `Y-m-d` y una fecha imposible queda vacía.
	 *
	 * @dataProvider fecha_cases
	 *
	 * @param string $entrada  Lo escrito.
	 * @param string $esperado Fecha normalizada.
	 */
	public function test_normalizar_fecha( string $entrada, string $esperado ): void {
		$this->assertSame( $esperado, CPT_Curso::normalize_date( $entrada ) );
	}
}
