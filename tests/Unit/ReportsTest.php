<?php
/**
 * Informes: duraciones y CSV.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Reports;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras del informe.
 */
final class ReportsTest extends TestCase {

	/**
	 * Convierte duraciones SCORM.
	 */
	public function test_convierte_duraciones(): void {
		$this->assertSame( 3661, Reports::segundos( 'PT1H1M1S' ) );
		$this->assertSame( 3661, Reports::segundos( '01:01:01' ) );
		$this->assertSame( 0, Reports::segundos( '' ) );
		$this->assertSame( '1 h 1 min', Reports::duracion( 3661 ) );
	}

	/**
	 * El CSV lleva la cabecera del informe.
	 */
	public function test_csv_incluye_cabecera(): void {
		$csv = Reports::csv( array() );

		$this->assertStringContainsString( 'Alumno', $csv );
		$this->assertStringContainsString( 'Conexiones', $csv );
		$this->assertStringContainsString( 'Encuesta', $csv );
	}
}
