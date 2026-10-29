<?php
/**
 * Renuncia al desistimiento: texto de la casilla y prueba del consentimiento.
 *
 * El artículo 103.m del TRLGDCU permite perder los 14 días de desistimiento en contenido digital, pero
 * exige tres cosas a la vez: consentimiento previo y expreso, información clara de que se pierde el derecho
 * al empezar, y **poder demostrarlo**. Lo primero y lo segundo son el texto; lo tercero es lo que se prueba
 * aquí: que quede archivado qué leyó la persona, cuándo, desde dónde y con qué navegador.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Condiciones;
use PHPUnit\Framework\TestCase;

/**
 * Texto y prueba de la renuncia.
 */
final class CondicionesPruebaTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'opciones' => $GLOBALS['convoca_courses_opciones'] ?? array(),
			'meta'     => $GLOBALS['convoca_test_meta'] ?? array(),
			'server'   => $_SERVER,
		);

		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_courses_correos']  = array();

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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- se restaura el entorno de la prueba.
		$_SERVER = $this->previo['server'];

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
	 * El texto de fábrica dice las tres cosas que exige la ley.
	 */
	public function test_el_texto_de_fabrica_cumple_los_tres_requisitos(): void {
		$texto = Condiciones::texto_renuncia();

		$this->assertStringContainsString( 'pierdo el derecho de desistimiento', $texto, 'tiene que decir que se pierde' );
		$this->assertStringContainsString( 'contenido digital', $texto, 'tiene que acotar el alcance' );
		$this->assertStringContainsString( 'si no la marco', $texto, 'tiene que ofrecer la alternativa' );
		$this->assertStringContainsString( '14 días', $texto, 'tiene que nombrar el plazo' );
	}

	/**
	 * La entidad puede poner su propio texto y manda sobre el de fábrica.
	 */
	public function test_el_texto_propio_manda(): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA_TEXTO ] = 'Texto propio de la entidad.';

		$this->assertSame( 'Texto propio de la entidad.', Condiciones::texto_renuncia() );
	}

	/**
	 * Un texto en blanco no anula la casilla: se vuelve al de fábrica.
	 */
	public function test_texto_propio_en_blanco_usa_el_de_fabrica(): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA_TEXTO ] = '   ';

		$this->assertSame( Condiciones::texto_renuncia(), Condiciones::texto_renuncia() );
		$this->assertStringContainsString( 'pierdo el derecho', Condiciones::texto_renuncia() );
	}

	/**
	 * La prueba guarda el texto, su huella, la fecha, la IP y el navegador.
	 */
	public function test_la_prueba_guarda_lo_que_hace_falta(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/128.0';

		$prueba = Condiciones::prueba( 'Acepto y renuncio.' );

		$this->assertSame( 'Acepto y renuncio.', $prueba['texto'] );
		$this->assertSame( hash( 'sha256', 'Acepto y renuncio.' ), $prueba['huella'] );
		$this->assertSame( '203.0.113.7', $prueba['ip'] );
		$this->assertStringContainsString( 'Firefox', $prueba['agente'] );
		$this->assertSame( 'contenido_digital', $prueba['ambito'] );
		$this->assertNotSame( '', $prueba['fecha'] );
	}

	/**
	 * Sin entorno de servidor no revienta: la prueba queda con campos vacíos, no con un fatal.
	 */
	public function test_sin_entorno_de_servidor(): void {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] );

		$prueba = Condiciones::prueba( 'Texto.' );

		$this->assertSame( '', $prueba['ip'] );
		$this->assertSame( '', $prueba['agente'] );
	}

	/**
	 * Un navegador larguísimo se recorta: no se guardan 4 KB de user-agent en cada matrícula.
	 */
	public function test_el_navegador_se_recorta(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['HTTP_USER_AGENT'] = str_repeat( 'A', 600 );

		$prueba = Condiciones::prueba( 'Texto.' );

		$this->assertSame( 255, mb_strlen( $prueba['agente'] ) );
	}

	/**
	 * Con la renuncia apagada, la matrícula guarda el consentimiento y nada más.
	 */
	public function test_renuncia_apagada_solo_guarda_el_consentimiento(): void {
		Condiciones::registrar_aceptacion( 500, true );

		$this->assertArrayHasKey( Condiciones::META_CONSENTIMIENTO, $GLOBALS['convoca_test_meta'][500] );
		$this->assertArrayHasKey( Condiciones::META_CONSENTIMIENTO_TEXTO, $GLOBALS['convoca_test_meta'][500] );
		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA, $GLOBALS['convoca_test_meta'][500] );
		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA_PRUEBA, $GLOBALS['convoca_test_meta'][500] );
	}

	/**
	 * Con la renuncia activa y la casilla marcada, quedan las cuatro cosas y la prueba es JSON con el texto.
	 */
	public function test_renuncia_activa_guarda_la_prueba(): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA ] = true;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- entorno de la prueba.
		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

		Condiciones::registrar_aceptacion( 501, true );

		$meta = $GLOBALS['convoca_test_meta'][501];
		$this->assertSame( '1', $meta[ Condiciones::META_RENUNCIA ] );

		$prueba = json_decode( (string) $meta[ Condiciones::META_RENUNCIA_PRUEBA ], true );
		$this->assertIsArray( $prueba );
		$this->assertSame( '198.51.100.9', $prueba['ip'] );
		$this->assertSame( Condiciones::texto_renuncia(), $prueba['texto'], 'se guarda el texto exacto que se mostró' );
	}

	/**
	 * Con la renuncia activa pero sin marcar la casilla, no se archiva renuncia: es la prueba de que fue libre.
	 */
	public function test_renuncia_activa_sin_marcar_no_archiva_renuncia(): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA ] = true;

		Condiciones::registrar_aceptacion( 502, false );

		$this->assertArrayHasKey( Condiciones::META_CONSENTIMIENTO, $GLOBALS['convoca_test_meta'][502] );
		$this->assertArrayNotHasKey( Condiciones::META_RENUNCIA, $GLOBALS['convoca_test_meta'][502] );
	}

	/**
	 * La aceptación se archiva aparte, en su tabla, con el correo y su plazo.
	 */
	public function test_la_aceptacion_se_archiva_aparte(): void {
		$GLOBALS['convoca_courses_opciones'][ Condiciones::OPTION_RENUNCIA ] = true;

		$id = Condiciones::registrar_aceptacion( 903, true, 55, 'quien@example.org' );

		$this->assertGreaterThan( 0, $id );

		$filas = \Convoca\Courses\Prueba_Consentimiento::de_matricula( 903 );

		$this->assertCount( 1, $filas );
		$this->assertSame( 'quien@example.org', $filas[0]['email'] );
		$this->assertSame( 55, (int) $filas[0]['curso_id'] );
		$this->assertNotSame( '0000-00-00', $filas[0]['conservar_hasta'] );
	}

	/**
	 * La confirmación del 98.7 lleva el texto literal que se aceptó y el nombre del curso.
	 */
	public function test_la_confirmacion_lleva_el_texto_literal(): void {
		$GLOBALS['convoca_test_meta'][600]['_convoca_precio'] = '5';
		$GLOBALS['convoca_courses_correos']                   = array();

		$ok = Condiciones::confirmar( 904, 600, 'quien@example.org', 'Ana', true, 1 );

		$this->assertTrue( $ok );
		$this->assertCount( 1, $GLOBALS['convoca_courses_correos'] );

		$correo = $GLOBALS['convoca_courses_correos'][0];

		$this->assertSame( 'quien@example.org', $correo['para'] );
		$this->assertStringContainsString( Condiciones::texto_renuncia(), $correo['mensaje'], 'el texto literal' );
		$this->assertStringContainsString( 'Ana', $correo['mensaje'] );
		$this->assertStringContainsString( 'art. 103.m', $correo['mensaje'] );
	}

	/**
	 * Sin renuncia, la confirmación dice que conserva los 14 días y no reproduce renuncia ninguna.
	 */
	public function test_sin_renuncia_la_confirmacion_deja_los_14_dias(): void {
		$GLOBALS['convoca_courses_correos'] = array();

		Condiciones::confirmar( 905, 600, 'quien@example.org', 'Ana', false, 0 );

		$mensaje = $GLOBALS['convoca_courses_correos'][0]['mensaje'];

		$this->assertStringContainsString( '14 días naturales', $mensaje );
		$this->assertStringNotContainsString( Condiciones::texto_renuncia(), $mensaje );
	}

	/**
	 * Sin correo no se manda nada y se dice que no.
	 */
	public function test_sin_correo_no_manda_nada(): void {
		$GLOBALS['convoca_courses_correos'] = array();

		$this->assertFalse( Condiciones::confirmar( 906, 600, '', 'Ana', true, 0 ) );
		$this->assertSame( array(), $GLOBALS['convoca_courses_correos'] );
	}

	/**
	 * Sin matrícula no se escribe nada.
	 */
	public function test_sin_matricula_no_escribe(): void {
		Condiciones::registrar_aceptacion( 0, true );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}
}
