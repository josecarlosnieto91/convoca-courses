<?php
/**
 * Pruebas de la licencia y del periodo de gracia.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\License;
use PHPUnit\Framework\TestCase;

/**
 * Al caducar la licencia no se borra nada: el vídeo aguanta 30 días y el texto sigue siempre.
 */
class LicenseTest extends TestCase {

	/**
	 * Una fecha de caducidad vacía no da gracia.
	 *
	 * @return void
	 */
	public function test_empty_expiry_has_no_grace(): void {
		$this->assertFalse( License::in_grace( '', 1700000000 ) );
	}

	/**
	 * Una fecha ilegible tampoco.
	 *
	 * @return void
	 */
	public function test_unparseable_expiry_has_no_grace(): void {
		$this->assertFalse( License::in_grace( 'no-es-una-fecha', 1700000000 ) );
	}

	/**
	 * Dentro de los 30 días posteriores a la caducidad, sí.
	 *
	 * @return void
	 */
	public function test_within_grace_period(): void {
		$expires = '2026-01-01';
		$base    = (int) strtotime( $expires );

		$this->assertTrue( License::in_grace( $expires, $base ) );
		$this->assertTrue( License::in_grace( $expires, $base + ( 29 * DAY_IN_SECONDS ) ) );
		$this->assertTrue( License::in_grace( $expires, $base + ( 30 * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Pasados los 30 días, el vídeo se corta.
	 *
	 * @return void
	 */
	public function test_after_grace_period(): void {
		$expires = '2026-01-01';
		$base    = (int) strtotime( $expires );

		$this->assertFalse( License::in_grace( $expires, $base + ( 30 * DAY_IN_SECONDS ) + 1 ) );
	}

	/**
	 * La constante de gracia es la que se documenta: 30 días.
	 *
	 * @return void
	 */
	public function test_grace_is_thirty_days(): void {
		$this->assertSame( 30, License::GRACE_DAYS );
	}

	/**
	 * Sin Convoca Core cargado no hay PRO y no se rompe nada.
	 *
	 * @return void
	 */
	public function test_without_core_there_is_no_pro(): void {
		$this->assertFalse( License::has_pro() );
	}
}
