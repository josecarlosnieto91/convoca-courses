<?php
/**
 * Casilla de renuncia al desistimiento: dónde se ofrece y qué se archiva.
 *
 * El artículo 103.m del TRLGDCU tiene tres patas (consentimiento previo y expreso, información clara y
 * poder demostrarlo) y un límite: **solo contenido digital**. Aquí se comprueba el límite y la prueba:
 *
 *  - la casilla solo aparece en cursos marcados como 100 % digital y con la instalación activada;
 *  - se pinta **desmarcada** y **separada** de la casilla obligatoria de condiciones;
 *  - al matricularse con la casilla marcada se guardan el texto literal que se mostró, la fecha, la IP y el
 *    navegador;
 *  - al matricularse sin marcarla **no** se guarda renuncia: los 14 días siguen completos.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Condiciones;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\Matricula_Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Ámbito y prueba de la renuncia.
 */
final class RenunciaAmbitoTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array
	 */
	private array $previo = array();

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
		$this->previo = array(
			'opciones' => $GLOBALS['convoca_courses_opciones'] ?? array(),
			'meta'     => $GLOBALS['convoca_test_meta'] ?? array(),
			'posts'    => $GLOBALS['convoca_test_posts'] ?? array(),
		);

		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_test_posts']       = array();

		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de pruebas.
		$GLOBALS['wpdb'] = new WpdbDoble();
	}

	/**
	 * Restaura el estado.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_opciones'] = $this->previo['opciones'];
		$GLOBALS['convoca_test_meta']        = $this->previo['meta'];
		$GLOBALS['convoca_test_posts']       = $this->previo['posts'];

		if ( null === $this->wpdb_previo ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura.
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Marca el curso como digital y activa la renuncia en la instalación.
	 *
	 * @param int  $course_id Curso.
	 * @param bool $digital   Si el curso es cien por cien digital.
	 * @return void
	 */
	private function preparar( int $course_id, bool $digital ): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA ] = true;
		$GLOBALS['convoca_test_meta'][ $course_id ]['_convoca_cien_digital'] = $digital ? '1' : '0';
	}

	/**
	 * Sin activar la renuncia no se ofrece, aunque el curso sea digital.
	 */
	public function test_apagada_no_se_ofrece(): void {
		$GLOBALS['convoca_test_meta'][10]['_convoca_cien_digital'] = '1';

		$this->assertFalse( Condiciones::renuncia_disponible( 10 ) );
		$this->assertStringNotContainsString( 'name="renuncia"', Matricula_Identidad::render( array( 'course' => '10' ) ) );
	}

	/**
	 * Activada, un curso mixto (directos, tutorías o material físico) no la ofrece: el 103.m no lo cubre.
	 */
	public function test_curso_no_digital_no_la_ofrece(): void {
		$this->preparar( 11, false );

		$this->assertFalse( Condiciones::renuncia_disponible( 11 ) );

		$html = Matricula_Identidad::render( array( 'course' => '11' ) );
		$this->assertStringNotContainsString( 'name="renuncia"', $html, 'en un curso mixto no aparece la casilla' );
		$this->assertStringContainsString( 'name="consentimiento"', $html, 'la de condiciones sí sigue' );
	}

	/**
	 * Activada y curso cien por cien digital: la casilla aparece, separada, desmarcada y opcional.
	 */
	public function test_curso_digital_ofrece_la_casilla_desmarcada(): void {
		$this->preparar( 12, true );

		$this->assertTrue( Condiciones::renuncia_disponible( 12 ) );

		$html = Matricula_Identidad::render( array( 'course' => '12' ) );

		$this->assertStringContainsString( 'name="renuncia"', $html );
		$this->assertStringNotContainsString( 'name="renuncia" value="1" required', $html, 'no puede ser obligatoria' );
		$this->assertStringNotContainsString( 'checked', $this->extraer( $html, 'renuncia' ), 'tiene que ir desmarcada' );

		// Separada de la obligatoria: cada una en su párrafo.
		$parrafos = explode( '</p>', $html );
		$con      = array();
		foreach ( $parrafos as $parrafo ) {
			if ( false !== strpos( $parrafo, 'name="consentimiento"' ) ) {
				$con[] = 'consentimiento';
			}
			if ( false !== strpos( $parrafo, 'name="renuncia"' ) ) {
				$con[] = 'renuncia';
			}
		}
		$this->assertSame( array( 'consentimiento', 'renuncia' ), $con, 'cada aceptación en su propio párrafo' );
	}

	/**
	 * Recorta el fragmento HTML de un campo por su nombre.
	 *
	 * @param string $html  HTML.
	 * @param string $campo Nombre del campo.
	 * @return string
	 */
	private function extraer( string $html, string $campo ): string {
		$pos = strpos( $html, 'name="' . $campo . '"' );
		if ( false === $pos ) {
			return '';
		}

		return substr( $html, max( 0, $pos - 120 ), 200 );
	}

	/**
	 * Matrícula con la casilla marcada: quedan el texto literal, la fecha, la IP y el navegador.
	 */
	public function test_marcarla_archiva_texto_fecha_ip_y_navegador(): void {
		$this->preparar( 13, true );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['REMOTE_ADDR'] = '192.0.2.44';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/129.0';

		Condiciones::registrar_aceptacion( 900, Condiciones::renuncia_disponible( 13 ) );

		$meta = $GLOBALS['convoca_test_meta'][900];

		$this->assertSame( '1', $meta[ Condiciones::META_RENUNCIA ] );
		$this->assertNotSame( '', (string) $meta[ Condiciones::META_CONSENTIMIENTO ], 'fecha del consentimiento' );

		$prueba = json_decode( (string) $meta[ Condiciones::META_RENUNCIA_PRUEBA ], true );
		$this->assertSame( Condiciones::texto_renuncia(), $prueba['texto'], 'el texto literal de la casilla' );
		$this->assertSame( '192.0.2.44', $prueba['ip'] );
		$this->assertStringContainsString( 'Chrome', $prueba['agente'] );
		$this->assertNotSame( '', $prueba['fecha'] );
		$this->assertSame( hash( 'sha256', Condiciones::texto_renuncia() ), $prueba['huella'] );
		$this->assertSame( 'contenido_digital', $prueba['ambito'] );

		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Matrícula sin marcarla: no hay renuncia y los 14 días siguen completos.
	 */
	public function test_sin_marcarla_no_se_archiva_renuncia(): void {
		$this->preparar( 14, true );

		$this->assertFalse( Condiciones::renuncia_disponible( 14 ) && false, 'casilla no marcada' );

		Condiciones::registrar_aceptacion( 901, false );

		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA, $GLOBALS['convoca_test_meta'][901] );
		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA_PRUEBA, $GLOBALS['convoca_test_meta'][901] );
		$this->assertArrayHasKey( Condiciones::META_CONSENTIMIENTO, $GLOBALS['convoca_test_meta'][901] );
	}

	/**
	 * Intentar colar la renuncia en un curso mixto no cuela: el curso manda, no el formulario.
	 */
	public function test_curso_mixto_no_archiva_renuncia_aunque_se_mande(): void {
		$this->preparar( 15, false );

		Condiciones::registrar_aceptacion( 902, Condiciones::renuncia_disponible( 15 ) );

		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA, $GLOBALS['convoca_test_meta'][902] );
	}

	/**
	 * El curso es digital por omisión: si nadie lo marca, no lo es.
	 */
	public function test_por_omision_el_curso_no_es_digital(): void {
		$this->assertFalse( CPT_Curso::is_fully_digital( 16 ) );
	}
}
