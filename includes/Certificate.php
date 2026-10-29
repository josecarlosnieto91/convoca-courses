<?php
/**
 * Certificado del curso: emisión en PDF y verificación pública.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Certificados.
 *
 * La numeración es propia del módulo y no depende de ningún otro plugin del ecosistema.
 */
class Certificate {

	/**
	 * Prefijo de los códigos de certificado.
	 */
	public const CODE_PREFIX = 'CC';

	/**
	 * Emite el certificado si el curso lo pide y el progreso está al 100 %.
	 *
	 * @param int $enrollment_id Identificador de la matrícula.
	 * @return string Código emitido, vacío si no procede.
	 */
	/**
	 * Emite el certificado cuando el intento sale aprobado.
	 *
	 * El intento no se usa: el certificado se pide por matrícula y `maybe_issue()` vuelve a comprobar
	 * progreso, nota mínima y si ya había código.
	 *
	 * @param int                  $attempt_id Intento corregido.
	 * @param int                  $course_id  Curso.
	 * @param int                  $user_id    Persona.
	 * @param array<string, mixed> $resultado  Resultado de la corrección.
	 * @return void
	 */
	public static function on_exam_submitted( int $attempt_id, int $course_id, int $user_id, array $resultado ): void {
		if ( empty( $resultado['aprobado'] ) ) {
			return;
		}

		// Identidad propia: la matrícula se busca por el intento, no por el usuario.
		$intento = Attempts::get( $attempt_id );
		if ( null !== $intento && (int) ( $intento['identidad_id'] ?? 0 ) > 0 ) {
			$enrollment = Enrollment::find_por_identidad( (int) $intento['identidad_id'], $course_id, true );
			if ( $enrollment > 0 ) {
				self::maybe_issue( $enrollment );
			}

			return;
		}

		$enrollment = Enrollment::active_id( $user_id, $course_id );
		if ( $enrollment > 0 ) {
			self::maybe_issue( $enrollment );
		}
	}

	/**
	 * Emite el certificado si el curso ya cumple todo lo que exige.
	 *
	 * Idempotente: si ya hay código, devuelve el que hay.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function maybe_issue( int $enrollment_id ): string {
		if ( CPT_Matricula::TYPE !== get_post_type( $enrollment_id ) ) {
			return '';
		}

		$course_id    = CPT_Matricula::course_id( $enrollment_id );
		$user_id      = CPT_Matricula::user_id( $enrollment_id );
		$identidad_id = (int) get_post_meta( $enrollment_id, Identidad::META_MATRICULA, true );

		if ( 0 === $course_id || ( 0 === $user_id && $identidad_id <= 0 ) || ! CPT_Curso::issues_certificate( $course_id ) ) {
			return '';
		}

		$existing = CPT_Matricula::get_string( $enrollment_id, 'cert_codigo' );
		if ( '' !== $existing ) {
			return $existing;
		}

		$progress = $identidad_id > 0
			? Progress::for_course_identidad( $identidad_id, $course_id )
			: Progress::for_course( $user_id, $course_id );

		if ( $progress['total'] <= 0 || $progress['done'] < $progress['total'] ) {
			return '';
		}

		// Curso que evalúa: el certificado exige aprobado, no solo haber visto las lecciones.
		// Es la puerta que impide certificar a quien no ha demostrado nada.
		if ( Gradebook::has_evaluation( $course_id ) ) {
			$notas = Gradebook::for_user( $user_id, $course_id, $identidad_id );
			if ( ! $notas['aprobado'] ) {
				return '';
			}
		}

		$code = self::generate_code( $course_id, $enrollment_id );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'cert_codigo', $code );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'cert_fecha', current_time( 'mysql' ) );

		// La nota del certificado se congela aquí: un certificado no puede cambiar de nota después.
		if ( Gradebook::has_evaluation( $course_id ) ) {
			$notas = Gradebook::for_user( $user_id, $course_id, $identidad_id );
			update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'cert_nota', null === $notas['nota'] ? '' : (float) $notas['nota'] );
		}

		if ( 'completada' !== CPT_Matricula::status( $enrollment_id ) ) {
			update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'estado', 'completada' );
		}

		// El registro propio se escribe al emitir: la validación no puede depender de que la matrícula
		// siga existiendo. Un certificado no se revoca, ni al desistir ni al borrar la matrícula.
		self::registrar( $enrollment_id, $code );

		/**
		 * Certificado emitido.
		 *
		 * @param int    $enrollment_id Identificador de la matrícula.
		 * @param string $code          Código público del certificado.
		 */
		do_action( 'convoca_courses_certificate_issued', $enrollment_id, $code );

