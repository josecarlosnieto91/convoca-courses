<?php
/**
 * Matrícula con identidad propia: sin cuenta de WordPress.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Enrollment;
use Convoca\Courses\Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Alta por identidad.
 */
final class MatriculaIdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_post_types']  = array();

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
	 * Un correo inválido no matricula a nadie.
	 */
	public function test_correo_invalido_no_matricula(): void {
		$resultado = Enrollment::enroll_por_identidad( 'no-es-correo', 'Ana', 10 );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
	}

	/**
	 * La identidad guarda el nombre.
	 */
	public function test_la_identidad_guarda_el_nombre(): void {
		Identidad::alta( 'ana@ejemplo.test', 'Ana Pérez' );

		$fila = Identidad::por_email( 'ana@ejemplo.test' );

		$this->assertSame( 'Ana Pérez', $fila['nombre'] );
	}

	/**
	 * La matrícula guarda el vínculo con la identidad.
	 */
	public function test_la_matricula_guarda_el_vinculo(): void {
		$this->assertSame( '_convoca_identidad_id', Identidad::META_MATRICULA );
	}

	/**
	 * Una identidad sin matrícula no encuentra nada.
	 */
	public function test_sin_matricula_no_hay_nada(): void {
		$id = Identidad::alta( 'bea@ejemplo.test', 'Bea' );

		$this->assertSame( 0, Enrollment::find_por_identidad( $id, 10 ) );
		$this->assertSame( 0, Enrollment::find_por_identidad( 0, 10 ) );
	}

	/**
	 * Marcar verificada no revienta con un id vacío.
	 */
	public function test_marcar_verificada_con_id_vacio(): void {
		Identidad::marcar_verificada( 0 );

		$this->assertNull( Identidad::por_id( 0 ) );
	}

	/**
	 * Marcar verificada deja la fecha.
	 */
	public function test_marcar_verificada(): void {
		$id = Identidad::alta( 'carla@ejemplo.test', 'Carla' );

		Identidad::marcar_verificada( $id );

		$fila = Identidad::por_id( $id );

		$this->assertNotEmpty( $fila['verificado'] );
		$this->assertNotSame( '0000-00-00 00:00:00', $fila['verificado'] );
	}
}
