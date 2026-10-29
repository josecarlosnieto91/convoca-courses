<?php
/**
 * Pruebas del esquema de la base de datos.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Installer;
use PHPUnit\Framework\TestCase;

/**
 * El esquema se construye sin tocar la base de datos y se comprueba aquí: qué tablas, qué columnas y
 * qué índices. La ejecución real contra MySQL se mide aparte, contra un WordPress de verdad.
 *
 * Las claves de los arrays van sin acentos: la alineación de PHPCS cuenta bytes y caracteres de forma
 * distinta según la versión de PHP y deja de ser reproducible entre el runner y local.
 */
class InstallerSchemaTest extends TestCase {

	/**
	 * Prefijo de prueba.
	 */
	private const PREFIX = 'wp_';

	/**
	 * Colación de prueba.
	 */
	private const COLLATE = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

	/**
	 * Devuelve el esquema de prueba.
	 *
	 * @return array<string, string>
	 */
	private function esquema(): array {
		return Installer::schema( self::PREFIX, self::COLLATE );
	}

	/**
	 * Están las seis tablas, con su nombre completo y con el prefijo aplicado.
	 */
	public function test_estan_las_seis_tablas(): void {
		$esperadas = array(
			Installer::PROGRESS_TABLE,
			Installer::IDENTIDADES_TABLE,
			Installer::QUESTIONS_TABLE,
			Installer::ATTEMPTS_TABLE,
			Installer::ANSWERS_TABLE,
			Installer::SUBMISSIONS_TABLE,
		);

		$sql = $this->esquema();

		$this->assertSame( $esperadas, array_keys( $sql ), 'Faltan tablas o sobra alguna.' );

		foreach ( $sql as $tabla => $sentencia ) {
			$this->assertStringContainsString( 'CREATE TABLE ' . self::PREFIX . $tabla . ' (', $sentencia );
			$this->assertStringContainsString( self::COLLATE, $sentencia );
		}
	}

	/**
	 * `dbDelta` exige la clave primaria con dos espacios y no admite `IF NOT EXISTS`: si alguien lo
	 * "arregla", la tabla deja de actualizarse y el fallo aparece meses después.
	 */
	public function test_formato_que_entiende_dbdelta(): void {
		foreach ( $this->esquema() as $tabla => $sentencia ) {
			$this->assertStringContainsString( 'PRIMARY KEY  (id)', $sentencia, $tabla . ': la clave primaria lleva dos espacios.' );
			$this->assertStringNotContainsString( 'IF NOT EXISTS', $sentencia, $tabla . ': dbDelta no lo admite.' );
			$this->assertStringContainsString( 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sentencia, $tabla . ': falta el id.' );
		}
	}

	/**
	 * Columnas del banco de preguntas: lo que necesita el corrector.
	 */
	public function test_columnas_del_banco_de_preguntas(): void {
		$sql = $this->esquema()[ Installer::QUESTIONS_TABLE ];

		foreach ( array( 'course_id', 'exam_id', 'tipo', 'enunciado', 'opciones', 'correctas', 'puntos', 'explicacion', 'orden' ) as $columna ) {
			$this->assertStringContainsString( $columna . ' ', $sql, 'Falta la columna ' . $columna . '.' );
		}

		$this->assertStringContainsString( 'KEY course_exam (course_id,exam_id)', $sql );
	}

	/**
	 * Los intentos llevan un índice único que impide dos intentos con el mismo número: es la carrera
	 * de dos envíos simultáneos, que una prueba de interfaz no ve.
	 */
	public function test_intentos_con_indice_unico_por_numero(): void {
		$sql = $this->esquema()[ Installer::ATTEMPTS_TABLE ];

		$this->assertStringContainsString( 'UNIQUE KEY intento (exam_id,user_id,intento)', $sql );
		$this->assertStringContainsString( 'KEY course_user (course_id,user_id)', $sql );

		foreach ( array( 'preguntas', 'iniciado_en', 'terminado_en', 'puntuacion', 'aprobado', 'estado' ) as $columna ) {
			$this->assertStringContainsString( $columna . ' ', $sql, 'Falta la columna ' . $columna . '.' );
		}
	}

	/**
	 * Una respuesta por pregunta y intento.
	 */
	public function test_respuestas_unicas_por_pregunta_e_intento(): void {
		$sql = $this->esquema()[ Installer::ANSWERS_TABLE ];

		$this->assertStringContainsString( 'UNIQUE KEY intento_pregunta (attempt_id,question_id)', $sql );

		foreach ( array( 'respuesta', 'acertada', 'puntos_obtenidos', 'respondida_en' ) as $columna ) {
			$this->assertStringContainsString( $columna . ' ', $sql, 'Falta la columna ' . $columna . '.' );
		}
	}

	/**
	 * Una entrega por tarea y alumno, con su nota y su comentario.
	 */
	public function test_entregas_unicas_por_tarea_y_alumno(): void {
		$sql = $this->esquema()[ Installer::SUBMISSIONS_TABLE ];

		$this->assertStringContainsString( 'UNIQUE KEY tarea_alumno (task_id,user_id)', $sql );

		foreach ( array( 'contenido', 'adjunto_id', 'entregada_en', 'estado', 'nota', 'comentario', 'corregida_por', 'corregida_en' ) as $columna ) {
			$this->assertStringContainsString( $columna . ' ', $sql, 'Falta la columna ' . $columna . '.' );
		}
	}

	/**
	 * La tabla de progreso no cambia: la migración no puede tocar los datos que ya existen.
	 */
	public function test_la_tabla_de_progreso_se_conserva(): void {
		$sql = $this->esquema()[ Installer::PROGRESS_TABLE ];

		$this->assertStringContainsString( 'UNIQUE KEY user_lesson (user_id,lesson_id)', $sql );
		$this->assertStringContainsString( 'completed_at', $sql );
	}

	/**
	 * Casos de la comprobación de versión de esquema.
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function upgrade_cases(): array {
		return array(
			'instalacion nueva' => array( '', '2.0.0', true ),
			'version anterior'  => array( '1.0.0', '2.0.0', true ),
			'ya actualizada'    => array( '2.0.0', '2.0.0', false ),
			'version mas nueva' => array( '3.0.0', '2.0.0', true ),
		);
	}

	/**
	 * La migración se aplica cuando la versión guardada no es la del código.
	 *
	 * @dataProvider upgrade_cases
	 *
	 * @param string $installed Versión guardada.
	 * @param string $code      Versión del código.
	 * @param bool   $esperado  Si debe migrar.
	 */
	public function test_needs_upgrade( string $installed, string $code, bool $esperado ): void {
		$this->assertSame( $esperado, Installer::needs_upgrade( $installed, $code ) );
	}
}
