<?php
/**
 * Certificados: el código, la búsqueda por código, los datos que se pintan y el PDF.
 *
 * `Certificate` estaba al 0 % (100 statements). El código es lo que una persona teclea a mano en la
 * página de verificación, así que lo que más importa aquí es que el mismo certificado se reconozca
 * escrito de cualquier manera, y que un código que no existe no devuelva datos a medias.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Certificate;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Matricula;
use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de los certificados.
 */
class CertificateCodigoTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_post_types']   = array();
		$GLOBALS['convoca_test_meta']         = array();
		$GLOBALS['convoca_courses_usuarios']  = array();
		$GLOBALS['convoca_courses_titulos']   = array();
		$GLOBALS['convoca_courses_aleatorio'] = 'abcd1234';
		$GLOBALS['convoca_courses_sitio']     = 'Centro de pruebas';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate_QR.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['convoca_courses_usuarios'] = array();
		$GLOBALS['convoca_courses_titulos']  = array();
	}

	/**
	 * El mismo código escrito de cualquier manera se reconoce igual.
	 */
	public function test_un_codigo_se_normaliza(): void {
		$this->assertSame( 'CC124ABCD1234', Certificate::normalize( 'cc-12-4-abcd1234' ) );
		$this->assertSame( 'CC124ABCD1234', Certificate::normalize( ' CC 12 4 ABcd1234 ' ) );
		$this->assertSame( '', Certificate::normalize( '---' ), 'sin letras ni números no queda código' );
	}

	/**
	 * El enlace de verificación lleva el código normalizado.
	 */
	public function test_el_enlace_de_verificacion_lleva_el_codigo_normalizado(): void {
		$this->assertSame(
			'https://example.test/verificar-curso/?codigo=CC124ABCD1234',
			Certificate::verify_url( 'cc-12-4-abcd1234' )
		);
	}

	/**
	 * El código generado lleva el prefijo, el curso, la matrícula y un sufijo.
	 */
	public function test_el_codigo_generado_lleva_curso_matricula_y_sufijo(): void {
		$codigo = Certificate::generate_code( 12, 4 );

		$this->assertSame( 'CC', substr( $codigo, 0, 2 ) );
		$this->assertStringStartsWith( 'CC124', $codigo, 'prefijo, curso y matrícula, en ese orden' );
		$this->assertStringEndsWith( 'ABCD1234', $codigo, 'el sufijo aleatorio va al final' );
		$this->assertMatchesRegularExpression( '/^[A-Z0-9]+$/', $codigo, 'sin guiones ni minúsculas: se teclea a mano' );
	}

	/**
	 * Un código que no está en la base no es de nadie.
	 */
	public function test_un_codigo_desconocido_no_es_de_nadie(): void {
		$this->assertSame( 0, Certificate::find_by_code( 'CC999X' ) );
	}

	/**
	 * Un código vacío no se busca.
	 */
	public function test_un_codigo_vacio_no_se_busca(): void {
		$this->assertSame( 0, Certificate::find_by_code( '   ' ) );
	}

	/**
	 * El código encuentra su matrícula aunque se escriba con guiones.
	 */
	public function test_el_codigo_encuentra_su_matricula(): void {
		$this->matricula( 21, 'CC124ABCD1234' );

		$this->assertSame( 21, Certificate::find_by_code( 'cc-124-abcd1234' ), 'se escribe como sea: se normaliza antes de buscar' );
	}

	/**
	 * Sin código no hay datos que pintar.
	 */
	public function test_sin_codigo_no_hay_datos(): void {
		$this->assertNull( Certificate::data( 'CC000X' ) );
	}

	/**
	 * Con código, los datos del certificado salen completos.
	 */
	public function test_los_datos_del_certificado_salen_completos(): void {
		$this->matricula( 22, 'CC124ABCD1234', 12, 9, '8.5' );
		$GLOBALS['convoca_courses_titulos'][12] = 'Curso de setas';
		$GLOBALS['convoca_courses_usuarios'][9] = (object) array( 'display_name' => 'Una persona' );

		$datos = Certificate::data( 'cc-124-abcd1234' );

		$this->assertSame( 'CC124ABCD1234', $datos['code'] );
		$this->assertSame( 'Curso de setas', $datos['course'] );
		$this->assertSame( 'Una persona', $datos['person'] );
		$this->assertSame( 'Centro de pruebas', $datos['center'] );
		$this->assertSame( '8,50', $datos['nota'], 'la nota va con formato local' );
		$this->assertStringContainsString( 'codigo=CC124ABCD1234', $datos['verify'] );
		$this->assertArrayHasKey( 'qr', $datos );
		$this->assertArrayHasKey( 'date', $datos );
	}

	/**
	 * Sin nota anotada, el certificado no pone un cero.
	 */
	public function test_sin_nota_el_certificado_no_pone_un_cero(): void {
		$this->matricula( 23, 'CC125ABCD1234' );

		$datos = Certificate::data( 'CC125ABCD1234' );

		$this->assertSame( '', $datos['nota'], 'sin nota no hay nota: un 0,00 parece un suspenso' );
		$this->assertSame( '', $datos['person'], 'sin persona no se inventa un nombre' );
	}

	/**
	 * Sin datos no hay PDF.
	 */
	public function test_sin_datos_no_hay_pdf(): void {
		$this->assertSame( '', Certificate::pdf_path( 'CC000X' ) );
	}

	/**
	 * Con datos pero sin la librería de PDF, no hay PDF y no se rompe.
	 */
	public function test_sin_libreria_de_pdf_no_se_genera(): void {
		$this->matricula( 24, 'CC126ABCD1234' );

		$this->assertSame( '', Certificate::pdf_path( 'CC126ABCD1234' ) );
	}

	/**
	 * Una plantilla que no existe no devuelve nada.
	 */
	public function test_una_plantilla_inexistente_no_devuelve_nada(): void {
		$this->assertSame( '', Certificate::render_template( 'no-existe-jamas.php', array() ) );
	}

	/**
	 * Una plantilla real devuelve su HTML, y solo lo imprime si se le pide.
	 */
	public function test_una_plantilla_real_devuelve_su_html(): void {
		$vars = array(
			'code'   => 'CC124ABCD1234',
			'course' => 'Curso de setas',
			'person' => 'Una persona',
			'date'   => '2026-10-01',
			'center' => 'Centro de pruebas',
			'nota'   => '8,50',
			'verify' => 'https://example.test/verificar-curso/?codigo=CC124ABCD1234',
			'qr'     => '',
		);

		$html = Certificate::render_template( 'certificado.php', $vars, false );

		$this->assertNotSame( '', $html, 'la plantilla del certificado se pinta' );
		$this->assertStringContainsString( 'CC124ABCD1234', $html, 'y lleva el código dentro' );

		ob_start();
		$devuelto = Certificate::render_template( 'certificado.php', $vars );
		$impreso  = (string) ob_get_clean();

		$this->assertSame( $devuelto, $impreso, 'con print_html se imprime lo mismo que se devuelve' );
	}

	/**
	 * Da de alta una matrícula con su certificado.
	 *
	 * @param int    $id       Identificador de la matrícula.
	 * @param string $codigo   Código del certificado.
	 * @param int    $curso    Curso.
	 * @param int    $persona  Persona.
	 * @param string $nota     Nota (vacía si no hay).
	 */
	private function matricula( int $id, string $codigo, int $curso = 12, int $persona = 0, string $nota = '' ): void {
		$meta = array(
			'cert_codigo' => $codigo,
			'curso_id'    => (string) $curso,
			'user_id'     => (string) $persona,
			'cert_fecha'  => '2026-10-01',
			'cert_nota'   => $nota,
		);

		$GLOBALS['convoca_test_post_types'][ $id ] = CPT_Matricula::TYPE;
		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['convoca_test_meta'][ $id ][ CPT_Base::META_PREFIX . $clave ] = $valor;
		}
	}
}
