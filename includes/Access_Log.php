<?php
/**
 * Registro inalterable de conexiones y actividad del alumnado.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cadena de eventos con HMAC.
 *
 * Cada fila guarda el hash de la anterior: alterar una fila o borrar una intermedia rompe la
 * verificación. La retención deja un ancla con el último hash purgado para que la cadena siga
 * siendo comprobable desde la primera fila conservada.
 */
class Access_Log {

	/**
	 * Tabla sin prefijo.
	 */
	public const TABLE = 'convoca_course_access_log';

	/**
	 * Opción con los días de retención.
	 */
	public const OPTION_RETENTION = 'convoca_courses_access_log_retention';

	/**
	 * Opción con el ancla de la cadena tras una purga.
	 */
	public const OPTION_ANCHOR = 'convoca_courses_access_log_anchor';

	/**
	 * Evento diario de purga.
	 */
	public const CRON = 'convoca_courses_access_log_purge';

	/**
	 * Días de retención por defecto.
	 */
	public const RETENTION_DEFAULT = 365;

	/**
	 * Eventos admitidos.
	 */
	public const EVENTS = array(
		'login',
		'logout',
		'acceso_curso',
		'acceso_leccion',
		'inscripcion',
		'entrega',
		'examen',
		'scorm_launch',
		'scorm_commit',
		'scorm_complete',
	);

	/**
	 * Engancha los eventos y la purga.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_login', array( __CLASS__, 'on_wp_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'on_wp_logout' ) );
		add_action( 'template_redirect', array( __CLASS__, 'on_template' ), 20 );
		add_action( 'convoca_courses_enrolled', array( __CLASS__, 'on_enrolled' ), 10, 3 );
		add_action( 'convoca_courses_exam_submitted', array( __CLASS__, 'on_exam_submitted' ), 10, 4 );
		add_action( 'convoca_courses_task_submitted', array( __CLASS__, 'on_task_submitted' ), 10, 3 );
		add_action( self::CRON, array( __CLASS__, 'purge' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Programa la purga diaria si no lo está.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Quita la purga al desactivar.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON );
		}
	}

	/**
	 * Registra el acceso con usuario de WordPress.
	 *
	 * @param string   $user_login Login.
	 * @param \WP_User $user      Usuario.
	 * @return void
	 */
	public static function on_wp_login( string $user_login, $user = null ): void {
		unset( $user_login );
		$user_id = $user instanceof \WP_User ? (int) $user->ID : 0;

		self::registrar(
			'login',
			array(
				'user_id' => $user_id,
				'data'    => array( 'via' => 'wp' ),
			)
		);
	}

	/**
	 * Registra el cierre de sesión de WordPress.
	 *
	 * @param int $user_id Usuario.
	 * @return void
	 */
	public static function on_wp_logout( $user_id = 0 ): void {
		self::registrar(
			'logout',
			array(
				'user_id' => (int) $user_id,
				'data'    => array( 'via' => 'wp' ),
			)
		);
	}

	/**
	 * Registra la visita a una ficha de curso o lección.
	 *
	 * @return void
	 */
	public static function on_template(): void {
		if ( ! is_singular( array( CPT_Curso::TYPE, CPT_Leccion::TYPE ) ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$tipo      = (string) get_post_type( $post_id );
		$course_id = CPT_Curso::TYPE === $tipo ? $post_id : Hierarchy::course_of_lesson( $post_id );
		$lesson_id = CPT_Leccion::TYPE === $tipo ? $post_id : 0;

		self::registrar(
			CPT_Curso::TYPE === $tipo ? 'acceso_curso' : 'acceso_leccion',
			array(
				'course_id' => $course_id,
				'lesson_id' => $lesson_id,
				'object_id' => $post_id,
			)
		);
	}

	/**
	 * Matrícula creada.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @param int $course_id     Curso.
	 * @param int $user_id       Usuario.
	 * @return void
	 */
	public static function on_enrolled( int $enrollment_id, int $course_id, int $user_id ): void {
		self::registrar(
			'inscripcion',
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
				'object_id' => $enrollment_id,
			)
		);
	}

	/**
	 * Examen entregado.
	 *
	 * @param int                  $attempt_id Intento.
	 * @param int                  $course_id  Curso.
	 * @param int                  $user_id    Usuario.
	 * @param array<string, mixed> $resultado  Resultado.
	 * @return void
	 */
	public static function on_exam_submitted( int $attempt_id, int $course_id, int $user_id, array $resultado = array() ): void {
		self::registrar(
			'examen',
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
				'object_id' => $attempt_id,
				'data'      => array( 'aprobado' => ! empty( $resultado['aprobado'] ) ),
			)
		);
	}

