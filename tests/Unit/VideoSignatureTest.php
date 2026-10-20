<?php
/**
 * Pruebas de la firma de URLs de vídeo (AWS Signature V4).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Video;
use PHPUnit\Framework\TestCase;

/**
 * La firma se prueba contra el vector publicado por AWS: si la implementación se rompe, el vídeo deja
 * de verse y nadie se entera hasta que un cliente llama.
 */
class VideoSignatureTest extends TestCase {

	/**
	 * Configuración equivalente al ejemplo de la documentación de AWS (estilo virtual-host).
	 *
	 * @return array<string, string|int|bool>
	 */
	private function config(): array {
		return array(
			'endpoint'    => 'https://s3.amazonaws.com',
			'bucket'      => 'examplebucket',
			'region'      => 'us-east-1',
			'key'         => 'AKIAIOSFODNN7EXAMPLE',
			'secret'      => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'path_prefix' => '',
			'path_style'  => false,
		);
	}

	/**
	 * Vector de AWS: GET /test.txt firmado el 20130524T000000Z con 86400 s de validez.
	 *
	 * @see https://docs.aws.amazon.com/AmazonS3/latest/API/sigv4-query-string-auth.html
	 *
	 * @return void
	 */
	public function test_matches_the_aws_published_vector(): void {
		$url = Video::presigned_url( $this->config(), 'test.txt', 86400, 1369353600 );

		$this->assertStringContainsString( 'X-Amz-Algorithm=AWS4-HMAC-SHA256', $url );
		$this->assertStringContainsString( 'X-Amz-Date=20130524T000000Z', $url );
		$this->assertStringContainsString( 'X-Amz-Expires=86400', $url );
		$this->assertStringContainsString( 'X-Amz-SignedHeaders=host', $url );
		$this->assertStringContainsString(
			'X-Amz-Credential=AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request',
			$url
		);
		$this->assertStringContainsString(
			'X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404',
			$url
		);
		$this->assertStringStartsWith( 'https://examplebucket.s3.amazonaws.com/test.txt?', $url );
	}

	/**
	 * La misma entrada da la misma URL y otra fecha da otra firma.
	 *
	 * @return void
	 */
	public function test_signature_is_deterministic_and_time_bound(): void {
		$a = Video::presigned_url( $this->config(), 'curso/leccion.mp4', 900, 1700000000 );
		$b = Video::presigned_url( $this->config(), 'curso/leccion.mp4', 900, 1700000000 );
		$c = Video::presigned_url( $this->config(), 'curso/leccion.mp4', 900, 1700000900 );

		$this->assertSame( $a, $b );
		$this->assertNotSame( $a, $c );
	}

	/**
	 * Sin configuración no hay URL: el módulo no puede inventarse un origen.
	 *
	 * @return void
	 */
	public function test_without_configuration_returns_empty(): void {
		$config = $this->config();
		$config['bucket'] = '';

		$this->assertSame( '', Video::presigned_url( $config, 'test.txt', 900, 1700000000 ) );
		$this->assertSame( '', Video::presigned_url( $this->config(), '', 900, 1700000000 ) );
	}

	/**
	 * El estilo de ruta mete el bucket en el camino.
	 *
	 * @return void
	 */
	public function test_path_style_puts_the_bucket_in_the_path(): void {
		$config = $this->config();
		$config['path_style'] = true;

		$url = Video::presigned_url( $config, 'leccion.mp4', 900, 1700000000 );
		$this->assertStringStartsWith( 'https://s3.amazonaws.com/examplebucket/leccion.mp4?', $url );
	}

	/**
	 * El prefijo de objetos se respeta.
	 *
	 * @return void
	 */
	public function test_prefix_is_applied(): void {
		$config = $this->config();
		$config['path_prefix'] = 'cursos/2026/';
		$config['path_style']  = true;

		$url = Video::presigned_url( $config, 'leccion.mp4', 900, 1700000000 );
		$this->assertStringContainsString( 's3.amazonaws.com/examplebucket/cursos/2026/leccion.mp4?', $url );
	}

	/**
	 * El secreto guardado se cifra y se recupera igual.
	 *
	 * @return void
	 */
	public function test_secret_round_trip(): void {
		$plain = 'secreto-de-prueba';

		$stored = Video::encrypt( $plain );
		$this->assertNotSame( $plain, $stored );
		$this->assertTrue( Video::is_encrypted( $stored ) );
		$this->assertSame( $plain, Video::decrypt( $stored ) );
		$this->assertSame( '', Video::encrypt( '' ) );
	}
}
