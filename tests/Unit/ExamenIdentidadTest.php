<?php
/**
 * Examen y certificado con identidad propia.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Attempts;
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
}
