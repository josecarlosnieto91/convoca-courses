<?php
/**
 * Identidad propia del alumnado: correo, cookie y sesión.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Identidad;
use PHPUnit\Framework\TestCase;

/**
 * Sin usuario de WordPress.
 */
final class IdentidadTest extends TestCase {

	/**
	 * Arnés limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		unset( $_COOKIE[ Identidad::COOKIE ] );

		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de pruebas.
		$GLOBALS['wpdb'] = new \Convoca\Courses\Tests\WpdbDoble();
	}

	/**
	 * Restaura la base de datos anterior.
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
	 * Base de datos previa, para restaurarla.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * El correo se normaliza a minúsculas y sin espacios.
	 */
	public function test_normaliza_el_correo(): void {
		$this->assertSame( 'ana@ejemplo.test', Identidad::normalizar_email( '  ANA@Ejemplo.test ' ) );
	}

	/**
	 * Un correo inválido no pasa.
	 */
	public function test_correo_invalido(): void {
		$this->assertSame( '', Identidad::normalizar_email( 'no-es-un-correo' ) );
		$this->assertSame( '', Identidad::normalizar_email( '' ) );
	}

	/**
	 * Sin cookie no hay sesión.
	 */
	public function test_sin_cookie_no_hay_sesion(): void {
		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * Una cookie bien firmada devuelve la identidad.
	 */
	public function test_cookie_valida_devuelve_la_identidad(): void {
		$expira                       = time() + 3600;
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 7, Identidad::actual() );
	}

	/**
	 * Una cookie manipulada no vale.
	 */
	public function test_cookie_manipulada_no_vale(): void {
		$expira                       = time() + 3600;
		$_COOKIE[ Identidad::COOKIE ] = '8|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * Una cookie caducada no vale.
	 */
	public function test_cookie_caducada_no_vale(): void {
		$expira                       = time() - 10;
		$_COOKIE[ Identidad::COOKIE ] = '7|' . $expira . '|' . Identidad::firma_cookie( 7, $expira );

		$this->assertSame( 0, Identidad::actual() );
	}

	/**
	 * Alta: el correo queda normalizado y no se duplica.
	 */
	public function test_alta_y_no_duplica(): void {
		$tabla = 'wp_' . \Convoca\Courses\Installer::IDENTIDADES_TABLE;
		unset( $GLOBALS['wpdb']->filas[ $tabla ] );

		$id = Identidad::alta( '  Ana@Ejemplo.test ', 'Ana' );
		$this->assertGreaterThan( 0, $id );

		$otra = Identidad::alta( 'ana@ejemplo.test', 'Ana otra vez' );
		$this->assertSame( $id, $otra );

		$fila = Identidad::por_email( 'ANA@ejemplo.test' );
		$this->assertNotNull( $fila );
		$this->assertSame( 'ana@ejemplo.test', $fila['email'] );
	}

	/**
	 * Un correo inválido no crea identidad.
	 */
	public function test_alta_rechaza_correo_invalido(): void {
		$this->assertSame( 0, Identidad::alta( 'no-es-correo' ) );
	}

	/**
	 * Se emite enlace y código, y ambos se validan.
	 */
	public function test_emitir_y_validar(): void {
		$tabla = 'wp_' . \Convoca\Courses\Installer::IDENTIDADES_TABLE;
		unset( $GLOBALS['wpdb']->filas[ $tabla ] );
		$id = Identidad::alta( 'bea@ejemplo.test' );

		$par = Identidad::emitir( $id );

		$this->assertSame( 64, strlen( $par['token'] ) );
		$this->assertSame( 6, strlen( $par['codigo'] ) );
		$this->assertTrue( Identidad::token_valido( $id, $par['token'] ) );
		$this->assertTrue( Identidad::codigo_valido( $id, $par['codigo'] ) );
		$this->assertFalse( Identidad::token_valido( $id, 'otro-token' ) );
		$this->assertFalse( Identidad::codigo_valido( $id, '000001' ) );
	}

	/**
	 * Un acceso solo sirve una vez.
	 */
	public function test_el_acceso_se_gasta(): void {
		$tabla = 'wp_' . \Convoca\Courses\Installer::IDENTIDADES_TABLE;
		unset( $GLOBALS['wpdb']->filas[ $tabla ] );
		$id  = Identidad::alta( 'carla@ejemplo.test' );
		$par = Identidad::emitir( $id );

		Identidad::consumir( $id );

		$this->assertFalse( Identidad::token_valido( $id, $par['token'] ) );
		$this->assertFalse( Identidad::codigo_valido( $id, $par['codigo'] ) );
	}

	/**
	 * Sin sesión, el shortcode pinta el formulario con el campo de correo y el del código.
	 */
	public function test_el_shortcode_pinta_el_formulario(): void {
		$html = \Convoca\Courses\Identidad_Acceso::render();

		$this->assertStringContainsString( 'convoca-acceso', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="codigo"', $html );
	}

	/**
	 * Con sesión, saluda por el correo.
	 */
	public function test_con_sesion_saluda(): void {
		$id                           = Identidad::alta( 'dora@ejemplo.test' );
		$exp                          = time() + 3600;
		$_COOKIE[ Identidad::COOKIE ] = $id . '|' . $exp . '|' . Identidad::firma_cookie( $id, $exp );

		$html = \Convoca\Courses\Identidad_Acceso::render();

		$this->assertStringContainsString( 'dora@ejemplo.test', $html );
		$this->assertStringNotContainsString( 'name="codigo"', $html );
	}

	/**
	 * Consultas con datos vacíos no revientan.
	 */
	public function test_consultas_vacias(): void {
		$this->assertNull( Identidad::por_id( 0 ) );
		$this->assertNull( Identidad::por_email( '' ) );
		$this->assertNull( Identidad::por_id( 9999 ) );
	}

	/**
	 * Sin acceso emitido, ni token ni código valen.
	 */
	public function test_sin_emitir_no_vale_nada(): void {
		$id = Identidad::alta( 'eva@ejemplo.test' );

		$this->assertFalse( Identidad::token_valido( $id, 'lo-que-sea' ) );
		$this->assertFalse( Identidad::codigo_valido( $id, '123456' ) );
	}

	/**
	 * Tras enviar el correo se avisa en la pantalla.
	 */
	public function test_aviso_de_enviado(): void {
		$_GET['convoca_enviado'] = '1';

		$html = \Convoca\Courses\Identidad_Acceso::render();

		unset( $_GET['convoca_enviado'] );

		$this->assertStringContainsString( 'Revisa el correo', $html );
	}

	/**
	 * La firma cambia con la identidad y con la caducidad.
	 */
	public function test_la_firma_depende_de_identidad_y_caducidad(): void {
		$expira = time() + 3600;

		$this->assertNotSame( Identidad::firma_cookie( 1, $expira ), Identidad::firma_cookie( 2, $expira ) );
		$this->assertNotSame( Identidad::firma_cookie( 1, $expira ), Identidad::firma_cookie( 1, $expira + 1 ) );
	}
}
