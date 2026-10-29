<?php
/**
 * La prueba del consentimiento: aparte de la matrícula y cinco años.
 *
 * Se comprueba lo que sostiene la excepción del artículo 103.m: que la prueba sobreviva a la matrícula,
 * que el plazo se cuente desde la aceptación, que se marque cuándo salió la confirmación del artículo 98.7 y
 * que la purga borre lo vencido sin tocar lo vigente.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Prueba_Consentimiento;
use PHPUnit\Framework\TestCase;

/**
 * Retención y purga de la prueba.
 */
final class PruebaConsentimientoTest extends TestCase {

	/**
	 * Base de datos previa.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

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
	 * Cinco años desde la aceptación, no desde hoy.
	 */
	public function test_el_plazo_se_cuenta_desde_la_aceptacion(): void {
		$this->assertSame( '2031-10-29', Prueba_Consentimiento::conservar_hasta( '2026-10-29T10:15:00+00:00' ) );
		$this->assertSame( '2027-03-01', Prueba_Consentimiento::conservar_hasta( '2022-03-01 08:00:00' ) );
	}

	/**
	 * Una fecha rota no revienta el alta: se cuenta desde ahora.
	 */
	public function test_fecha_invalida_no_revienta(): void {
		$esperado = gmdate( 'Y', strtotime( '+5 years' ) );
		$obtenido = Prueba_Consentimiento::conservar_hasta( 'no es una fecha' );

		$this->assertStringStartsWith( $esperado, $obtenido );
	}

	/**
	 * La fecha ISO se guarda como la entiende MySQL.
	 */
	public function test_fecha_a_mysql(): void {
		$this->assertSame( '2026-10-29 10:15:00', Prueba_Consentimiento::a_mysql( '2026-10-29T10:15:00+00:00' ) );
	}

	/**
	 * Guardar archiva la fila con su plazo y devuelve el identificador.
	 */
	public function test_guardar_archiva_con_plazo(): void {
		$id = Prueba_Consentimiento::guardar(
			array(
				'matricula_id'         => 77,
				'curso_id'             => 12,
				'email'                => 'alguien@example.org',
				'consentimiento_texto' => 'Acepto las condiciones.',
				'prueba'               => array(
					'texto'   => 'Renuncio al desistimiento del contenido digital.',
					'huella'  => hash( 'sha256', 'Renuncio al desistimiento del contenido digital.' ),
					'fecha'   => '2026-10-29T10:15:00+00:00',
					'ip'      => '192.0.2.10',
					'agente'  => 'Mozilla/5.0',
					'ambito'  => 'contenido_digital',
					'version' => '0.16.0',
				),
			)
		);

		$this->assertGreaterThan( 0, $id );

		$filas = Prueba_Consentimiento::de_matricula( 77 );

		$this->assertCount( 1, $filas );
		$this->assertSame( 'alguien@example.org', $filas[0]['email'] );
		$this->assertSame( '192.0.2.10', $filas[0]['ip'] );
		$this->assertSame( '2031-10-29', $filas[0]['conservar_hasta'], 'cinco años desde la aceptación' );
		$this->assertSame( hash( 'sha256', 'Renuncio al desistimiento del contenido digital.' ), $filas[0]['huella'] );
	}

	/**
	 * La confirmación del artículo 98.7 deja su fecha.
	 */
	public function test_marcar_la_confirmacion(): void {
		$id = Prueba_Consentimiento::guardar(
			array(
				'matricula_id' => 78,
				'prueba'       => array( 'fecha' => '2026-10-29T10:15:00+00:00' ),
			)
		);

		Prueba_Consentimiento::marcar_confirmacion( $id );

		$filas = Prueba_Consentimiento::de_matricula( 78 );
		$this->assertNotSame( '0000-00-00 00:00:00', $filas[0]['confirmacion_enviada'] );
		$this->assertNotSame( '', (string) $filas[0]['confirmacion_enviada'] );
	}

	/**
	 * La purga borra lo vencido y respeta lo vigente.
	 */
	public function test_la_purga_borra_lo_vencido_y_respeta_lo_vigente(): void {
		global $wpdb;

		Prueba_Consentimiento::guardar(
			array(
				'matricula_id' => 90,
				'prueba'       => array( 'fecha' => '2015-01-01T00:00:00+00:00' ),
			)
		);
		Prueba_Consentimiento::guardar(
			array(
				'matricula_id' => 91,
				'prueba'       => array( 'fecha' => '2026-10-29T00:00:00+00:00' ),
			)
		);

		$this->assertSame( 1, Prueba_Consentimiento::purgar(), 'solo se va la de 2015' );
		$this->assertCount( 0, Prueba_Consentimiento::de_matricula( 90 ) );
		$this->assertCount( 1, Prueba_Consentimiento::de_matricula( 91 ) );
		$this->assertNotFalse( $wpdb );
	}
}
