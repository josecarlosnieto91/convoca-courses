<?php
/**
 * Ciclo de vida: tablas propias, capabilities y reescrituras.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instalador del módulo.
 */
class Installer {

	/**
	 * Opción con la versión de esquema ya aplicada.
	 */
	public const DB_OPTION = 'convoca_courses_db_version';

	/**
	 * Opción que decide si al desinstalar se conservan los datos.
	 */
	public const KEEP_DATA_OPTION = 'convoca_courses_keep_data';

	/**
	 * Opción que marca que ya se decidió el desbloqueo secuencial de los cursos antiguos.
	 */
	public const SEQ_MIGRATED_OPTION = 'convoca_courses_seq_migrated';

	/**
	 * Nombre de la tabla de progreso (sin prefijo).
	 */
	public const PROGRESS_TABLE = 'convoca_course_progress';

	/**
	 * Banco de preguntas (sin prefijo).
	 */
	public const QUESTIONS_TABLE = 'convoca_course_questions';

	/**
	 * Intentos de examen (sin prefijo).
	 */
	public const ATTEMPTS_TABLE = 'convoca_course_attempts';

	/**
	 * Respuestas de un intento (sin prefijo).
	 */
	public const ANSWERS_TABLE = 'convoca_course_answers';

	/**
	 * Entregas de tareas (sin prefijo).
	 */
	public const SUBMISSIONS_TABLE = 'convoca_course_submissions';

