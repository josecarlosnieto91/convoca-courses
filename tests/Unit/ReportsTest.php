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
	 * Sin la librería de PDF devuelve vacío.
	 */
	public function test_pdf_sin_libreria(): void {
		$this->assertSame(
			'',
			Reports::pdf(
				array(
					'course_id'    => 5,
					'user_id'      => 1,
					'identidad_id' => 0,
				)
			)
		);
	}

	/**
	 * CSV con una fila completa.
	 */
	public function test_csv_con_una_fila(): void {
		$csv = Reports::csv(
			array(
				array(
					'nombre'     => 'Alumno de prueba',
					'correo'     => 'alumno@example.test',
					'estado'     => 'activa',
					'progreso'   => 100.0,
					'notas'      => array(
						'nota'     => 8.0,
						'aprobado' => true,
					),
					'conexiones' => array(
						'total'    => 2,
						'ultima'   => '2026-11-12 00:00:00',
						'duracion' => 60,
						'eventos'  => array(),
					),
					'scorm'      => array(
						array(
							'leccion' => 'Lección 1',
							'estado'  => 'completed',
							'tiempo'  => '00:10:00',
						),
					),
					'encuesta'   => array(
						'answers'    => array( 5, 4 ),
						'comentario' => 'Bien',
					),
				),
			)
		);

		$this->assertStringContainsString( 'Alumno de prueba', $csv );
		$this->assertStringContainsString( 'Lección 1: completed', $csv );
		$this->assertStringContainsString( '4.5', $csv );
	}
}
