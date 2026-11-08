<?php
/**
 * Las fechas del sitio se leen en la zona del sitio (issue #47).
 *
 * `current_time( 'timestamp' )` devolvía la hora local como si fuera UTC y `strtotime()` interpretaba en
 * UTC: en Madrid, una ventana que cierra el 31 a las 23:59 se cerraba dos horas antes de tiempo, y en
 * invierno una hora antes. Lo que se fija aquí es la conversión, con zona y con hora.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Fechas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Fechas.php';

/**
 * Conversión de fechas del sitio.
 */
final class FechasTest extends TestCase {

	/**
	 * Estado limpio: sin filtros ni zona fijada.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_filtros'] = array();
		unset( $GLOBALS['convoca_courses_tz'] );
	}

	/**
	 * Una fecha sin hora se lee a medianoche del sitio.
	 */
	public function test_una_fecha_sin_hora_es_medianoche(): void {
		$this->assertSame( strtotime( '2026-12-31 00:00:00 UTC' ), Fechas::a_timestamp( '2026-12-31' ) );
	}

	/**
	 * El cierre llega al último segundo del día.
	 */
	public function test_la_hora_por_defecto_del_cierre_es_la_ultima_del_dia(): void {
		$this->assertSame( strtotime( '2026-12-31 23:59:59 UTC' ), Fechas::a_timestamp( '2026-12-31', '23:59:59' ) );
	}

	/**
	 * La fecha se lee en la zona del sitio, no en UTC.
	 */
	public function test_se_lee_en_la_zona_del_sitio_no_en_utc(): void {
		$GLOBALS['convoca_courses_tz'] = 'Europe/Madrid';

		// 1 de julio a las 00:00 en Madrid (UTC+2) son las 22:00 del 30 de junio en UTC.
		$this->assertSame(
			strtotime( '2026-06-30 22:00:00 UTC' ),
			Fechas::a_timestamp( '2026-07-01' ),
			'La medianoche del sitio no es la medianoche UTC.'
		);
	}

	/**
	 * En invierno la zona es la de invierno (UTC+1).
	 */
	public function test_en_invierno_la_zona_es_la_de_invierno(): void {
		$GLOBALS['convoca_courses_tz'] = 'Europe/Madrid';

		// 1 de enero a las 00:00 en Madrid (UTC+1) son las 23:00 del 31 de diciembre en UTC.
		$this->assertSame(
			strtotime( '2025-12-31 23:00:00 UTC' ),
			Fechas::a_timestamp( '2026-01-01' )
		);
	}

	/**
	 * Si la fecha trae hora, no se le añade otra.
	 */
	public function test_una_fecha_que_ya_trae_hora_no_se_le_añade_otra(): void {
		$this->assertSame(
			strtotime( '2026-12-31 18:30:00 UTC' ),
			Fechas::a_timestamp( '2026-12-31 18:30:00', '23:59:59' )
		);
	}

	/**
	 * Una fecha vacía o rota devuelve cero.
	 */
	public function test_una_fecha_vacia_o_rota_devuelve_cero(): void {
		$this->assertSame( 0, Fechas::a_timestamp( '' ) );
		$this->assertSame( 0, Fechas::a_timestamp( 'no-es-una-fecha' ) );
	}

	/**
	 * El reloj se puede fijar desde una prueba.
	 */
	public function test_ahora_se_puede_fijar_en_las_pruebas(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_ahora'] = 1_761_220_800;

		$this->assertSame( 1_761_220_800, Fechas::ahora() );
	}

	/**
	 * Sin filtro, «ahora» es el reloj del sistema.
	 */
	public function test_ahora_sin_filtro_es_el_reloj_utc(): void {
		$this->assertEqualsWithDelta( time(), Fechas::ahora(), 5, 'Sin filtro, el reloj del sistema.' );
	}
}
