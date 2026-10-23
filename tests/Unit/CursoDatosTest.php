<?php
/**
 * Curso: sus datos de configuración y las tres normalizaciones.
 *
 * `CPT_Curso` estaba al 8,5 % con casi todas sus lecturas sin tocar. Lo que se prueba aquí es lo que
 * consumen la matrícula, el aforo y el examen: precio, plazas, nota mínima, fechas, caducidad y los
 * formatos. Cada lector tiene su valor por defecto, y el defecto es lo que se come un sitio mal
 * configurado, así que también va probado.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use PHPUnit\Framework\TestCase;

/**
 * Lecturas y normalizaciones del curso.
 */
class CursoDatosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta'] = array();
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_meta'] = array();
	}

	/**
	 * Sin configurar, cada lector devuelve su valor por defecto.
	 */
	public function test_sin_configurar_mandan_los_valores_por_defecto(): void {
		$this->assertSame( 0.0, CPT_Curso::price( 3 ) );
		$this->assertSame( 'EUR', CPT_Curso::currency( 3 ) );
		$this->assertSame( 0, CPT_Curso::seats( 3 ), 'sin plazas no hay límite' );
		$this->assertFalse( CPT_Curso::issues_certificate( 3 ), 'sin decirlo, el curso no emite certificado' );
		$this->assertTrue( CPT_Curso::is_sequential( 3 ), 'sin decirlo, el curso es secuencial' );
		$this->assertSame( '', CPT_Curso::enrollment_open_date( 3 ) );
		$this->assertSame( '', CPT_Curso::enrollment_close_date( 3 ) );
		$this->assertSame( '', CPT_Curso::end_date( 3 ) );
		$this->assertSame( 0, CPT_Curso::expiry_days( 3 ) );
		$this->assertSame( 50.0, CPT_Curso::min_score( 3 ), 'sin nota mínima se aprueba con 50' );
		$this->assertSame( 'todas', CPT_Curso::exam_access( 3 ) );
		$this->assertSame( 100, CPT_Curso::exam_access_percent( 3 ) );
		$this->assertNull( CPT_Curso::member_price( 3 ), 'sin precio de socio, no hay precio de socio' );
		$this->assertSame( 0.0, CPT_Curso::member_discount( 3 ) );
		$this->assertFalse( CPT_Curso::members_free( 3 ) );
	}

	/**
	 * Lo configurado se lee tal cual.
	 */
	public function test_lo_configurado_se_lee(): void {
		$this->config( 3, 'precio', '25.5' );
		$this->config( 3, 'moneda', 'usd' );
		$this->config( 3, 'plazas', '12' );
		$this->config( 3, 'certificado', '1' );
		$this->config( 3, 'desbloqueo_secuencial', '0' );
		$this->config( 3, 'fecha_apertura_matricula', '2026-10-01' );
		$this->config( 3, 'fecha_cierre_matricula', '2026-10-20' );
		$this->config( 3, 'fecha_fin', '2026-12-31' );
		$this->config( 3, 'caducidad_dias', '365' );

		$this->assertSame( 25.5, CPT_Curso::price( 3 ) );
		$this->assertSame( 'usd', CPT_Curso::currency( 3 ) );
		$this->assertSame( 12, CPT_Curso::seats( 3 ) );
		$this->assertTrue( CPT_Curso::issues_certificate( 3 ) );
		$this->assertFalse( CPT_Curso::is_sequential( 3 ) );
		$this->assertSame( '2026-10-01', CPT_Curso::enrollment_open_date( 3 ) );
		$this->assertSame( '2026-10-20', CPT_Curso::enrollment_close_date( 3 ) );
		$this->assertSame( '2026-12-31', CPT_Curso::end_date( 3 ) );
		$this->assertSame( 365, CPT_Curso::expiry_days( 3 ) );
	}

	/**
	 * La caducidad y el porcentaje de acceso no admiten negativos ni pasarse de cien.
	 */
	public function test_no_hay_caducidad_negativa_ni_porcentaje_fuera_de_rango(): void {
		$this->config( 3, 'caducidad_dias', '-10' );
		$this->config( 3, 'acceso_examen_porcentaje', '250' );

		$this->assertSame( 0, CPT_Curso::expiry_days( 3 ) );
		$this->assertSame( 100, CPT_Curso::exam_access_percent( 3 ) );

		$this->config( 3, 'acceso_examen_porcentaje', '-5' );
		$this->assertSame( 0, CPT_Curso::exam_access_percent( 3 ) );
	}

	/**
	 * La nota mínima se acota a la escala del módulo.
	 */
	public function test_la_nota_minima_se_acota(): void {
		$this->config( 3, 'nota_minima', '150' );
		$this->assertSame( 100.0, CPT_Curso::min_score( 3 ) );

		$this->config( 3, 'nota_minima', '70' );
		$this->assertSame( 70.0, CPT_Curso::min_score( 3 ) );
	}

	/**
	 * El modo de acceso al examen se reconoce o cae a «todas».
	 */
	public function test_el_modo_de_acceso_se_reconoce_o_cae(): void {
		$this->config( 3, 'acceso_examen', 'MODULO' );
		$this->assertSame( 'modulo', CPT_Curso::exam_access( 3 ), 'se compara en minúsculas' );

		$this->config( 3, 'acceso_examen', 'porcentaje' );
		$this->assertSame( 'porcentaje', CPT_Curso::exam_access( 3 ) );

		$this->config( 3, 'acceso_examen', 'inventado' );
		$this->assertSame( 'todas', CPT_Curso::exam_access( 3 ), 'lo que no se reconoce no bloquea el examen' );
	}

	/**
	 * El precio de socio: negativo no es precio, positivo se redondea.
	 */
	public function test_el_precio_de_socio(): void {
		$this->config( 3, 'precio_socio', '-1' );
		$this->assertNull( CPT_Curso::member_price( 3 ) );

		$this->config( 3, 'precio_socio', '18.755' );
		$this->assertSame( 18.76, CPT_Curso::member_price( 3 ) );
	}

	/**
	 * El descuento de socio y la gratuidad para socios.
	 */
	public function test_descuento_y_gratuidad_para_socios(): void {
		$this->config( 3, 'descuento_socio', '15' );
		$this->config( 3, 'gratis_socios', '1' );

		$this->assertSame( 15.0, CPT_Curso::member_discount( 3 ) );
		$this->assertTrue( CPT_Curso::members_free( 3 ) );
	}

	/**
	 * Una fecha se normaliza a `Y-m-d`, y lo que no es fecha se descarta.
	 */
	public function test_las_fechas_se_normalizan_o_se_descartan(): void {
		$this->assertSame( '', CPT_Curso::normalize_date( '   ' ) );
		$this->assertSame( '', CPT_Curso::normalize_date( 'no es una fecha' ) );
		$this->assertSame( '2026-10-05', CPT_Curso::normalize_date( '2026-10-05' ) );
		$this->assertSame( '2026-10-05', CPT_Curso::normalize_date( '2026-10-05 10:30:00' ), 'con hora se queda solo el día' );
		$this->assertSame( '', CPT_Curso::normalize_date( '5 de octubre de 2026' ), 'una fecha en castellano no la entiende strtotime: se descarta' );
	}

	/**
	 * Una nota se acota a 0-100 y se redondea a dos decimales.
	 */
	public function test_las_notas_se_acotan_y_redondean(): void {
		$this->assertSame( 0.0, CPT_Curso::normalize_score( -5.0 ) );
		$this->assertSame( 100.0, CPT_Curso::normalize_score( 150.0 ) );
		$this->assertSame( 66.67, CPT_Curso::normalize_score( 66.666 ) );
	}

	/**
	 * Un importe se pinta con dos decimales y su moneda en mayúsculas.
	 */
	public function test_un_importe_se_pinta_con_su_moneda(): void {
		$this->assertSame( '25,50 EUR', CPT_Curso::format_amount( 25.5, 'eur' ), 'la moneda en mayúsculas y con dos decimales' );
		$this->assertSame( '0,00 EUR', CPT_Curso::format_amount( 0.0, 'EUR' ) );
	}

	/**
	 * El precio con formato sale del precio y la moneda del curso.
	 */
	public function test_el_precio_con_formato_sale_del_curso(): void {
		$this->config( 3, 'precio', '30' );
		$this->config( 3, 'moneda', 'usd' );

		$this->assertSame( '30,00 USD', CPT_Curso::formatted_price( 3 ) );
		$this->assertSame( '0,00 EUR', CPT_Curso::formatted_price( 4 ), 'un curso sin precio no pinta vacío' );
	}

	/**
	 * Guarda un valor de configuración del curso.
	 *
	 * @param int    $id    Curso.
	 * @param string $clave Clave sin prefijo.
	 * @param string $valor Valor.
	 */
	private function config( int $id, string $clave, string $valor ): void {
		$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . $clave ] = $valor;
	}
}
