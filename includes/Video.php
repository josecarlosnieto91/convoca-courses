<?php
/**
 * Vídeo PRO: URL firmada de un almacenamiento compatible con S3, o URL externa.
 *
 * El plugin no aloja ni transcodifica vídeo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Firma de URLs y resolución del vídeo de una lección.
 */
class Video {

	/**
	 * Opción con la configuración del almacenamiento.
	 */
	public const OPTION = 'convoca_courses_video';

	/**
	 * Segundos de vida de una URL firmada.
	 */
	public const TTL = 900;

	/**
	 * Configuración guardada, con valores por defecto.
	 *
	 * @return array<string, string|int|bool>
	 */
	public static function config(): array {
		$defaults = array(
			'endpoint'    => '',
			'bucket'      => '',
			'region'      => 'auto',
			'key'         => '',
			'secret'      => '',
			'path_prefix' => '',
			'path_style'  => true,
		);

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$config = array_merge( $defaults, $stored );

		if ( '' !== (string) $config['secret'] && ! self::is_encrypted( (string) $config['secret'] ) ) {
			$config['secret'] = (string) $config['secret'];
		} else {
			$config['secret'] = self::decrypt( (string) $config['secret'] );
		}

		return $config;
	}

	/**
	 * Cifra un secreto con la sal del sitio antes de guardarlo.
	 *
	 * @param string $plain Secreto en claro.
	 * @return string
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		return 'enc:' . base64_encode( self::xor_pad( $plain, self::salt() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- formato del dato, no ofuscación de código.
	}

	/**
	 * Descifra un secreto guardado. Si no está cifrado lo devuelve tal cual.
	 *
	 * @param string $stored Valor guardado.
	 * @return string
	 */
	public static function decrypt( string $stored ): string {
		if ( '' === $stored || ! self::is_encrypted( $stored ) ) {
			return $stored;
		}
		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- idem.
		if ( false === $raw ) {
			return '';
		}
		return self::xor_pad( $raw, self::salt() );
	}

	/**
	 * ¿El valor guardado está cifrado por nosotros?
	 *
	 * @param string $value Valor.
	 * @return bool
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strpos( $value, 'enc:' );
	}

	/**
	 * Sal del sitio, reutilizando la del núcleo si está.
	 *
	 * @return string
	 */
	private static function salt(): string {
		if ( class_exists( '\Convoca\Core\Utils' ) && method_exists( '\Convoca\Core\Utils', 'get_persistent_salt' ) ) {
			return (string) \Convoca\Core\Utils::get_persistent_salt();
		}
		return (string) wp_salt( 'auth' );
	}

