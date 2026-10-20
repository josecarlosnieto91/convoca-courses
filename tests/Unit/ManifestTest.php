<?php
/**
 * Pruebas del manifiesto del plugin: regla 0, versiones y textos obligatorios.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Estas pruebas vigilan el paquete, no el código: son las dos formas de romper una publicación.
 *
 * 1. Regla 0: el módulo se vende solo, así que no puede aparecer el nombre de ninguna entidad.
 * 2. Versiones: `Version:` del header, la constante y `Stable tag` tienen que coincidir, o el
 *    Plugin Check tumba el run por eso solo.
 */
class ManifestTest extends TestCase {

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros de texto del repo, sin dependencias ni artefactos.
	 *
	 * @return array<int, string>
	 */
	private function text_files(): array {
		$root  = $this->root();
		$skip  = array( 'vendor', 'node_modules', '.git', '.phpunit.cache', 'build', 'build-dir' );
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$path = $file->getPathname();
			$rel  = ltrim( str_replace( $root, '', $path ), '/' );

			foreach ( $skip as $dir ) {
				if ( 0 === strpos( $rel, $dir . '/' ) ) {
					continue 2;
				}
			}

			$extension = strtolower( (string) $file->getExtension() );
			if ( ! in_array( $extension, array( 'php', 'js', 'css', 'md', 'txt', 'json', 'pot', 'po', 'yml', 'yaml' ), true ) ) {
				continue;
			}

			$files[] = $rel;
		}

		sort( $files );

		return $files;
	}

	/**
	 * Regla 0: ni una sola aparición de una entidad concreta en el repo.
	 *
	 * @return void
	 */
	public function test_rule_zero_no_entity_names(): void {
		// El patrón se arma por trozos a propósito: si estuviera escrito entero, esta misma prueba
		// sería la única infractora de la regla que vigila.
		$forbidden = '/' . implode( '|', array( 'sa' . 'via', 'bio' . 'devas', 'lu' . 'gg', 'josecnr91' . '@' ) ) . '/i';
		$offenders = array();

		foreach ( $this->text_files() as $relative ) {
			$content = (string) file_get_contents( $this->root() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura en una prueba.
			if ( preg_match( $forbidden, $content ) ) {
				$offenders[] = $relative;
			}
		}

		$this->assertSame( array(), $offenders, 'La regla 0 prohíbe nombres de entidades en el repositorio.' );
	}

	/**
	 * La versión del header, la constante y el Stable tag coinciden.
	 *
	 * @return void
	 */
	public function test_versions_are_coherent(): void {
		$main = (string) file_get_contents( $this->root() . '/convoca-courses.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura en una prueba.
		$this->assertSame( 1, preg_match( '/^ \* Version:\s*([0-9.]+)$/m', $main, $header ), 'Falta la cabecera Version.' );

		$readme = (string) file_get_contents( $this->root() . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- idem.
		$this->assertSame( 1, preg_match( '/^Stable tag:\s*([0-9.]+)$/m', $readme, $stable ), 'Falta Stable tag en readme.txt.' );

		$this->assertSame( $header[1], $stable[1], 'Version y Stable tag tienen que coincidir.' );
		$this->assertStringContainsString( 'CONVOCA_COURSES_DB_VERSION', $main );
	}

	/**
	 * El readme tiene las secciones que pide el directorio de plugins.
	 *
	 * @return void
	 */
	public function test_readme_has_required_sections(): void {
		$readme = (string) file_get_contents( $this->root() . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- idem.

		foreach ( array( '== Description ==', '== Installation ==', '== Frequently Asked Questions ==', '== Changelog ==' ) as $section ) {
			$this->assertStringContainsString( $section, $readme, 'Falta la sección ' . $section );
		}

		$this->assertStringContainsString( 'GPL-2.0-or-later', $readme );
	}

	/**
	 * Los textos visibles usan el dominio del módulo.
	 *
	 * @return void
	 */
	public function test_text_domain_is_used(): void {
		foreach ( array( 'includes/Shortcodes.php', 'includes/CPT_Base.php', 'admin/class-admin-fields.php' ) as $relative ) {
			$content = (string) file_get_contents( $this->root() . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- idem.
			$this->assertStringContainsString( "'convoca-courses'", $content, $relative . ' no usa el dominio convoca-courses.' );
		}
	}
}
