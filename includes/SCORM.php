<?php
/**
 * Paquetes SCORM 1.2 y SCORM 2004 4ª edición.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carga, lanza y guarda el estado de un paquete SCORM.
 *
 * El paquete se sirve desde uploads y el player vive en una página del propio sitio para que el
 * contenido encuentre `window.parent.API` en el mismo origen. No hay iframe de terceros.
 */
class SCORM {

	/**
	 * Meta de la lección con el paquete.
	 */
	public const META = '_convoca_scorm_paquete';

	/**
	 * Carpeta relativa dentro de uploads.
	 */
	public const DIR = 'convoca-courses/scorm';

	/**
	 * Acción del guardado de estado.
	 */
	public const AJAX_STATE = 'convoca_scorm_state';

	/**
	 * Acción de subida.
	 */
	public const ACTION_UPLOAD = 'convoca_courses_scorm_upload';

	/**
	 * Extensiones que no pueden viajar en un paquete.
	 */
	public const BLOQUEADAS = array( 'php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'exe', 'dll', 'so', 'htaccess' );

	/**
	 * Engancha el player y el guardado.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 1 );
		add_action( 'wp_ajax_' . self::AJAX_STATE, array( __CLASS__, 'handle_state' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_STATE, array( __CLASS__, 'handle_state' ) );
	}

	/**
	 * Sirve el player si la petición lo pide.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del identificador; el acceso se comprueba justo después.
		$lesson_id = isset( $_GET['convoca_scorm'] ) ? absint( wp_unslash( (string) $_GET['convoca_scorm'] ) ) : 0;

		if ( $lesson_id <= 0 ) {
			return;
		}

		if ( ! Pro::enabled( 'scorm' ) || ! self::can_launch( $lesson_id ) ) {
			wp_die( esc_html__( 'No tienes acceso a este paquete SCORM.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		$meta = self::paquete( $lesson_id );
		if ( null === $meta ) {
			wp_die( esc_html__( 'Esta lección no tiene un paquete SCORM.', 'convoca-courses' ), '', array( 'response' => 404 ) );
		}

		self::render_player( $lesson_id, $meta );
		exit;
	}

	/**
	 * ¿Puede esta persona lanzar el paquete de la lección?
	 *
	 * @param int $lesson_id Lección.
	 * @return bool
	 */
	public static function can_launch( int $lesson_id ): bool {
		if ( $lesson_id <= 0 || ! Hierarchy::is_lesson( $lesson_id ) ) {
			return false;
		}

		return Access::can_view_lesson( get_current_user_id(), $lesson_id );
	}

	/**
	 * Metadatos del paquete de una lección.
	 *
	 * @param int $lesson_id Lección.
	 * @return array<string, mixed>|null
	 */
	public static function paquete( int $lesson_id ): ?array {
		$meta = get_post_meta( $lesson_id, self::META, true );

		if ( ! is_array( $meta ) || empty( $meta['entry'] ) || empty( $meta['dir'] ) ) {
			return null;
		}

		return $meta;
	}

	/**
	 * URL del player.
	 *
	 * @param int $lesson_id Lección.
	 * @return string
	 */
	public static function player_url( int $lesson_id ): string {
		return add_query_arg( 'convoca_scorm', $lesson_id, home_url( '/' ) );
	}

	/**
	 * Parsea un `imsmanifest.xml`.
	 *
	 * Función pura: no toca WordPress ni el sistema de archivos.
	 *
	 * @param string $xml Contenido XML.
	 * @return array{version:string, title:string, entry:string}|null
	 */
	public static function manifest_data( string $xml ): ?array {
		if ( '' === trim( $xml ) ) {
			return null;
		}

		$anterior = libxml_use_internal_errors( true );
		$manifest = simplexml_load_string( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors( $anterior );

		if ( false === $manifest ) {
			return null;
		}

		$schemaversion = (string) ( $manifest['schemaversion'] ?? '' );
		$version       = false !== stripos( $schemaversion, '2004' ) ? '2004' : '1.2';
		$title         = '';

		if ( isset( $manifest->organizations->organization->title ) ) {
			$title = trim( (string) $manifest->organizations->organization->title );
		}

		$entry = '';
		foreach ( $manifest->resources->resource as $resource ) {
			$href = trim( (string) $resource['href'] );
			if ( '' !== $href ) {
				$entry = $href;
				break;
			}
		}

		if ( '' === $entry ) {
			return null;
		}

		return array(
			'version' => $version,
			'title'   => $title,
			'entry'   => ltrim( str_replace( '\\', '/', $entry ), '/' ),
		);
	}

	/**
	 * Comprueba los nombres de un ZIP antes de extraerlo.
	 *
	 * @param array<int, string> $nombres Nombres de entrada.
	 * @return true|\WP_Error
	 */
	public static function validar_entradas( array $nombres ) {
		if ( array() === $nombres ) {
			return new \WP_Error( 'scorm_vacio', __( 'El paquete está vacío.', 'convoca-courses' ) );
		}

		$manifiesto = false;

		foreach ( $nombres as $nombre ) {
			$nombre = str_replace( '\\', '/', (string) $nombre );

			if ( false !== strpos( $nombre, "\0" ) || preg_match( '#(^|/)\.\.(/|$)#', $nombre ) ) {
				return new \WP_Error( 'scorm_ruta', __( 'El paquete contiene una ruta no permitida.', 'convoca-courses' ) );
			}

			$extension = strtolower( pathinfo( $nombre, PATHINFO_EXTENSION ) );
			if ( in_array( $extension, self::BLOQUEADAS, true ) ) {
				return new \WP_Error(
					'scorm_ejecutable',
					/* translators: %s: nombre del fichero. */
					sprintf( __( 'El paquete contiene un fichero ejecutable: %s', 'convoca-courses' ), $nombre )
				);
			}

			if ( 'imsmanifest.xml' === strtolower( basename( $nombre ) ) ) {
				$manifiesto = true;
			}
		}

		if ( ! $manifiesto ) {
			return new \WP_Error( 'scorm_manifiesto', __( 'El paquete no contiene imsmanifest.xml.', 'convoca-courses' ) );
		}

		return true;
	}

	/**
	 * Instala un ZIP ya subido en una lección.
	 *
	 * @param string $zip_path Ruta del ZIP.
	 * @param int    $lesson_id Lección.
	 * @return true|\WP_Error
	 */
	public static function instalar( string $zip_path, int $lesson_id ) {
		if ( $lesson_id <= 0 || ! Hierarchy::is_lesson( $lesson_id ) ) {
			return new \WP_Error( 'scorm_leccion', __( 'La lección no existe.', 'convoca-courses' ) );
		}

		if ( ! is_readable( $zip_path ) ) {
			return new \WP_Error( 'scorm_zip', __( 'No se ha podido leer el ZIP.', 'convoca-courses' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new \WP_Error( 'scorm_zip', __( 'El fichero no es un ZIP válido.', 'convoca-courses' ) );
		}

		$nombres   = array();
		$num_files = (int) $zip->numFiles; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- propiedad de ZipArchive.
		for ( $i = 0; $i < $num_files; $i++ ) {
			$nombres[] = (string) $zip->getNameIndex( $i );
		}
		$zip->close();

		$validacion = self::validar_entradas( $nombres );
		if ( is_wp_error( $validacion ) ) {
			return $validacion;
		}

		$subida = wp_upload_dir();
		if ( ! empty( $subida['error'] ) ) {
			return new \WP_Error( 'scorm_uploads', (string) $subida['error'] );
		}

		$hash = substr( hash_file( 'sha256', $zip_path ), 0, 24 );
		$rel  = self::DIR . '/' . $hash;
		$dir  = trailingslashit( $subida['basedir'] ) . $rel;

		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'scorm_directorio', __( 'No se ha podido crear el directorio del paquete.', 'convoca-courses' ) );
		}

		// Extracción directa con ZipArchive: `unzip_file()` exige WP_Filesystem y en un sitio con
		// credenciales de FTP devuelve `fs_unavailable` aunque el ZIP sea válido.
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new \WP_Error( 'scorm_zip', __( 'El fichero no es un ZIP válido.', 'convoca-courses' ) );
		}

		$extraido = $zip->extractTo( $dir );
		$zip->close();

		if ( ! $extraido ) {
			return new \WP_Error( 'scorm_extraer', __( 'No se ha podido extraer el paquete.', 'convoca-courses' ) );
		}

		$manifiesto = self::buscar_manifiesto( $dir );
		if ( '' === $manifiesto ) {
			return new \WP_Error( 'scorm_manifiesto', __( 'No se ha encontrado imsmanifest.xml.', 'convoca-courses' ) );
		}

		$xml = file_get_contents( $manifiesto ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local de uploads.
		if ( false === $xml ) {
			return new \WP_Error( 'scorm_manifiesto', __( 'No se ha podido leer imsmanifest.xml.', 'convoca-courses' ) );
		}

		$datos = self::manifest_data( (string) $xml );
		if ( null === $datos ) {
			return new \WP_Error( 'scorm_manifiesto', __( 'imsmanifest.xml no es válido.', 'convoca-courses' ) );
		}

		$base  = dirname( $manifiesto );
		$entry = ltrim( str_replace( trailingslashit( $dir ), '', trailingslashit( $base ) . $datos['entry'] ), '/' );

		update_post_meta(
			$lesson_id,
			self::META,
			array(
				'dir'      => $rel,
				'entry'    => $entry,
				'version'  => $datos['version'],
				'title'    => $datos['title'],
				'hash'     => $hash,
				'uploaded' => current_time( 'mysql', true ),
			)
		);

		return true;
	}

	/**
	 * Busca el manifiesto en la raíz o en una única carpeta de primer nivel.
	 *
	 * @param string $dir Directorio.
	 * @return string Ruta o cadena vacía.
	 */
	private static function buscar_manifiesto( string $dir ): string {
		$directo = trailingslashit( $dir ) . 'imsmanifest.xml';
		if ( is_readable( $directo ) ) {
			return $directo;
		}

		foreach ( (array) glob( trailingslashit( $dir ) . '*/imsmanifest.xml' ) as $candidato ) {
			if ( is_readable( (string) $candidato ) ) {
				return (string) $candidato;
			}
		}

		return '';
	}

	/**
	 * Pinta el player con la API SCORM.
	 *
	 * @param int                  $lesson_id Lección.
	 * @param array<string, mixed> $meta      Paquete.
	 * @return void
	 */
	private static function render_player( int $lesson_id, array $meta ): void {
		$subida = wp_upload_dir();
		$url    = trailingslashit( $subida['baseurl'] ) . $meta['dir'] . '/';
		$entry  = (string) $meta['entry'];
		$src    = $url . implode( '/', array_map( 'rawurlencode', explode( '/', $entry ) ) );
		$nonce  = wp_create_nonce( self::AJAX_STATE . '_' . $lesson_id );
		$ajax   = admin_url( 'admin-ajax.php' );
		$curso  = Hierarchy::course_of_lesson( $lesson_id );
		$api    = '2004' === (string) $meta['version'] ? 'API_1484_11' : 'API';

		Access_Log::registrar(
			'scorm_launch',
			array(
				'course_id' => $curso,
				'lesson_id' => $lesson_id,
			)
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		$titulo = '' !== (string) $meta['title'] ? (string) $meta['title'] : __( 'Contenido SCORM', 'convoca-courses' );
		?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo esc_html( $titulo ); ?></title>
<style>html,body,iframe{height:100%;margin:0;border:0;width:100%;overflow:hidden}</style>
</head>
<body>
<iframe id="convoca-scorm" src="<?php echo esc_url( $src ); ?>" title="<?php echo esc_attr__( 'Contenido del curso', 'convoca-courses' ); ?>"></iframe>
<script>
(function(){
	var cfg =
		<?php
		echo wp_json_encode(
			array(
				'ajax'   => $ajax,
				'nonce'  => $nonce,
				'lesson' => $lesson_id,
			)
		);
		?>
				;
	var state = {};
	var cola = [];
	var enviando = false;
	function enviar(){
		if ( enviando || ! cola.length ) { return; }
		enviando = true;
		var lote = cola.splice(0, cola.length);
		var cuerpo = new URLSearchParams();
		cuerpo.set('action', '<?php echo esc_js( self::AJAX_STATE ); ?>');
		cuerpo.set('nonce', cfg.nonce);
		cuerpo.set('lesson_id', String(cfg.lesson));
		cuerpo.set('state', JSON.stringify(lote));
		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: cuerpo })
			.catch(function(){})
			.finally(function(){ enviando = false; enviar(); });
	}
	function poner(k, v){
		state[k] = v;
		cola.push([k, v]);
		enviar();
	}
	function api(){
		return {
			LMSInitialize: function(){ return 'true'; },
			Initialize: function(){ return 'true'; },
			LMSGetValue: function(k){ return state[k] || ''; },
			GetValue: function(k){ return state[k] || ''; },
			LMSSetValue: function(k,v){ poner(k,v); return 'true'; },
			SetValue: function(k,v){ poner(k,v); return 'true'; },
			LMSCommit: function(){ enviar(); return 'true'; },
			Commit: function(){ enviar(); return 'true'; },
			LMSFinish: function(){ enviar(); return 'true'; },
			Terminate: function(){ enviar(); return 'true'; },
			LMSGetLastError: function(){ return '0'; },
			GetLastError: function(){ return '0'; },
			LMSGetErrorString: function(){ return ''; },
			GetErrorString: function(){ return ''; },
			LMSGetDiagnostic: function(){ return ''; },
			GetDiagnostic: function(){ return ''; }
		};
	}
	window.API = api();
	window.API_1484_11 = window.API;
	window.addEventListener('beforeunload', function(){ enviar(); });
})();
</script>
</body>
</html>
		<?php
	}

	/**
	 * Guarda un lote de valores SCORM.
	 *
	 * @return void
	 */
	public static function handle_state(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- se comprueba justo después.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( (string) $_POST['lesson_id'] ) ) : 0;
		$nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		$json      = isset( $_POST['state'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::AJAX_STATE . '_' . $lesson_id ) || ! self::can_launch( $lesson_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin acceso.', 'convoca-courses' ) ), 403 );
		}

		$lote = json_decode( $json, true );
		if ( ! is_array( $lote ) ) {
			wp_send_json_error( array( 'message' => __( 'Estado no válido.', 'convoca-courses' ) ), 400 );
		}

		$permitidos = array( 'cmi.core.lesson_status', 'cmi.core.score.raw', 'cmi.core.score.max', 'cmi.core.score.min', 'cmi.core.session_time', 'cmi.suspend_data', 'cmi.core.lesson_location', 'cmi.completion_status', 'cmi.success_status', 'cmi.score.raw', 'cmi.score.max', 'cmi.score.min', 'cmi.score.scaled', 'cmi.session_time', 'cmi.suspend_data', 'cmi.location' );
		$estado     = array();

		foreach ( $lote as $par ) {
			if ( ! is_array( $par ) || 2 !== count( $par ) ) {
				continue;
			}
			$clave = (string) $par[0];
			if ( in_array( $clave, $permitidos, true ) ) {
				$estado[ $clave ] = substr( (string) $par[1], 0, 65535 );
			}
		}

		if ( array() === $estado ) {
			wp_send_json_success( array( 'guardado' => 0 ) );
		}

		self::guardar_estado( $lesson_id, $estado );
		wp_send_json_success( array( 'guardado' => count( $estado ) ) );
	}

	/**
	 * Guarda el estado agregado de una lección.
	 *
	 * @param int                   $lesson_id Lección.
	 * @param array<string, string> $estado   Claves SCORM.
	 * @return void
	 */
	public static function guardar_estado( int $lesson_id, array $estado ): void {
		global $wpdb;

		$course_id    = Hierarchy::course_of_lesson( $lesson_id );
		$user_id      = get_current_user_id();
		$identidad_id = Identidad::actual();
		$tabla        = $wpdb->prefix . Installer::SCORM_STATE_TABLE;

		$status = (string) ( $estado['cmi.core.lesson_status'] ?? $estado['cmi.completion_status'] ?? '' );
		$score  = (string) ( $estado['cmi.core.score.raw'] ?? $estado['cmi.score.raw'] ?? '' );
		$max    = (string) ( $estado['cmi.core.score.max'] ?? $estado['cmi.score.max'] ?? '' );
		$min    = (string) ( $estado['cmi.core.score.min'] ?? $estado['cmi.score.min'] ?? '' );
		$tiempo = (string) ( $estado['cmi.core.session_time'] ?? $estado['cmi.session_time'] ?? '' );

		$fila = array(
			'lesson_id'     => $lesson_id,
			'course_id'     => $course_id,
			'user_id'       => $user_id,
			'identidad_id'  => $identidad_id,
			'lesson_status' => substr( sanitize_text_field( $status ), 0, 40 ),
			'score_raw'     => is_numeric( $score ) ? (float) $score : 0,
			'score_max'     => is_numeric( $max ) ? (float) $max : 0,
			'score_min'     => is_numeric( $min ) ? (float) $min : 0,
			'session_time'  => substr( sanitize_text_field( $tiempo ), 0, 40 ),
			'suspend_data'  => (string) ( $estado['cmi.suspend_data'] ?? '' ),
			'location'      => substr( sanitize_text_field( (string) ( $estado['cmi.core.lesson_location'] ?? $estado['cmi.location'] ?? '' ) ), 0, 255 ),
			'updated_utc'   => current_time( 'mysql', true ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->replace( $tabla, $fila, array( '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s' ) );

		$completado = in_array( strtolower( $fila['lesson_status'] ), array( 'completed', 'passed' ), true );
		if ( $completado ) {
			if ( $identidad_id > 0 ) {
				Progress::complete_identidad( $identidad_id, $course_id, $lesson_id );
			} else {
				Progress::complete( $user_id, $course_id, $lesson_id );
			}

			Access_Log::registrar(
				'scorm_complete',
				array(
					'course_id' => $course_id,
					'lesson_id' => $lesson_id,
					'data'      => array(
						'status' => $fila['lesson_status'],
						'score'  => $fila['score_raw'],
					),
				)
			);
		}
	}

	/**
	 * Estado SCORM de una persona en una lección.
	 *
	 * @param int $lesson_id    Lección.
	 * @param int $user_id      Usuario.
	 * @param int $identidad_id Identidad.
	 * @return array<string, mixed>|null
	 */
	public static function estado( int $lesson_id, int $user_id, int $identidad_id = 0 ): ?array {
		global $wpdb;

		$tabla = $wpdb->prefix . Installer::SCORM_STATE_TABLE;
		$where = $identidad_id > 0 ? $wpdb->prepare( 'identidad_id = %d', $identidad_id ) : $wpdb->prepare( 'user_id = %d', $user_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$fila = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE lesson_id = %d AND {$where} LIMIT 1", $lesson_id ), 'ARRAY_A' );

		return is_array( $fila ) ? $fila : null;
	}
}
