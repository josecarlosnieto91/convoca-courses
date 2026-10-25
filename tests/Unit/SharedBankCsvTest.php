<?php
/**
 * Banco compartido de preguntas: el CSV de ida y vuelta.
 *
 * `Shared_Bank` estaba al 0 % (87 statements). Lo que de verdad importa aquí es que un CSV exportado se
 * pueda volver a importar sin perder nada: los índices de las respuestas correctas van en el fichero
 * **desde 1** (para que los lea una persona) y en la pregunta **desde 0**.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Mailer;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\Gradebook;
use Convoca\Courses\Installer;
use Convoca\Courses\Questions;
use Convoca\Courses\Shared_Bank;
use PHPUnit\Framework\TestCase;

/**
 * Ida y vuelta del CSV del banco de preguntas.
 */
class SharedBankCsvTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_filtros'] = array();
		$GLOBALS['wpdb']->vars              = array();
		$GLOBALS['wpdb']->filas             = array();
		$GLOBALS['wpdb']->resultados        = array();
		$GLOBALS['wpdb']->consultas         = array();
		$GLOBALS['wpdb']->inserts           = array();
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Gradebook.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Questions.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Shared_Bank.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_filtros'] = array();
		$GLOBALS['wpdb']->vars              = array();
		$GLOBALS['wpdb']->filas             = array();
		$GLOBALS['wpdb']->resultados        = array();
		$GLOBALS['wpdb']->consultas         = array();
		$GLOBALS['wpdb']->inserts           = array();
	}

	/**
	 * El banco compartido depende del PRO.
	 */
	public function test_el_banco_compartido_depende_del_pro(): void {
		$this->assertFalse( Shared_Bank::enabled() );

		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$this->assertTrue( Shared_Bank::enabled() );
	}

	/**
	 * El CSV lleva cabecera, las cuatro opciones y las correctas desde uno.
	 */
	public function test_el_csv_lleva_cabecera_y_correctas_desde_uno(): void {
		$csv    = Shared_Bank::to_csv( array( $this->pregunta( array( 'A', 'B' ), array( '0', '1' ) ) ) );
		$lineas = explode( "\r\n", trim( $csv ) );

		$this->assertCount( 2, $lineas, 'cabecera y una fila' );
		$this->assertStringContainsString( '"tipo";"enunciado";"opcion1"', $lineas[0], 'todo va entrecomillado' );
		$this->assertStringContainsString( '"unica"', $lineas[1] );
		$this->assertStringContainsString( '"1,2"', $lineas[1], 'las correctas salen desde 1, como las lee una persona' );
		$this->assertStringContainsString( '"2.50"', $lineas[1], 'los puntos con punto decimal y dos decimales' );
		$this->assertStringEndsWith( "\r\n", $csv );
	}

	/**
	 * Las opciones que faltan se rellenan vacías en vez de dejar la fila corta.
	 */
	public function test_las_opciones_que_faltan_se_rellenan(): void {
		$csv = Shared_Bank::to_csv( array( $this->pregunta( array( 'A' ), array( '0' ) ) ) );

		$this->assertStringContainsString( '"A";"";"";""', $csv, 'cuatro columnas de opción siempre' );
	}

	/**
	 * Un texto con comillas y con el separador no rompe el CSV.
	 */
	public function test_un_texto_con_comillas_y_separador_no_rompe_el_csv(): void {
		$csv = Shared_Bank::to_csv(
			array( $this->pregunta( array( 'A' ), array( '0' ), array( 'enunciado' => 'Dijo "hola"; y se fue' ) ) )
		);

		$vuelta = Shared_Bank::parse_csv( $csv );

		$this->assertCount( 1, $vuelta );
		$this->assertSame( 'Dijo "hola"; y se fue', $vuelta[0]['enunciado'] );
	}

	/**
	 * Leer el CSV devuelve las correctas desde cero.
	 */
	public function test_leer_el_csv_devuelve_las_correctas_desde_cero(): void {
		$csv = "tipo;enunciado;opcion1;opcion2;opcion3;opcion4;correctas;puntos;explicacion\r\n"
			. "multiple;Elige;A;B;C;D;\"1,3\";2.5;Porque sí\r\n";

		$preguntas = Shared_Bank::parse_csv( $csv );

		$this->assertCount( 1, $preguntas );
		$this->assertSame( 'multiple', $preguntas[0]['tipo'] );
		$this->assertSame( array( 'A', 'B', 'C', 'D' ), $preguntas[0]['opciones'] );
		$this->assertSame( array( '0', '2' ), $preguntas[0]['correctas'], '1 y 3 del fichero son 0 y 2 en la pregunta' );
		$this->assertSame( 2.5, $preguntas[0]['puntos'] );
		$this->assertSame( 'Porque sí', $preguntas[0]['explicacion'] );
	}

	/**
	 * La cabecera se salta y las filas sin enunciado también.
	 */
	public function test_la_cabecera_y_las_filas_vacias_se_saltan(): void {
		$csv = "tipo;enunciado;opcion1;opcion2;opcion3;opcion4;correctas;puntos;explicacion\r\n"
			. "unica;;A;B;;;1;1;\r\n"
			. "unica;Buena;A;B;;;1;1;\r\n";

		$preguntas = Shared_Bank::parse_csv( $csv );

		$this->assertCount( 1, $preguntas, 'sin enunciado no hay pregunta' );
		$this->assertSame( 'Buena', $preguntas[0]['enunciado'] );
	}

	/**
	 * Un CSV vacío o sin filas legibles no da preguntas.
	 */
	public function test_un_csv_vacio_no_da_preguntas(): void {
		$this->assertSame( array(), Shared_Bank::parse_csv( '' ) );
		$this->assertSame( array(), Shared_Bank::parse_csv( '   ' ) );
	}

	/**
	 * Sin puntos en el fichero la pregunta vale uno; un índice inventado no cuenta.
	 *
	 * Ojo con la segunda mitad: si la columna de puntos **no está vacía** pero no es un número, el
	 * valor se queda en 0. Comportamiento real, fijado aquí y abierto como issue: una errata en esa
	 * columna deja la pregunta sin peso en vez de darle el punto por defecto.
	 */
	public function test_sin_puntos_vale_uno_y_los_indices_raros_no_cuentan(): void {
		$csv = "unica;Pregunta;A;B;;;0,-3,2;;\r\n";

		$preguntas = Shared_Bank::parse_csv( $csv );

		$this->assertSame( 1.0, $preguntas[0]['puntos'], 'sin puntos, un punto' );
		$this->assertSame( array( '1' ), $preguntas[0]['correctas'], 'el 0 y el negativo se descartan; 2 pasa a 1' );

		$con_errata = Shared_Bank::parse_csv( "unica;Pregunta;A;B;;;1;incalculable;\r\n" );
		$this->assertSame( 1.0, $con_errata[0]['puntos'], 'una errata en los puntos NO puede dejar la pregunta a cero' );

		// La coma decimal se acepta: el separador de columnas es `;`, así que en un CSV español la coma es decimal.
		$con_coma = Shared_Bank::parse_csv( "unica;Pregunta;A;B;;;1;2,5;\r\n" );
		$this->assertSame( 2.5, $con_coma[0]['puntos'], '2,5 son dos puntos y medio, no cero ni uno' );

		// Y un número de verdad se respeta.
		$con_numero = Shared_Bank::parse_csv( "unica;Pregunta;A;B;;;1;3;\r\n" );
		$this->assertSame( 3.0, $con_numero[0]['puntos'] );
	}

	/**
	 * Una fila a la que le faltan columnas no revienta.
	 */
	public function test_una_fila_corta_no_revienta(): void {
		$preguntas = Shared_Bank::parse_csv( "unica;Solo enunciado\r\n" );

		$this->assertCount( 1, $preguntas );
		$this->assertSame( array(), $preguntas[0]['opciones'] );
		$this->assertSame( array(), $preguntas[0]['correctas'] );
	}

	/**
	 * Lo exportado se vuelve a leer igual: eso es lo que hace útil al banco.
	 */
	public function test_lo_exportado_se_vuelve_a_leer_igual(): void {
		$original = array(
			$this->pregunta(
				array( 'Uno', 'Dos', 'Tres' ),
				array( '1' ),
				array(
					'tipo'   => 'unica',
					'puntos' => 1.0,
				)
			),
			$this->pregunta(
				array( 'Sí', 'No' ),
				array( '0', '1' ),
				array(
					'tipo'   => 'multiple',
					'puntos' => 2.5,
				)
			),
		);

		$vuelta = Shared_Bank::parse_csv( Shared_Bank::to_csv( $original ) );

		$this->assertCount( 2, $vuelta );
		$this->assertSame( 'unica', $vuelta[0]['tipo'] );
		$this->assertSame( array( 'Uno', 'Dos', 'Tres' ), $vuelta[0]['opciones'] );
		$this->assertSame( array( '1' ), $vuelta[0]['correctas'] );
		$this->assertSame( 1.0, $vuelta[0]['puntos'] );
		$this->assertSame( 'multiple', $vuelta[1]['tipo'] );
		$this->assertSame( array( '0', '1' ), $vuelta[1]['correctas'] );
		$this->assertSame( 2.5, $vuelta[1]['puntos'] );
	}

	/**
	 * Exportar sin PRO no es una opción.
	 */
	public function test_exportar_sin_pro_no_se_puede(): void {
		$r = Shared_Bank::export_csv( 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_pro_required', $r->get_error_code() );
	}

	/**
	 * Exportar un banco sin preguntas lo dice, en vez de dar un CSV con solo la cabecera.
	 */
	public function test_exportar_sin_preguntas_lo_dice(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$r = Shared_Bank::export_csv( 3 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_bank_empty', $r->get_error_code() );
	}

	/**
	 * Exportar con preguntas devuelve su CSV.
	 */
	public function test_exportar_con_preguntas_devuelve_el_csv(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;
		$GLOBALS['wpdb']->resultados                                   = array(
			array(
				array(
					'id'        => '1',
					'course_id' => '3',
					'tipo'      => 'unica',
					'enunciado' => 'Pregunta del banco',
					'opciones'  => '["A","B"]',
					'correctas' => '[0]',
					'puntos'    => '1',
				),
			),
		);

		// Con examen se lee su tabla; sin él se pasa por el banco del curso (WP_Query), que es otra
		// consulta: aquí interesa el camino de `Questions::all()`.
		$csv = Shared_Bank::export_csv( 3, 7 );

		$this->assertIsString( $csv );
		$this->assertStringContainsString( 'Pregunta del banco', $csv );
	}

	/**
	 * Importar sin PRO no es una opción.
	 */
	public function test_importar_sin_pro_no_se_puede(): void {
		$r = Shared_Bank::import_csv( 3, 0, 'unica;Pregunta;A;B;;;1;1;' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_pro_required', $r->get_error_code() );
	}

	/**
	 * Importar un CSV sin preguntas legibles lo dice.
	 */
	public function test_importar_un_csv_ilegible_lo_dice(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$r = Shared_Bank::import_csv( 3, 0, "tipo;enunciado;opcion1;opcion2;opcion3;opcion4;correctas;puntos;explicacion\r\n" );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'convoca_courses_bank_parse', $r->get_error_code() );
	}

	/**
	 * Importar cuenta las creadas y dice qué fila falló y por qué.
	 */
	public function test_importar_cuenta_las_creadas_y_las_que_fallan(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;
		$GLOBALS['wpdb']->inserts                                      = array();

		$csv = "tipo;enunciado;opcion1;opcion2;opcion3;opcion4;correctas;puntos;explicacion\r\n"
			. "unica;Buena;A;B;;;1;1;\r\n"
			. "unica;Mala;A;; ;;;1;1;\r\n";

		$r = Shared_Bank::import_csv( 3, 7, $csv );

		$this->assertSame( 1, $r['creadas'], 'la buena entra' );
		$this->assertCount( 1, $r['errores'], 'la mala se explica' );
		$this->assertStringContainsString( 'Fila 3', $r['errores'][0], 'la fila se cuenta como la ve una persona, con la cabecera dentro' );
		$this->assertCount( 1, $GLOBALS['wpdb']->inserts, 'solo se inserta la válida' );
	}

	/**
	 * Una pregunta normalizada como la que devuelve `Questions::all()`.
	 *
	 * @param array<int, string>   $opciones Opciones.
	 * @param array<int, string>   $correctas Correctas (desde cero).
	 * @param array<string, mixed> $extra     Otros campos.
	 * @return array<string, mixed>
	 */
	private function pregunta( array $opciones, array $correctas, array $extra = array() ): array {
		return array_merge(
			array(
				'id'          => 1,
				'course_id'   => 3,
				'exam_id'     => 7,
				'tipo'        => 'unica',
				'enunciado'   => 'Pregunta',
				'opciones'    => $opciones,
				'correctas'   => $correctas,
				'puntos'      => 2.5,
				'explicacion' => '',
				'orden'       => 0,
			),
			$extra
		);
	}
}
