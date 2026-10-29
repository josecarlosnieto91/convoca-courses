<?php
/**
 * El certificado sobrevive a la matrícula: los certificados no se revocan.
 *
 * Antes, el código del certificado vivía solo en el `postmeta` de la matrícula. Si la persona se daba de
 * baja —o se borraba su matrícula por supresión de datos— la página de verificación dejaba de reconocer un
 * certificado auténtico. Ahora hay un registro propio y la validación no depende de nada más.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Certificate;
use PHPUnit\Framework\TestCase;

/**
 * Registro de certificados.
 */
final class CertificadoRegistroTest extends TestCase {

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
			'meta'  => $GLOBALS['convoca_test_meta'] ?? array(),
			'posts' => $GLOBALS['convoca_test_posts'] ?? array(),
		);

		$GLOBALS['convoca_test_meta']  = array();
		$GLOBALS['convoca_test_posts'] = array();

		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de pruebas.
		$GLOBALS['wpdb'] = new WpdbDoble();
	}

	/**
	 * Restaura el estado.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_meta']  = $this->previo['meta'];
		$GLOBALS['convoca_test_posts'] = $this->previo['posts'];

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
	 * Matrícula de prueba con su certificado congelado.
	 *
	 * @param int $id Matrícula.
	 * @return void
	 */
	private function matricula( int $id ): void {
		$GLOBALS['convoca_test_posts'][ $id ] = array(
			'ID'          => $id,
			'post_type'   => 'convoca_matricula',
			'post_status' => 'publish',
			'post_title'  => 'Matrícula',
		);

		$GLOBALS['convoca_test_meta'][ $id ]['_convoca_curso_id']  = '600';
		$GLOBALS['convoca_test_meta'][ $id ]['_convoca_cert_nota'] = '8.5';
	}

	/**
	 * Se registra y se recupera.
	 */
	public function test_registrar_y_recuperar(): void {
		$this->matricula( 700 );

		Certificate::registrar( 700, 'CC600700ABCDEF' );

		$fila = Certificate::registro( 'cc600700-abcdef' );

		$this->assertNotNull( $fila, 'la normalización quita guiones y pasa a mayúsculas' );
		$this->assertSame( 'CC600700ABCDEF', $fila['codigo'] );
		$this->assertSame( 600, (int) $fila['curso_id'] );
		$this->assertSame( 8.5, (float) $fila['nota'] );
	}

	/**
	 * Registrar dos veces no duplica: el código es único.
	 */
	public function test_no_duplica(): void {
		$this->matricula( 701 );

		Certificate::registrar( 701, 'CC600701ZZZZ' );
		Certificate::registrar( 701, 'CC600701ZZZZ' );

		global $wpdb;
		$this->assertCount( 1, $wpdb->filas['wp_convoca_certificados'] ?? array() );
	}

	/**
	 * Borrada la matrícula, el certificado sigue verificándose.
	 */
	public function test_el_certificado_sobrevive_a_la_matricula(): void {
		$GLOBALS['convoca_test_posts'][600] = array(
			'ID'         => 600,
			'post_type'  => 'convoca_curso',
			'post_title' => 'Respetando la fauna silvestre',
		);

		$this->matricula( 702 );
		Certificate::registrar( 702, 'CC600702MUERE' );

		// Se borra la matrícula entera: posts y metas.
		unset( $GLOBALS['convoca_test_posts'][702], $GLOBALS['convoca_test_meta'][702] );

		$datos = Certificate::data( 'CC600702MUERE' );

		$this->assertNotNull( $datos, 'un certificado no se revoca al dar de baja a la persona' );
		$this->assertSame( 'CC600702MUERE', $datos['code'] );
		$this->assertSame( 'Título 600', $datos['course'], 'el título quedó congelado al emitir' );
		$this->assertSame( '8,50', $datos['nota'] ?? '' );
	}

	/**
	 * Un código que no existe no devuelve nada.
	 */
	public function test_codigo_desconocido(): void {
		$this->assertNull( Certificate::data( 'CC000000NOEXISTE' ) );
		$this->assertNull( Certificate::registro( 'CC000000NOEXISTE' ) );
	}
}