	/**
	 * Activación: crea tablas, capabilities y reescribe reglas.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::add_capabilities();

		// Los tipos de contenido tienen que existir ANTES de reescribir las reglas: si no, el flush
		// de la activación se guarda sin las URLs de /cursos/ y el archivo del curso da 404 hasta que
		// alguien vuelve a guardar los enlaces permanentes.
		Plugin::instance()->register_types();

		Verification::register_rewrite();
		flush_rewrite_rules();

		update_option( self::DB_OPTION, CONVOCA_COURSES_DB_VERSION, false );
	}

	/**
	 * Desactivación: solo se limpian las reglas de reescritura. Los datos se quedan.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Definición del esquema, sin tocar la base de datos.
	 *
	 * Es el único sitio donde vive el SQL: así se puede comprobar en una prueba unitaria qué tablas,
	 * columnas e índices se esperan, sin necesitar WordPress ni MySQL.
	 *
	 * `dbDelta` es quisquilloso: quiere dos espacios tras `PRIMARY KEY`, una definición por línea y
	 * nada de `IF NOT EXISTS`. Cualquier cambio aquí se mide contra una base de datos real.
	 *
	 * @param string $prefix  Prefijo de las tablas del sitio.
	 * @param string $collate Juego de caracteres y colación (de `$wpdb->get_charset_collate()`).
	 * @return array<string, string> Sentencias `CREATE TABLE` indexadas por nombre de tabla sin prefijo.
	 */
	public static function schema( string $prefix, string $collate ): array {
		$sql = array();

		$sql[ self::PROGRESS_TABLE ] = "CREATE TABLE {$prefix}" . self::PROGRESS_TABLE . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			completed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id,lesson_id),
			KEY course_user (course_id,user_id)
		) {$collate};";

		$sql[ self::QUESTIONS_TABLE ] = "CREATE TABLE {$prefix}" . self::QUESTIONS_TABLE . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			exam_id bigint(20) unsigned NOT NULL DEFAULT 0,
			tipo varchar(20) NOT NULL DEFAULT 'unica',
			enunciado longtext NOT NULL,
			opciones longtext NOT NULL,
			correctas longtext NOT NULL,
			puntos decimal(6,2) NOT NULL DEFAULT 1.00,
			explicacion longtext NOT NULL,
			orden int(11) NOT NULL DEFAULT 0,
			creado_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY course_exam (course_id,exam_id),
			KEY exam (exam_id)
		) {$collate};";

		$sql[ self::ATTEMPTS_TABLE ] = "CREATE TABLE {$prefix}" . self::ATTEMPTS_TABLE . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			exam_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			enrollment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			intento int(11) unsigned NOT NULL DEFAULT 1,
			preguntas longtext NOT NULL,
			iniciado_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			terminado_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			puntuacion decimal(5,2) NOT NULL DEFAULT 0.00,
			aprobado tinyint(1) NOT NULL DEFAULT 0,
			estado varchar(20) NOT NULL DEFAULT 'abierto',
			PRIMARY KEY  (id),
			UNIQUE KEY intento (exam_id,user_id,intento),
			KEY course_user (course_id,user_id)
		) {$collate};";

		$sql[ self::ANSWERS_TABLE ] = "CREATE TABLE {$prefix}" . self::ANSWERS_TABLE . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_id bigint(20) unsigned NOT NULL,
			question_id bigint(20) unsigned NOT NULL,
			respuesta longtext NOT NULL,
			acertada tinyint(1) NOT NULL DEFAULT 0,
			puntos_obtenidos decimal(6,2) NOT NULL DEFAULT 0.00,
			respondida_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY intento_pregunta (attempt_id,question_id)
		) {$collate};";

		$sql[ self::SUBMISSIONS_TABLE ] = "CREATE TABLE {$prefix}" . self::SUBMISSIONS_TABLE . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			enrollment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			contenido longtext NOT NULL,
			adjunto_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entregada_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			estado varchar(20) NOT NULL DEFAULT 'entregada',
			nota decimal(5,2) NOT NULL DEFAULT 0.00,
			comentario longtext NOT NULL,
			corregida_por bigint(20) unsigned NOT NULL DEFAULT 0,
			corregida_en datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY tarea_alumno (task_id,user_id),
			KEY course_user (course_id,user_id)
		) {$collate};";

		return $sql;
	}

	/**
	 * Crea o actualiza las tablas propias con dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::schema( $wpdb->prefix, $wpdb->get_charset_collate() ) as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * ¿Hay que aplicar el esquema?
	 *
	 * Función pura para poder probarla sin WordPress: la migración se dispara cuando la versión
	 * guardada no es la del código, y también cuando no hay versión guardada (instalación nueva).
	 *
	 * @param string $installed Versión de esquema guardada en opciones ('' si no hay).
	 * @param string $code      Versión de esquema que espera el código.
	 * @return bool
	 */
	public static function needs_upgrade( string $installed, string $code ): bool {
		return $installed !== $code;
	}

	/**
	 * Aplica el esquema si la versión guardada no coincide con la del código.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_OPTION, '' );

		if ( ! self::needs_upgrade( $installed, CONVOCA_COURSES_DB_VERSION ) ) {
			return;
		}

		self::create_tables();
		self::migrate_sequential_default();

		// Las capabilities también se refrescan al actualizar: una instalación que viene de la 0.2.3
		// no tiene `convoca_grade_courses` (la añadió la 0.3.0) y sin esto no hay forma de dar tutor
		// sin tocar la base a mano. Medido en la demo: el administrador no tenía la capability del
		// módulo y el panel funcionaba solo por el respaldo de `manage_options`.
		self::add_capabilities();

		update_option( self::DB_OPTION, CONVOCA_COURSES_DB_VERSION, false );
	}

	/**
	 * Deja el desbloqueo secuencial como estaba en los cursos anteriores a la 0.3.0.
	 *
	 * Desde la 0.3.0 un curso nuevo nace secuencial (`CPT_Curso::is_sequential()` devuelve `true` por
	 * defecto). Aplicar eso a los cursos que ya están en marcha encerraría al alumnado a mitad de
	 * temario, así que aquí se les escribe un `0` explícito la primera vez y se marca la opción: a
	 * partir de ahí, cambiarlo es una decisión de quien administra, no un efecto de la actualización.
	 *
	 * @return void
	 */
	public static function migrate_sequential_default(): void {
		if ( '1' === (string) get_option( self::SEQ_MIGRATED_OPTION, '' ) ) {
			return;
		}

		$clave  = CPT_Base::META_PREFIX . 'desbloqueo_secuencial';
		$cursos = get_posts(
			array(
				'post_type'   => CPT_Curso::TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- se ejecuta una sola vez, en la migración.
					array(
						'key'     => $clave,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $cursos as $curso_id ) {
			update_post_meta( (int) $curso_id, $clave, '0' );
		}

		update_option( self::SEQ_MIGRATED_OPTION, '1', false );
	}

	/**
	 * Capabilities propias. Se dan al rol administrador y se pueden repartir con un plugin de roles.
	 *
	 * @return void
	 */
	public static function add_capabilities(): void {
		$caps = array(
			'convoca_manage_courses',
			'convoca_enroll_courses',
			'convoca_view_certificates',
			'convoca_grade_courses',
		);

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Retira las capabilities propias.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		$caps = array(
			'convoca_manage_courses',
			'convoca_enroll_courses',
			'convoca_view_certificates',
			'convoca_grade_courses',
		);

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Nombre completo de la tabla de progreso.
	 *
	 * @return string
	 */
	public static function progress_table(): string {
		return self::table( self::PROGRESS_TABLE );
	}

	/**
	 * Nombre completo de una tabla del módulo.
	 *
	 * @param string $name Nombre sin prefijo.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}
}