		// Aviso de cierre a quien la organización haya decidido (o al administrador).
		Notifications::aviso_cierre( $enrollment_id );

		if ( $user_id > 0 ) {
			Notifications::certificate_issued( $user_id, $course_id, $code, self::verify_url( $code ) );
		}

		return $code;
	}

	/**
	 * Guarda el certificado en el registro propio, independiente de la matrícula.
	 *
	 * @param int    $enrollment_id Matrícula.
	 * @param string $code          Código público.
	 * @return void
	 */
	public static function registrar( int $enrollment_id, string $code ): void {
		global $wpdb;

		$code = self::normalize( $code );
		if ( '' === $code || null !== self::registro( $code ) ) {
			return;
		}

		$course_id = CPT_Matricula::course_id( $enrollment_id );
		$user_id   = CPT_Matricula::user_id( $enrollment_id );
		$user      = $user_id > 0 ? get_userdata( $user_id ) : null;

		if ( null === $user ) {
			$identidad_id = (int) get_post_meta( $enrollment_id, Identidad::META_MATRICULA, true );
			$fila         = Identidad::por_id( $identidad_id );

			if ( null !== $fila ) {
				$user = (object) array(
					'display_name' => (string) $fila['nombre'],
					'user_email'   => (string) $fila['email'],
				);
			}
		}

		$nota = CPT_Matricula::get_string( $enrollment_id, 'cert_nota', '' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- tabla propia.
			$wpdb->prefix . Installer::CERTIFICADOS_TABLE,
			array(
				'codigo'       => $code,
				'curso_id'     => $course_id,
				'curso_titulo' => (string) get_the_title( $course_id ),
				'persona'      => $user ? (string) $user->display_name : '',
				'email'        => $user ? (string) $user->user_email : '',
				'nota'         => '' === $nota ? 0.0 : (float) $nota,
				'fecha'        => gmdate( 'Y-m-d', (int) strtotime( (string) current_time( 'mysql' ) ) ),
				'emitido_utc'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
	}

	/**
	 * Fila del registro de certificados.
	 *
	 * @param string $code Código normalizado.
	 * @return array<string, mixed>|null
	 */
	public static function registro( string $code ): ?array {
		global $wpdb;

		$tabla = $wpdb->prefix . Installer::CERTIFICADOS_TABLE;
		$fila  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia.
			$wpdb->prepare( 'SELECT * FROM ' . $tabla . ' WHERE codigo = %s', self::normalize( $code ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- el nombre de la tabla no es una entrada.
			'ARRAY_A'
		);

		return is_array( $fila ) ? $fila : null;
	}

	/**
	 * Forma canónica de un código: mayúsculas y solo letras y números.
	 *
	 * La gente escribe el código como quiere (con guiones, con espacios, en minúsculas). La búsqueda
	 * normaliza la entrada y el código se guarda ya normalizado, así que no puede haber dos formas del
	 * mismo certificado.
	 *
	 * @param string $code Código tal cual lo escribe la persona.
	 * @return string
	 */
	public static function normalize( string $code ): string {
		return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $code ) );
	}

	/**
	 * URL pública de verificación de un código.
	 *
	 * @param string $code Código del certificado.
	 * @return string
	 */
	public static function verify_url( string $code ): string {
		return home_url( '/verificar-curso/?codigo=' . self::normalize( $code ) );
	}

	/**
	 * Genera el código público del certificado.
	 *
	 * @param int $course_id     Curso.
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function generate_code( int $course_id, int $enrollment_id ): string {
		$body = self::normalize(
			sprintf(
				'%s-%d-%d-%s',
				self::CODE_PREFIX,
				$course_id,
				$enrollment_id,
				substr( strtoupper( wp_generate_password( 8, false, false ) ), 0, 8 )
			)
		);

		// Se comprueba que no exista ya: la numeración es única.
		if ( self::find_by_code( $body ) > 0 ) {
			return self::generate_code( $course_id, $enrollment_id );
		}

		return $body;
	}

	/**
	 * Busca la matrícula dueña de un código de certificado.
	 *
	 * @param string $code Código.
	 * @return int Identificador de la matrícula, 0 si no existe.
	 */
	public static function find_by_code( string $code ): int {
		$code = self::normalize( $code );
		if ( '' === $code ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- búsqueda por código único, conjunto pequeño.
					array(
						'key'   => CPT_Base::META_PREFIX . 'cert_codigo',
						'value' => $code,
					),
				),
			)
		);

		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/**
	 * Datos del certificado para pintarlo.
	 *
	 * @param string $code Código.
	 * @return array<string, string>|null
	 */
	public static function data( string $code ): ?array {
		$code = self::normalize( $code );
		$reg  = self::registro( $code );

		// El registro propio es la fuente: quien borra la matrícula (baja, desistimiento, supresión de
		// datos) no invalida el certificado, porque los certificados no se revocan.
		if ( null !== $reg ) {
			$nota = (float) $reg['nota'];

			return array(
				'code'   => $code,
				'course' => (string) $reg['curso_titulo'],
				'person' => (string) $reg['persona'],
				'date'   => (string) $reg['fecha'],
				'center' => (string) get_bloginfo( 'name' ),
				'nota'   => $nota > 0 ? number_format_i18n( $nota, 2 ) : '',
				'verify' => self::verify_url( $code ),
				'qr'     => Certificate_QR::enabled() ? Certificate_QR::image( self::verify_url( $code ) ) : '',
			);
		}

		$enrollment_id = self::find_by_code( $code );
		if ( 0 === $enrollment_id ) {
			return null;
		}

		$course_id = CPT_Matricula::course_id( $enrollment_id );
		$user_id   = CPT_Matricula::user_id( $enrollment_id );
		$user      = $user_id > 0 ? get_userdata( $user_id ) : null;

		// Alumnado sin cuenta: el nombre sale de su identidad.
		if ( null === $user ) {
			$identidad_id = (int) get_post_meta( $enrollment_id, Identidad::META_MATRICULA, true );
			$fila         = Identidad::por_id( $identidad_id );

			if ( null !== $fila ) {
				$user = (object) array(
					'display_name' => (string) $fila['nombre'],
					'user_email'   => (string) $fila['email'],
				);
			}
		}

		$nota = CPT_Matricula::get_string( $enrollment_id, 'cert_nota', '' );

		return array(
			'code'   => self::normalize( $code ),
			'course' => (string) get_the_title( $course_id ),
			'person' => $user ? (string) $user->display_name : '',
			'date'   => (string) CPT_Matricula::get_string( $enrollment_id, 'cert_fecha' ),
			'center' => (string) get_bloginfo( 'name' ),
			'nota'   => '' === $nota ? '' : number_format_i18n( (float) $nota, 2 ),
			'verify' => self::verify_url( $code ),
			'qr'     => Certificate_QR::enabled() ? Certificate_QR::image( self::verify_url( $code ) ) : '',
		);
	}

	/**
	 * Genera el PDF del certificado. Devuelve la ruta o cadena vacía.
	 *
	 * @param string $code Código del certificado.
	 * @return string Ruta absoluta del PDF.
	 */
	public static function pdf_path( string $code ): string {
		$data = self::data( $code );
		if ( null === $data ) {
			return '';
		}

		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			return '';
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'convoca-courses';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$path = trailingslashit( $dir ) . 'certificado-' . sanitize_file_name( $data['code'] ) . '.pdf';
		if ( file_exists( $path ) ) {
			return $path;
		}

		$html = self::render_template( 'certificado.php', $data, false );

		try {
			$dompdf = new \Dompdf\Dompdf(
				array(
					'isRemoteEnabled'      => false,
					'isHtml5ParserEnabled' => true,
				)
			);
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'landscape' );
			$dompdf->render();

			$bytes = $dompdf->output();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- escritura en uploads del propio plugin, WP_Filesystem pide credenciales en muchos hostings.
			file_put_contents( $path, $bytes );
		} catch ( \Throwable $e ) {
			if ( class_exists( '\Convoca\Core\Logger' ) ) {
				\Convoca\Core\Logger::error( 'No se ha podido generar el PDF del certificado: ' . $e->getMessage(), 'Convoca Courses', 0 );
			}
			return '';
		}

		return file_exists( $path ) ? $path : '';
	}

	/**
	 * Pinta una plantilla del módulo y devuelve el HTML.
	 *
	 * @param string              $template Nombre del fichero en templates/.
	 * @param array<string,mixed> $vars     Variables disponibles en la plantilla.
	 * @param bool                $print_html Si se imprime (true) o se devuelve (false).
	 * @return string
	 */
	public static function render_template( string $template, array $vars, bool $print_html = true ): string {
		$file = CONVOCA_COURSES_DIR . 'templates/' . $template;
		if ( ! file_exists( $file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- contexto controlado de plantilla propia.
		extract( $vars, EXTR_SKIP );

		ob_start();
		include $file;
		$html = (string) ob_get_clean();

		if ( $print_html ) {
			// La plantilla ya escapa cada dato.
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construido por la plantilla, escapado campo a campo.
		}

		return $html;
	}
}
