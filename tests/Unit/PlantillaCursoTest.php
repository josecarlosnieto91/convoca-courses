<?php
/**
 * La portada del curso tiene que recibir todas las variables que su plantilla documenta.
 *
 * El refactor que partió `course()` en `render_course()` dejó `$receipt_url` calculada y fuera del array:
 * un aviso de PHP por cada visita de cada persona matriculada y el enlace al recibo sin pintarse nunca.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contrato entre plantilla y shortcode.
 */
final class PlantillaCursoTest extends TestCase {

	/**
	 * Directorio raíz del plugin.
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Variables que la plantilla documenta en su `@var`.
	 *
	 * @param string $plantilla Nombre del fichero.
	 * @return array<int, string>
	 */
	private function documentadas( string $plantilla ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plantilla del propio plugin.
		$fuente = (string) file_get_contents( $this->raiz() . '/templates/' . $plantilla );

		if ( ! preg_match( '/@var array<string, mixed>(.*?)\.\s*\*/s', $fuente, $bloque ) ) {
			return array();
		}

		preg_match_all( '/\$([a-z_]+)/', $bloque[1], $coincidencias );

		return array_values( array_unique( $coincidencias[1] ) );
	}

	/**
	 * Claves que `render_course()` entrega a la plantilla.
	 *
	 * @param string $plantilla Nombre del fichero.
	 * @return array<int, string>
	 */
	private function entregadas( string $plantilla ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- código del propio plugin.
		$fuente = (string) file_get_contents( $this->raiz() . '/includes/Shortcodes.php' );

		$inicio = strpos( $fuente, "'" . $plantilla . "'" );
		if ( false === $inicio ) {
			return array();
		}

		$fin = strpos( $fuente, 'false', $inicio );
		if ( false === $fin ) {
			return array();
		}

		preg_match_all( "/'([a-z_]+)'\s*=>/", substr( $fuente, $inicio, $fin - $inicio ), $coincidencias );

		return array_values( array_unique( $coincidencias[1] ) );
	}

	/**
	 * Lo que la plantilla documenta es lo que el shortcode tiene que entregar.
	 */
	public function test_la_portada_recibe_todas_las_variables_que_documenta(): void {
		$documentadas = $this->documentadas( 'curso.php' );
		$entregadas   = $this->entregadas( 'curso.php' );

		$this->assertNotEmpty( $documentadas, 'la plantilla tiene que documentar sus variables' );
		$this->assertNotEmpty( $entregadas, 'el shortcode tiene que pasar variables' );

		$faltan = array_diff( $documentadas, $entregadas );

		$this->assertSame(
			array(),
			array_values( $faltan ),
			'la plantilla usa y no recibe: ' . implode( ', ', $faltan )
		);
	}

	/**
	 * Con recibo, la portada pinta el enlace.
	 */
	public function test_la_portada_pinta_el_recibo_cuando_hay(): void {
		$html = $this->renderizar( 'https://ejemplo.test/recibo/12' );

		$this->assertStringContainsString( 'recibo', strtolower( $html ), 'el enlace al recibo tiene que aparecer' );
	}

	/**
	 * Sin recibo, no se pinta y no aparece ninguna variable sin definir.
	 */
	public function test_sin_recibo_no_pinta_el_enlace(): void {
		$html = $this->renderizar( '' );

		$this->assertStringNotContainsString( 'recibo del pago', strtolower( $html ) );
	}

	/**
	 * Renderiza la plantilla con el juego completo de variables documentadas.
	 *
	 * @param string $recibo URL del recibo.
	 * @return string
	 */
	private function renderizar( string $recibo ): string {
		$datos = array(
			'course_id'         => 12,
			'title'             => 'Curso de prueba',
			'content'           => '',
			'price'             => '',
			'price_reason'      => '',
			'seats'             => 3,
			'enrolled'          => true,
			'progress'          => array(
				'percent' => 50,
				'done'    => 1,
				'total'   => 2,
			),
			'certificate'       => '',
			'modules'           => array(),
			'logged_in'         => true,
			'enroll_url'        => '',
			'enroll_nonce'      => '',
			'needs_payment'     => false,
			'enrollment_status' => 'completada',
			'payment_url'       => '',
			'receipt_url'       => $recibo,
		);

		$avisos = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- en un test: captura los avisos de variable indefinida para poder afirmar que no hay.
		set_error_handler(
			static function ( $errno, $mensaje ) use ( &$avisos ) {
				$avisos[] = (string) $mensaje;
				return true;
			}
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- reproducción exacta de lo que hace el motor de plantillas.
		extract( $datos );
		include $this->raiz() . '/templates/curso.php';
		$html = (string) ob_get_clean();

		restore_error_handler();

		$this->assertSame( array(), $avisos, 'la plantilla no puede usar variables que no recibe' );

		return $html;
	}
}