	/**
	 * Tarea entregada.
	 *
	 * @param int    $task_id Tarea.
	 * @param int    $user_id Usuario.
	 * @param string $estado  Estado.
	 * @return void
	 */
	public static function on_task_submitted( int $task_id, int $user_id, string $estado ): void {
		self::registrar(
			'entrega',
			array(
				'user_id'   => $user_id,
				'object_id' => $task_id,
				'data'      => array( 'estado' => $estado ),
			)
		);
	}

	/**
	 * Escribe una fila en la cadena.
	 *
	 * @param string               $event   Evento.
	 * @param array<string, mixed> $contexto Contexto.
	 * @return int Identificador de la fila, o 0.
	 */
	public static function registrar( string $event, array $contexto = array() ): int {
		if ( ! Pro::enabled( 'registro_conexiones' ) || ! in_array( $event, self::EVENTS, true ) ) {
			return 0;
		}

		$fila = self::normalizar( $event, $contexto );
		$lock = self::lock();

		$fila['prev_hash'] = self::ultimo_hash();
		$fila['hash']      = self::hash_fila( $fila, $fila['prev_hash'] );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$ok = $wpdb->insert( self::tabla(), $fila, self::formatos( $fila ) );
		$id = $ok ? (int) $wpdb->insert_id : 0;

		self::unlock( $lock );

		return $id;
	}

	/**
	 * Construye la fila canónica a partir del contexto.
	 *
	 * @param string               $event    Evento.
	 * @param array<string, mixed> $contexto Contexto.
	 * @return array<string, mixed>
	 */
	public static function normalizar( string $event, array $contexto ): array {
		$user_id      = isset( $contexto['user_id'] ) ? absint( $contexto['user_id'] ) : get_current_user_id();
		$identidad_id = isset( $contexto['identidad_id'] ) ? absint( $contexto['identidad_id'] ) : Identidad::actual();
		$ip           = isset( $contexto['ip'] ) ? (string) $contexto['ip'] : sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		$agente       = isset( $contexto['user_agent'] ) ? (string) $contexto['user_agent'] : sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		$data         = isset( $contexto['data'] ) ? $contexto['data'] : array();

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}

		return array(
			'created_utc'  => current_time( 'mysql', true ),
			'event'        => $event,
			'user_id'      => $user_id,
			'identidad_id' => $identidad_id,
			'course_id'    => isset( $contexto['course_id'] ) ? absint( $contexto['course_id'] ) : 0,
			'lesson_id'    => isset( $contexto['lesson_id'] ) ? absint( $contexto['lesson_id'] ) : 0,
			'object_id'    => isset( $contexto['object_id'] ) ? absint( $contexto['object_id'] ) : 0,
			'ip'           => $ip,
			'user_agent'   => substr( sanitize_text_field( $agente ), 0, 255 ),
			'session_id'   => self::session_id(),
			'duration'     => isset( $contexto['duration'] ) ? absint( $contexto['duration'] ) : 0,
			'data'         => (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'prev_hash'    => '',
			'hash'         => '',
		);
	}

