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
	public static function maybe_issue( int $enrollment_id ): string {
		if ( CPT_Matricula::TYPE !== get_post_type( $enrollment_id ) ) {
			return '';
		}

		$course_id = CPT_Matricula::course_id( $enrollment_id );
		$user_id   = CPT_Matricula::user_id( $enrollment_id );

		if ( 0 === $course_id || 0 === $user_id || ! CPT_Curso::issues_certificate( $course_id ) ) {
			return '';
		}

		$existing = CPT_Matricula::get_string( $enrollment_id, 'cert_codigo' );
		if ( '' !== $existing ) {
			return $existing;
		}

		$progress = Progress::for_course( $user_id, $course_id );
		if ( $progress['total'] <= 0 || $progress['done'] < $progress['total'] ) {
			return '';
		}

		$code = self::generate_code( $course_id, $enrollment_id );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'cert_codigo', $code );
		update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'cert_fecha', current_time( 'mysql' ) );

		if ( 'completada' !== CPT_Matricula::status( $enrollment_id ) ) {
			update_post_meta( $enrollment_id, CPT_Base::META_PREFIX . 'estado', 'completada' );
		}

		/**
		 * Certificado emitido.
		 *
		 * @param int    $enrollment_id Identificador de la matrícula.
		 * @param string $code          Código público del certificado.
		 */
		do_action( 'convoca_courses_certificate_issued', $enrollment_id, $code );

		return $code;
	}

	/**
	 * Genera el código público del certificado.
	 *
	 * @param int $course_id     Curso.
	 * @param int $enrollment_id Matrícula.
	 * @return string
	 */
	public static function generate_code( int $course_id, int $enrollment_id ): string {
		$body = sprintf(
			'%s-%d-%d-%s',
			self::CODE_PREFIX,
			$course_id,
			$enrollment_id,
			substr( strtoupper( wp_generate_password( 8, false, false ) ), 0, 8 )
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
		$code = strtoupper( trim( $code ) );
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
		$enrollment_id = self::find_by_code( $code );
		if ( 0 === $enrollment_id ) {
			return null;
		}

		$course_id = CPT_Matricula::course_id( $enrollment_id );
		$user_id   = CPT_Matricula::user_id( $enrollment_id );
		$user      = get_userdata( $user_id );

		return array(
			'code'   => strtoupper( trim( $code ) ),
			'course' => (string) get_the_title( $course_id ),
			'person' => $user ? (string) $user->display_name : '',
			'date'   => (string) CPT_Matricula::get_string( $enrollment_id, 'cert_fecha' ),
			'center' => (string) get_bloginfo( 'name' ),
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
