<?php
/**
 * Pantallas PRO y rutas de integración con WordPress.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access_Log;
use Convoca\Courses\Admin_Informes;
use Convoca\Courses\Admin_Registro;
use Convoca\Courses\Admin_SCORM;
use Convoca\Courses\Reports;
use Convoca\Courses\Survey;
use PHPUnit\Framework\TestCase;

/**
 * Ejercita las pantallas con los dobles mínimos del arnés.
 */
final class ProPantallasTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array<string, mixed>
	 */
	private array $previo = array();

	/**
	 * Doble de base de datos para las pantallas.
	 *
	 * @return object
	 */
	private function wpdb_doble(): object {
		return new class() {
			/**
			 * Prefijo de tablas.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';
			/**
			 * Último id insertado.
			 *
			 * @var int
			 */
			public $insert_id = 7;

			/**
			 * Prepara una consulta (sin interpretar).
			 *
			 * @param string $query Consulta.
			 * @param mixed  ...$args Argumentos.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				unset( $args );
				return (string) $query;
			}

			/**
			 * Sin filas.
			 *
			 * @param string $query Consulta.
			 * @param string $output Salida.
			 * @return array<int, mixed>
			 */
			public function get_results( $query, $output = 'OBJECT' ) {
				unset( $query, $output );
				return array();
			}

			/**
			 * Sin valor.
			 *
			 * @param string $query Consulta.
			 * @return null
			 */
			public function get_var( $query ) {
				unset( $query );
				return null;
			}

			/**
			 * Sin columna.
			 *
			 * @param string $query Consulta.
			 * @return array<int, mixed>
			 */
			public function get_col( $query ) {
				unset( $query );
				return array();
			}

			/**
			 * Fila de prueba.
			 *
			 * @param string $query Consulta.
			 * @param string $output Salida.
			 * @return array<string, mixed>
			 */
			public function get_row( $query, $output = 'OBJECT' ) {
				unset( $query, $output );
				return array(
					'id'            => 11,
					'identidad_id'  => 0,
					'user_id'       => 1,
					'answers'       => '[5,4]',
					'comentario'    => 'Bien',
					'lesson_status' => 'completed',
					'score_raw'     => 90,
					'session_time'  => '00:10:00',
				);
			}

			/**
			 * Inserta.
			 *
			 * @param string $tabla Tabla.
			 * @param array  $datos Datos.
			 * @param array  $formato Formato.
			 * @return int
			 */
			public function insert( $tabla, $datos, $formato = null ) {
				unset( $tabla, $datos, $formato );
				return 1;
			}

			/**
			 * Reemplaza.
			 *
			 * @param string $tabla Tabla.
			 * @param array  $datos Datos.
			 * @param array  $formato Formato.
			 * @return int
			 */
			public function replace( $tabla, $datos, $formato = null ) {
				unset( $tabla, $datos, $formato );
				return 1;
			}
		};
	}

	/**
	 * Prepara globals.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'caps'    => $GLOBALS['convoca_test_caps'] ?? null,
			'filtros' => $GLOBALS['convoca_courses_filtros'] ?? null,
			'wpdb'    => $GLOBALS['wpdb'] ?? null,
			'tipos'   => $GLOBALS['convoca_test_post_types'] ?? null,
			'titulos' => $GLOBALS['convoca_test_titulos'] ?? null,
		);

		$GLOBALS['convoca_test_caps']       = array( 'convoca_manage_courses' => true );
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => true );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de base de datos de la prueba.
		$GLOBALS['wpdb']                    = $this->wpdb_doble();
		$GLOBALS['convoca_test_post_types'] = array( 5 => 'convoca_curso' );
		$GLOBALS['convoca_test_titulos']    = array( 5 => 'Curso de prueba' );
	}

	/**
	 * Restaura globals.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_caps']       = $this->previo['caps'];
		$GLOBALS['convoca_courses_filtros'] = $this->previo['filtros'];
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restaura el doble de la prueba.
		$GLOBALS['wpdb']                    = $this->previo['wpdb'];
		$GLOBALS['convoca_test_post_types'] = $this->previo['tipos'];
		$GLOBALS['convoca_test_titulos']    = $this->previo['titulos'];
	}

	/**
	 * Registro sin PRO avisa.
	 */
	public function test_registro_sin_pro(): void {
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => false );

		ob_start();
		( new Admin_Registro() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'función PRO', $html );
	}

	/**
	 * Registro con PRO y sin eventos.
	 */
	public function test_registro_con_pro_sin_eventos(): void {
		ob_start();
		( new Admin_Registro() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Cadena verificada', $html );
		$this->assertStringContainsString( 'Todavía no hay eventos', $html );
	}

	/**
	 * Informes sin PRO avisa.
	 */
	public function test_informes_sin_pro(): void {
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => false );

		ob_start();
		( new Admin_Informes() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'función PRO', $html );
	}

	/**
	 * Informes con PRO y sin curso elegido.
	 */
	public function test_informes_con_pro_sin_curso(): void {
		ob_start();
		( new Admin_Informes() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Elige un curso', $html );
	}

	/**
	 * SCORM sin PRO avisa.
	 */
	public function test_scorm_sin_pro(): void {
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => false );

		ob_start();
		( new Admin_SCORM() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'función PRO', $html );
	}

	/**
	 * SCORM con PRO pinta el formulario.
	 */
	public function test_scorm_con_pro_pinta_formulario(): void {
		ob_start();
		( new Admin_SCORM() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Paquete ZIP', $html );
		$this->assertStringContainsString( 'imsmanifest.xml', $html );
	}

	/**
	 * Encuesta desactivada no pinta nada.
	 */
	public function test_encuesta_desactivada_no_pinta(): void {
		$this->assertSame( '', Survey::render( 5 ) );
	}

	/**
	 * Registro normaliza y escribe.
	 */
	public function test_registro_normaliza_y_escribe(): void {
		$id = Access_Log::registrar(
			'login',
			array(
				'user_id'    => 7,
				'ip'         => '203.0.113.7',
				'user_agent' => 'PHPUnit',
			)
		);

		$this->assertSame( 7, $id );
	}

	/**
	 * Registro con eventos: pinta la tabla.
	 */
	public function test_registro_con_eventos(): void {
		$row = array(
			'id'           => 1,
			'created_utc'  => '2026-11-12 00:00:00',
			'event'        => 'login',
			'user_id'      => 1,
			'identidad_id' => 0,
			'course_id'    => 5,
			'lesson_id'    => 0,
			'ip'           => '203.0.113.7',
			'hash'         => 'abc',
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de base de datos de la prueba.
		$GLOBALS['wpdb'] = new class( $row ) {
			/**
			 * Prefijo de tablas.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';
			/**
			 * Fila que devuelve la consulta.
			 *
			 * @var array<string, mixed>
			 */
			private array $row;

			/**
			 * Constructor.
			 *
			 * @param array<string, mixed> $row Fila.
			 */
			public function __construct( array $row ) {
				$this->row = $row;
			}

			/**
			 * Prepara.
			 *
			 * @param string $query Consulta.
			 * @param mixed  ...$args Argumentos.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				unset( $args );
				return (string) $query;
			}

			/**
			 * Devuelve la fila.
			 *
			 * @param string $query Consulta.
			 * @param string $output Salida.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( $query, $output = 'OBJECT' ) {
				unset( $query, $output );
				return array( $this->row );
			}
		};

		ob_start();
		( new Admin_Registro() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'login', $html );
		$this->assertStringContainsString( '203.0.113.7', $html );
	}

	/**
	 * Informes con curso y sin alumnado.
	 */
	public function test_informes_con_curso_sin_alumnado(): void {
		$_GET['course_id'] = 5;
		ob_start();
		( new Admin_Informes() )->render();
		$html = (string) ob_get_clean();
		unset( $_GET['course_id'] );

		$this->assertStringContainsString( 'Descargar CSV del curso', $html );
		$this->assertStringContainsString( 'no tiene alumnado', $html );
	}

	/**
	 * Encuesta configurada y sin matrícula no pinta formulario.
	 */
	public function test_encuesta_configurada_sin_matricula(): void {
		update_post_meta(
			5,
			Survey::META,
			array(
				'enabled'   => true,
				'preguntas' => array( 'Uno' ),
			)
		);

		$this->assertSame( '', Survey::render( 5 ) );
	}

	/**
	 * Metadatos del paquete y URL del player.
	 */
	public function test_scorm_paquete_y_url(): void {
		update_post_meta(
			5,
			\Convoca\Courses\SCORM::META,
			array(
				'dir'   => 'convoca-courses/scorm/abc',
				'entry' => 'index.html',
			)
		);

		$this->assertSame( 'index.html', \Convoca\Courses\SCORM::paquete( 5 )['entry'] );
		$this->assertStringContainsString( 'convoca_scorm=5', \Convoca\Courses\SCORM::player_url( 5 ) );
	}

	/**
	 * Informes con un alumno: pinta la fila y el enlace PDF.
	 */
	public function test_informes_con_alumno(): void {
		$GLOBALS['convoca_test_post_types']      = array(
			5  => 'convoca_curso',
			11 => 'convoca_matricula',
		);
		$GLOBALS['convoca_test_post_status'][11] = 'publish';
		$GLOBALS['convoca_test_meta'][11]        = array(
			'_convoca_curso_id' => '5',
			'_convoca_estado'   => 'activa',
			'_convoca_user_id'  => '1',
		);
		$GLOBALS['convoca_courses_usuarios'][1]  = (object) array(
			'ID'           => 1,
			'display_name' => 'Alumno de prueba',
			'user_email'   => 'alumno@example.test',
		);
		$_GET['course_id']                       = 5;

		ob_start();
		( new Admin_Informes() )->render();
		$html = (string) ob_get_clean();

		unset( $_GET['course_id'] );

		$this->assertStringContainsString( 'Alumno de prueba', $html );
		$this->assertStringContainsString( 'PDF', $html );
		$this->assertStringContainsString( 'Encuesta de calidad', $html );
	}

	/**
	 * Un curso sin alumnado devuelve null.
	 */
	public function test_informe_sin_alumno(): void {
		$this->assertNull( Reports::student( 5, 7, 0 ) );
	}
}