	/**
	 * Hash de una fila, encadenado con la anterior.
	 *
	 * @param array<string, mixed> $fila      Fila.
	 * @param string               $prev_hash Hash anterior.
	 * @return string
	 */
	public static function hash_fila( array $fila, string $prev_hash ): string {
		$campos = array(
			(string) ( $fila['created_utc'] ?? '' ),
			(string) ( $fila['event'] ?? '' ),
			(string) (int) ( $fila['user_id'] ?? 0 ),
			(string) (int) ( $fila['identidad_id'] ?? 0 ),
			(string) (int) ( $fila['course_id'] ?? 0 ),
			(string) (int) ( $fila['lesson_id'] ?? 0 ),
			(string) (int) ( $fila['object_id'] ?? 0 ),
			(string) ( $fila['ip'] ?? '' ),
			(string) ( $fila['user_agent'] ?? '' ),
			(string) ( $fila['session_id'] ?? '' ),
			(string) (int) ( $fila['duration'] ?? 0 ),
			(string) ( $fila['data'] ?? '' ),
		);

		return hash_hmac( 'sha256', implode( '|', $campos ) . '|' . $prev_hash, self::secreto() );
	}

	/**
	 * Verifica una lista de filas ya ordenada por id.
	 *
	 * @param array<int, array<string, mixed>> $filas       Filas.
	 * @param string                           $anchor_hash Hash del último registro purgado.
	 * @return bool
	 */
	public static function verify_rows( array $filas, string $anchor_hash = '' ): bool {
		$prev = $anchor_hash;

		foreach ( $filas as $fila ) {
			if ( (string) ( $fila['prev_hash'] ?? '' ) !== $prev ) {
				return false;
			}

			if ( ! hash_equals( self::hash_fila( $fila, $prev ), (string) ( $fila['hash'] ?? '' ) ) ) {
				return false;
			}

			$prev = (string) $fila['hash'];
		}

		return true;
	}

	/**
	 * Verifica la cadena conservada en la base de datos.
	 *
	 * @param int $limit Máximo de filas.
	 * @return bool
	 */
	public static function verify( int $limit = 5000 ): bool {
		global $wpdb;

		$tabla = self::tabla();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$filas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY id ASC LIMIT %d", $limit ), 'ARRAY_A' );

		if ( ! is_array( $filas ) ) {
			return false;
		}

		$anchor = get_option( self::OPTION_ANCHOR, array() );
		$hash   = is_array( $anchor ) && isset( $anchor['hash'] ) ? (string) $anchor['hash'] : '';

		return self::verify_rows( $filas, $hash );
	}

