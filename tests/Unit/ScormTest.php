<?php
/**
 * SCORM: manifiestos y validación del paquete.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\SCORM;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras del parser y del filtro de ZIP.
 */
final class ScormTest extends TestCase {

	/**
	 * Manifiesto SCORM 1.2.
	 */
	private function manifiesto_12(): string {
		return '<?xml version="1.0"?><manifest identifier="m1" version="1.0" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" schemaversion="1.2"><organizations><organization identifier="o1"><title>Curso de prueba</title></organization></organizations><resources><resource identifier="r1" type="webcontent" href="index.html" adlcp:scormtype="sco" /></resources></manifest>'; // convoca-hygiene-ignore: namespace oficial del esquema SCORM 1.2.
	}

	/**
	 * Manifiesto SCORM 2004.
	 */
	private function manifiesto_2004(): string {
		return '<?xml version="1.0"?><manifest identifier="m2" version="1.0" xmlns="http://www.imsglobal.org/xsd/imscp_v1p1" schemaversion="2004 4th Edition"><organizations><organization identifier="o2"><title>Curso 2004</title></organization></organizations><resources><resource identifier="r2" type="webcontent" href="contenido/index.html" /></resources></manifest>'; // convoca-hygiene-ignore: namespace oficial del esquema SCORM 2004.
	}

	/**
	 * Detecta SCORM 1.2.
	 */
	public function test_parsea_scorm_12(): void {
		$datos = SCORM::manifest_data( $this->manifiesto_12() );

		$this->assertNotNull( $datos );
		$this->assertSame( '1.2', $datos['version'] );
		$this->assertSame( 'Curso de prueba', $datos['title'] );
		$this->assertSame( 'index.html', $datos['entry'] );
	}

	/**
	 * Detecta SCORM 2004.
	 */
	public function test_parsea_scorm_2004(): void {
		$datos = SCORM::manifest_data( $this->manifiesto_2004() );

		$this->assertNotNull( $datos );
		$this->assertSame( '2004', $datos['version'] );
		$this->assertSame( 'contenido/index.html', $datos['entry'] );
	}

	/**
	 * Un XML roto no se acepta.
	 */
	public function test_un_manifest_roto_devuelve_null(): void {
		$this->assertNull( SCORM::manifest_data( '<manifest><resources>' ) );
	}

	/**
	 * Un ZIP con un fichero ejecutable se rechaza.
	 */
	public function test_rechaza_php_en_el_paquete(): void {
		$resultado = SCORM::validar_entradas( array( 'imsmanifest.xml', 'index.html', 'shell.php' ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'scorm_ejecutable', $resultado->get_error_code() );
	}

	/**
	 * Un ZIP sin manifiesto se rechaza.
	 */
	public function test_exige_imsmanifest(): void {
		$resultado = SCORM::validar_entradas( array( 'index.html', 'assets/app.js' ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'scorm_manifiesto', $resultado->get_error_code() );
	}

	/**
	 * Una ruta con `..` se rechaza.
	 */
	public function test_rechaza_rutas_con_punto_punto(): void {
		$resultado = SCORM::validar_entradas( array( 'imsmanifest.xml', '../fuera.html' ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'scorm_ruta', $resultado->get_error_code() );
	}

	/**
	 * Un paquete sano pasa.
	 */
	public function test_un_paquete_sano_pasa(): void {
		$this->assertTrue( SCORM::validar_entradas( array( 'imsmanifest.xml', 'index.html', 'assets/app.js' ) ) );
	}

	/**
	 * Instala un paquete real en el directorio de subidas de prueba.
	 */
	public function test_instala_un_paquete_real(): void {
		$lesson_id                                        = 33;
		$GLOBALS['convoca_test_post_types'][ $lesson_id ] = 'convoca_leccion';

		$zip_path = tempnam( sys_get_temp_dir(), 'scorm-test-' );
		$zip      = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'imsmanifest.xml', $this->manifiesto_12() );
		$zip->addFromString( 'index.html', '<html><body>ok</body></html>' );
		$zip->close();

		$resultado = SCORM::instalar( $zip_path, $lesson_id );
		$this->assertTrue( $resultado );
		$this->assertSame( 'index.html', SCORM::paquete( $lesson_id )['entry'] );

		$meta = SCORM::paquete( $lesson_id );
		$dir  = trailingslashit( wp_upload_dir()['basedir'] ) . $meta['dir'];
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink -- limpieza de la prueba.
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- limpieza de la prueba.
		rmdir( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- limpieza de la prueba.
		unlink( $zip_path );
		delete_post_meta( $lesson_id, SCORM::META );
	}
}
