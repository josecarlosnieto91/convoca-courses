<?php
/**
 * Verificación pública de certificados: /verificar-curso/?codigo=...
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página pública de verificación.
 */
class Verification {

	/**
	 * Variable de consulta que marca la página de verificación.
	 */
	public const QUERY_VAR = 'convoca_verify';

	/**
	 * Registra la reescritura. Se llama en la activación y en cada arranque.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		add_rewrite_rule( '^verificar-curso/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Añade la variable de consulta a las reconocidas por WordPress.
	 *
	 * @param array<int, string> $vars Variables.
	 * @return array<int, string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Atiende la página de verificación.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- consulta pública de solo lectura.
		$code   = isset( $_GET['codigo'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['codigo'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$format = isset( $_GET['formato'] ) ? sanitize_key( wp_unslash( (string) $_GET['formato'] ) ) : '';

		$data = '' === $code ? null : Certificate::data( $code );

		if ( 'pdf' === $format && null !== $data ) {
			self::serve_pdf( $data['code'] );
			return;
		}

		Certificate::render_template(
			'verificar.php',
			array(
				'code'  => $code,
				'data'  => $data,
				'valid' => null !== $data,
			)
		);

		exit;
	}

	/**
	 * Entrega el PDF del certificado.
	 *
	 * @param string $code Código.
	 * @return void
	 */
	private static function serve_pdf( string $code ): void {
		$path = Certificate::pdf_path( $code );
		if ( '' === $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'El certificado no está disponible en PDF.', 'convoca-courses' ), '', array( 'response' => 404 ) );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- entrega de un fichero propio ya generado.
		readfile( $path );
		exit;
	}
}
