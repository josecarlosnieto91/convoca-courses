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
 * tiene por qué salir de casa. Si la librería no está, se devuelve la URL en texto y el certificado
 * se emite igual: un QR que falta no puede tumbar un certificado.
 */
class Certificate_QR {

	/**
	 * ¿Se puede generar el QR en este sitio?
	 *
	 * Función pura respecto a WordPress: solo mira clases.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( '\chillerlan\QRCode\QRCode' )
			&& class_exists( '\chillerlan\QRCode\QROptions' )
			&& class_exists( '\chillerlan\QRCode\Output\QRGdImagePNG' );
	}

	/**
	 * Imagen del QR como `<img>` con data URI, o la URL en texto si no hay librería.
	 *
	 * Devolver el texto de la URL no es un adorno: es lo que permite verificar el certificado a mano
	 * cuando el sitio no puede generar la imagen.
	 *
	 * @param string $data Contenido a codificar (la URL de verificación).
	 * @return string HTML listo para el PDF.
	 */
	public static function image( string $data ): string {
		if ( '' === trim( $data ) ) {
			return '';
		}

		if ( ! self::available() ) {
			return '<span class="convoca-qr-fallback">' . esc_html( $data ) . '</span>';
		}

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
			return '<img src="data:image/png;base64,' . base64_encode( (string) $png ) . '" alt="' . esc_attr__( 'QR de verificación', 'convoca-courses' ) . '" />';
		} catch ( \Throwable $e ) {
			if ( class_exists( '\Convoca\Core\Logger' ) ) {
				\Convoca\Core\Logger::warning( 'El QR del certificado no se ha podido generar: ' . $e->getMessage(), 'Convoca Courses', 0 );
			}

			return '<span class="convoca-qr-fallback">' . esc_html( $data ) . '</span>';
		}
	}

	/**
	 * ¿El certificado lleva QR?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'certificado_qr' ) && self::available();
	}
}
