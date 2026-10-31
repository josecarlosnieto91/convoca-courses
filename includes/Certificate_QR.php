<?php
/**
 * QR de verificación del certificado (PRO).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QR del certificado, generado en el propio sitio.
 *
 * Nada de servicios externos de QR: la URL de verificación lleva el código del certificado y no
 * tiene por qué salir de casa. Si la librería no está, **el bloque no queda vacío**: se imprime la
 * URL de verificación con su rótulo. Un hueco con el rótulo «QR» y nada debajo es peor que no
 * ponerlo (courses#29).
 */
class Certificate_QR {

	/**
	 * ¿Se puede generar la imagen del QR en este sitio?
	 *
	 * Función pura respecto a WordPress: solo mira clases. Va blindada porque un autoloader que no
	 * encuentra la librería puede lanzar, y un certificado no se puede tumbar por eso.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		try {
			return class_exists( '\chillerlan\QRCode\QRCode' )
				&& class_exists( '\chillerlan\QRCode\QROptions' )
				&& class_exists( '\chillerlan\QRCode\Output\QRGdImagePNG' );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Bloque de verificación: imagen del QR si se puede, y si no la URL impresa.
	 *
	 * Nunca devuelve un bloque vacío: o hay imagen, o hay URL con su rótulo.
	 *
	 * @param string $data Contenido a codificar (la URL de verificación).
	 * @return string HTML listo para el PDF.
	 */
	public static function image( string $data ): string {
		if ( '' === trim( $data ) ) {
			return '';
		}

		if ( self::enabled() && self::available() ) {
			try {
				$options = new \chillerlan\QRCode\QROptions(
					array(
						'outputInterface'  => \chillerlan\QRCode\Output\QRGdImagePNG::class,
						'eccLevel'         => \chillerlan\QRCode\Common\EccLevel::M,
						'scale'            => 6,
						'addQuietzone'     => true,
						'quietzoneSize'    => 2,
						'outputBase64'     => false,
						'imageTransparent' => false,
					)
				);

				$png = ( new \chillerlan\QRCode\QRCode( $options ) )->render( $data );

				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- es el formato obligatorio de un data URI de imagen, no ofuscación de código.
				$imagen = '<img src="data:image/png;base64,' . base64_encode( (string) $png ) . '" alt="' . esc_attr__( 'QR de verificación', 'convoca-courses' ) . '" />';

				return $imagen . '<span class="convoca-qr-fallback__url">' . esc_html( $data ) . '</span>';
			} catch ( \Throwable $e ) {
				if ( class_exists( '\Convoca\Core\Logger' ) ) {
					\Convoca\Core\Logger::warning( 'El QR del certificado no se ha podido generar: ' . $e->getMessage(), 'Convoca Courses', 0 );
				}
			}
		}

		// Sin librería (o sin PRO) se imprime la URL completa: es verificable a mano y no deja hueco.
		return '<span class="convoca-qr-fallback__label">' . esc_html__( 'Verificación', 'convoca-courses' ) . '</span>'
			. '<span class="convoca-qr-fallback__url">' . esc_html( $data ) . '</span>';
	}

	/**
	 * ¿El certificado lleva el QR gráfico?
	 *
	 * Es una función PRO. Si no está, el bloque sigue saliendo con la URL en texto: la verificación
	 * del certificado no puede depender de una licencia (courses#29).
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'certificado_qr' );
	}
}
