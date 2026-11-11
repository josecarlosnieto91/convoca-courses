<?php
/**
 * Rutas de datos PRO: purga, resumen, encuesta y subida SCORM.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Access_Log;
use Convoca\Courses\Admin_SCORM;
use Convoca\Courses\Survey;
use PHPUnit\Framework\TestCase;

/**
 * Doble de base de datos con las operaciones de las tablas PRO.
 */
final class ProDatosTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array<string, mixed>
	 */
	private array $previo = array();

	/**
	 * Doble de base de datos.
	 *
	 * @var object
	 */
	private object $db;

	/**
	 * Prepara el escenario.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'wpdb'    => $GLOBALS['wpdb'] ?? null,
			'caps'    => $GLOBALS['convoca_test_caps'] ?? null,
			'filtros' => $GLOBALS['convoca_courses_filtros'] ?? null,
		);

		$this->db = new class() {
			/**
			 * Prefijo.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';
			/**
			 * Entero.
			 *
			 * @var int
			 */
			public $insert_id = 1;
			/**
			 * Filas.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public array $filas = array();
			/**
			 * Entero.
			 *
			 * @var int
			 */
			public int $borradas = 0;

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
			 * Fila de prueba.
			 *
			 * @param string $query Consulta.
			 * @param string $output Salida.
			 * @return array<string, mixed>
			 */
			public function get_row( $query, $output = 'OBJECT' ) {
				unset( $query, $output );
				return array(
					'id'            => 1,
					'hash'          => 'hash-viejo',
					'answers'       => '[5,4]',
					'comentario'    => 'Bien',
					'lesson_status' => 'completed',
					'score_raw'     => 90,
					'session_time'  => '00:10:00',
				);
			}

			/**
			 * Filas de prueba.
			 *
			 * @param string $query Consulta.
			 * @param string $output Salida.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( $query, $output = 'OBJECT' ) {
				unset( $query, $output );
				return $this->filas;
			}

			/**
			 * Ejecuta.
			 *
			 * @param string $query Consulta.
			 * @return int
			 */
			public function query( $query ) {
				unset( $query );
				return $this->borradas;
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
				unset( $tabla, $formato );
				$this->filas[] = $datos;
				return 1;
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- doble de la prueba.
		$GLOBALS['wpdb']                    = $this->db;
		$GLOBALS['convoca_test_caps']       = array( 'convoca_manage_courses' => true );
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => true );
	}

	/**
	 * Restaura.
	 */
	protected function tearDown(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restaura el doble.
		$GLOBALS['wpdb']                    = $this->previo['wpdb'];
		$GLOBALS['convoca_test_caps']       = $this->previo['caps'];
		$GLOBALS['convoca_courses_filtros'] = $this->previo['filtros'];
	}

	/**
	 * La purga borra y deja ancla.
	 */
	public function test_purga_devuelve_borradas(): void {
		$this->db->borradas = 4;
		$n                  = Access_Log::purge();
		$this->assertSame( 4, $n );
		$this->assertSame( 'hash-viejo', get_option( Access_Log::OPTION_ANCHOR )['hash'] );
	}

	/**
	 * El resumen lee los eventos.
	 */
	public function test_resumen_de_la_base(): void {
		$this->db->filas = array(
			array(
				'event'       => 'login',
				'created_utc' => '2026-11-12 10:00:00',
				'duration'    => 0,
			),
			array(
				'event'       => 'acceso_curso',
				'created_utc' => '2026-11-12 10:01:00',
				'duration'    => 60,
			),
		);

		$resumen = Access_Log::summary_for( 5, 1, 0 );
		$this->assertSame( 2, $resumen['total'] );
		$this->assertSame( 60, $resumen['duracion'] );
	}

	/**
	 * La encuesta guarda, lee y agrega.
	 */
	public function test_encuesta_guarda_lee_y_agrega(): void {
		Survey::guardar( 5, array( 5, 4 ), 'Bien' );
		$this->assertCount( 1, $this->db->filas );

		$this->db->filas = array(
			array(
				'answers'    => '[5,4]',
				'comentario' => 'Bien',
			),
		);
		$resultados      = Survey::results( 5 );
		$this->assertSame( 1, $resultados['total'] );
		$this->assertSame( 5.0, $resultados['medias'][0] );
	}

	/**
	 * La respuesta de encuesta se decodifica.
	 */
	public function test_respuesta_de_encuesta(): void {
		$respuesta = Survey::respuesta( 5, 1, 0 );
		$this->assertIsArray( $respuesta );
		$this->assertSame( 5, $respuesta['answers'][0] );
	}

	/**
	 * La verificación lee las filas de la base.
	 */
	public function test_verify_lee_las_filas(): void {
		$this->db->filas = array(
			array(
				'created_utc'  => '2026-11-12 00:00:00',
				'event'        => 'login',
				'user_id'      => 1,
				'identidad_id' => 0,
				'course_id'    => 0,
				'lesson_id'    => 0,
				'object_id'    => 0,
				'ip'           => '',
				'user_agent'   => '',
				'session_id'   => '',
				'duration'     => 0,
				'data'         => '{}',
				'prev_hash'    => '',
				'hash'         => 'no-coincide',
			),
		);

		$this->assertFalse( Access_Log::verify() );
	}

	/**
	 * La subida sin permisos corta.
	 */
	public function test_subida_scorm_sin_permisos(): void {
		$GLOBALS['convoca_courses_filtros'] = array( 'convoca_courses_has_pro' => false );

		$this->expectException( \RuntimeException::class );
		( new Admin_SCORM() )->upload();
	}

	/**
	 * La subida sin fichero redirige.
	 */
	public function test_subida_scorm_sin_fichero(): void {
		$_POST['_convoca_nonce'] = 'nonce';

		$this->expectException( \Convoca_Courses_Redirect::class );
		( new Admin_SCORM() )->upload();
	}
}