	/**
	 * Resumen de actividad de una persona en un curso.
	 *
	 * @param int $course_id    Curso.
	 * @param int $user_id      Usuario.
	 * @param int $identidad_id Identidad.
	 * @return array{total:int, ultima:string, duracion:int, eventos:array<string,int>}
	 */
	public static function summary_for( int $course_id, int $user_id, int $identidad_id = 0 ): array {
		global $wpdb;

		$tabla = self::tabla();
		$where = $identidad_id > 0 ? $wpdb->prepare( 'identidad_id = %d', $identidad_id ) : $wpdb->prepare( 'user_id = %d', $user_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$filas = $wpdb->get_results( $wpdb->prepare( "SELECT event, created_utc, duration FROM {$tabla} WHERE course_id = %d AND {$where} ORDER BY id ASC", $course_id ), 'ARRAY_A' );

		return self::resumen_filas( is_array( $filas ) ? $filas : array() );
	}

	/**
	 * Agrega filas de actividad.
	 *
	 * @param array<int, array<string, mixed>> $filas Filas.
	 * @return array{total:int, ultima:string, duracion:int, eventos:array<string,int>}
	 */
	public static function resumen_filas( array $filas ): array {
		$salida = array(
			'total'    => 0,
			'ultima'   => '',
			'duracion' => 0,
			'eventos'  => array(),
		);

		foreach ( $filas as $fila ) {
			++$salida['total'];
			$evento                       = (string) ( $fila['event'] ?? '' );
			$salida['eventos'][ $evento ] = (int) ( $salida['eventos'][ $evento ] ?? 0 ) + 1;
			$salida['duracion']          += (int) ( $fila['duration'] ?? 0 );
			$fecha                        = (string) ( $fila['created_utc'] ?? '' );
			if ( '' !== $fecha && $fecha > $salida['ultima'] ) {
				$salida['ultima'] = $fecha;
			}
		}

		return $salida;
	}

	/**
	 * Purga lo que supera la retención y deja un ancla.
	 *
	 * @return int Filas borradas.
	 */
	public static function purge(): int {
		global $wpdb;

		$dias   = max( 30, min( 3650, (int) get_option( self::OPTION_RETENTION, self::RETENTION_DEFAULT ) ) );
		$limite = gmdate( 'Y-m-d H:i:s', time() - ( $dias * DAY_IN_SECONDS ) );
		$tabla  = self::tabla();

		// El ancla se actualiza con el último hash que se va a borrar.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia.
		$ultimo = $wpdb->get_row( $wpdb->prepare( "SELECT id, hash FROM {$tabla} WHERE created_utc < %s ORDER BY id DESC LIMIT 1", $limite ), 'ARRAY_A' );

		if ( is_array( $ultimo ) ) {
			update_option(
				self::OPTION_ANCHOR,
				array(
					'id'   => (int) $ultimo['id'],
					'hash' => (string) $ultimo['hash'],
				),
				false
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia.
		$borradas = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tabla} WHERE created_utc < %s", $limite ) );

		return max( 0, (int) $borradas );
	}

	/**
	 * Último hash de la cadena.
	 *
	 * @return string
	 */
	private static function ultimo_hash(): string {
		global $wpdb;

		$tabla = self::tabla();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$hash = $wpdb->get_var( "SELECT hash FROM {$tabla} ORDER BY id DESC LIMIT 1" );

		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * Bloqueo para que dos peticiones no lean el mismo hash anterior.
	 *
	 * @return string|false
	 */
	private static function lock() {
		global $wpdb;

		if ( ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cerrojo de la tabla propia.
		return $wpdb->get_var( "SELECT GET_LOCK('convoca_courses_access_log', 5)" );
	}

	/**
	 * Suelta el bloqueo.
	 *
	 * @param mixed $lock Resultado de `lock()`.
	 * @return void
	 */
	private static function unlock( $lock ): void {
		global $wpdb;

		if ( false === $lock || null === $lock ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cerrojo de la tabla propia.
		$wpdb->get_var( "SELECT RELEASE_LOCK('convoca_courses_access_log')" );
	}

	/**
	 * Identificador de sesión no reversible.
	 *
	 * @return string
	 */
	private static function session_id(): string {
		$wp = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		if ( '' === $wp ) {
			$wp = sanitize_text_field( wp_unslash( (string) ( $_COOKIE[ Identidad::COOKIE ] ?? '' ) ) );
		}

		return '' === $wp ? '' : substr( hash( 'sha256', $wp ), 0, 64 );
	}

	/**
	 * Secreto de la cadena.
	 *
	 * @return string
	 */
	private static function secreto(): string {
		return hash_hmac( 'sha256', 'convoca_courses_access_log', Profesorado::secreto_publico() );
	}

	/**
	 * Tipos de `$wpdb->insert`.
	 *
	 * @param array<string, mixed> $fila Fila.
	 * @return array<int, string>
	 */
	private static function formatos( array $fila ): array {
		$formatos = array();

		foreach ( $fila as $campo => $valor ) {
			$formatos[] = in_array( $campo, array( 'user_id', 'identidad_id', 'course_id', 'lesson_id', 'object_id', 'duration' ), true ) ? '%d' : '%s';
		}

		return $formatos;
	}

	/**
	 * Nombre de la tabla con prefijo.
	 *
	 * @return string
	 */
	private static function tabla(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}
}