	/**
	 * XOR con la sal repetida. No pretende ser criptografía fuerte: evita que la clave quede
	 * legible en la base de datos y en los volcados, igual que el resto del ecosistema.
	 *
	 * @param string $input Texto.
	 * @param string $pad   Sal.
	 * @return string
	 */
	private static function xor_pad( string $input, string $pad ): string {
		$length = strlen( $input );
		if ( 0 === $length || '' === $pad ) {
			return $input;
		}
		$pad    = str_pad( '', $length, $pad );
		$output = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$output .= $input[ $i ] ^ $pad[ $i ];
		}
		return $output;
	}

	/**
	 * ¿Está configurado el almacenamiento?
	 *
	 * @param array<string, string|int|bool> $config Configuración.
	 * @return bool
	 */
	public static function is_configured( array $config ): bool {
		return '' !== (string) $config['endpoint'] && '' !== (string) $config['bucket'] && '' !== (string) $config['secret'];
	}

	/**
	 * Construye una URL firmada (AWS Signature V4, query string) para un objeto.
	 *
	 * Función pura salvo por la configuración: sin llamadas de red, para poder probarla.
	 *
	 * @param array<string, string|int|bool> $config  Configuración del almacenamiento.
	 * @param string                         $key     Clave del objeto dentro del bucket.
	 * @param int                            $expires Segundos de validez.
	 * @param int                            $now     Marca de tiempo actual.
	 * @return string URL firmada, o cadena vacía si falta configuración.
	 */
	public static function presigned_url( array $config, string $key, int $expires, int $now ): string {
		if ( ! self::is_configured( $config ) || '' === $key ) {
			return '';
		}

		$endpoint = untrailingslashit( (string) $config['endpoint'] );
		$bucket   = (string) $config['bucket'];
		$region   = (string) $config['region'];
		$prefix   = trim( (string) $config['path_prefix'], '/' );

		$object_key = '' === $prefix ? $key : $prefix . '/' . ltrim( $key, '/' );
		$object_key = ltrim( $object_key, '/' );

		$parts  = wp_parse_url( $endpoint );
		$scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] : 'https';
		$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$base   = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';

		if ( '' === $host ) {
			return '';
		}

		$host = $host . $port;
		if ( $config['path_style'] ) {
			$path = $base . '/' . $bucket . '/' . $object_key;
		} else {
			// Estilo virtual-host: el bucket va en el nombre del servidor.
			$host = $bucket . '.' . $host;
			$path = $base . '/' . $object_key;
		}

		$date_short = gmdate( 'Ymd', $now );
		$date_full  = gmdate( 'Ymd\THis\Z', $now );
		$scope      = $date_short . '/' . $region . '/s3/aws4_request';
		$algorithm  = 'AWS4-HMAC-SHA256';
		$credential = (string) $config['key'] . '/' . $scope;

		$query = array(
			'X-Amz-Algorithm'     => $algorithm,
			'X-Amz-Credential'    => $credential,
			'X-Amz-Date'          => $date_full,
			'X-Amz-Expires'       => (string) max( 1, $expires ),
			'X-Amz-SignedHeaders' => 'host',
		);

		$canonical_query = self::canonical_query( $query );

		$canonical_request = implode(
			"\n",
			array(
				'GET',
				$path,
				$canonical_query,
				'host:' . $host . "\n",
				'host',
				'UNSIGNED-PAYLOAD',
			)
		);

		$string_to_sign = implode(
			"\n",
			array(
				$algorithm,
				$date_full,
				$scope,
				hash( 'sha256', $canonical_request ),
			)
		);

		$signing_key = self::signing_key( (string) $config['secret'], $date_short, $region, 's3' );
		$signature   = hash_hmac( 'sha256', $string_to_sign, $signing_key );

		return $scheme . '://' . $host . $path . '?' . $canonical_query . '&X-Amz-Signature=' . $signature;
	}

	/**
	 * Query canónica: claves y valores escapados y ordenados.
	 *
	 * @param array<string, string> $params Parámetros.
	 * @return string
	 */
	public static function canonical_query( array $params ): string {
		ksort( $params );
		$pairs = array();
		foreach ( $params as $name => $value ) {
			$pairs[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $value );
		}
		return implode( '&', $pairs );
	}

	/**
	 * Clave de firma derivada.
	 *
	 * @param string $secret  Secreto.
	 * @param string $date    Fecha corta (Ymd).
	 * @param string $region  Región.
	 * @param string $service Servicio.
	 * @return string
	 */
	private static function signing_key( string $secret, string $date, string $region, string $service ): string {
		$key = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
		$key = hash_hmac( 'sha256', $region, $key, true );
		$key = hash_hmac( 'sha256', $service, $key, true );
		return hash_hmac( 'sha256', 'aws4_request', $key, true );
	}

	/**
	 * URL reproducible para una lección, si procede.
	 *
	 * @param int $lesson_id Lección.
	 * @return array{src: string, external: bool}
	 */
	public static function lesson_source( int $lesson_id ): array {
		$external = CPT_Leccion::get_string( $lesson_id, 'video_url' );
		if ( '' !== $external ) {
			return array(
				'src'      => $external,
				'external' => true,
			);
		}

		$key = CPT_Leccion::get_string( $lesson_id, 'video_key' );
		if ( '' === $key ) {
			return array(
				'src'      => '',
				'external' => false,
			);
		}

		$config = self::config();
		$url    = self::presigned_url( $config, $key, self::TTL, time() );

		return array(
			'src'      => $url,
			'external' => false,
		);
	}
}
