<?php
/**
 * Informes por alumno (CSV y PDF).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrega lo que ya está disperso en el módulo y lo entrega en un informe.
 */
class Reports {

	/**
	 * Acción CSV.
	 */
	public const ACTION_CSV = 'convoca_courses_student_csv';

	/**
	 * Acción PDF.
	 */
	public const ACTION_PDF = 'convoca_courses_student_pdf';

	/**
	 * Engancha las descargas.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_CSV, array( __CLASS__, 'handle_csv' ) );
		add_action( 'admin_post_' . self::ACTION_PDF, array( __CLASS__, 'handle_pdf' ) );
	}

	/**
	 * Datos de un alumno en un curso.
	 *
	 * @param int $course_id    Curso.
	 * @param int $user_id      Usuario.
	 * @param int $identidad_id Identidad.
	 * @return array<string, mixed>|null
	 */
	public static function student( int $course_id, int $user_id, int $identidad_id = 0 ): ?array {
		$alumno = null;
		foreach ( Roster::students( $course_id ) as $fila ) {
			if ( (int) $fila['user_id'] === $user_id && (int) $fila['identidad'] === $identidad_id ) {
				$alumno = $fila;
				break;
			}
		}

		if ( null === $alumno ) {
			return null;
		}

		$progreso = $identidad_id > 0
			? Progress::for_course_identidad( $identidad_id, $course_id )
			: Progress::for_course( $user_id, $course_id );

		$notas = Gradebook::for_user( $user_id, $course_id, $identidad_id );
		$log   = Access_Log::summary_for( $course_id, $user_id, $identidad_id );
		$scorm = array();

		foreach ( CPT_Leccion::of_course( $course_id ) as $leccion ) {
			$estado = SCORM::estado( (int) $leccion->ID, $user_id, $identidad_id );
			if ( null !== $estado ) {
				$scorm[] = array(
					'leccion' => (string) $leccion->post_title,
					'estado'  => (string) $estado['lesson_status'],
					'nota'    => (float) $estado['score_raw'],
					'tiempo'  => (string) $estado['session_time'],
				);
			}
		}

		$encuesta = Survey::respuesta( $course_id, $user_id, $identidad_id );

		return array(
			'course_id'    => $course_id,
			'course'       => (string) get_the_title( $course_id ),
			'user_id'      => $user_id,
			'identidad_id' => $identidad_id,
			'nombre'       => (string) $alumno['nombre'],
			'correo'       => (string) $alumno['correo'],
			'estado'       => (string) $alumno['estado'],
			'progreso'     => (float) $progreso['percent'],
			'notas'        => $notas,
			'conexiones'   => $log,
			'scorm'        => $scorm,
			'encuesta'     => $encuesta,
		);
	}

	/**
	 * Filas de todos los alumnos de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( int $course_id ): array {
		$salida = array();

		foreach ( Roster::students( $course_id ) as $alumno ) {
			$fila = self::student( $course_id, (int) $alumno['user_id'], (int) $alumno['identidad'] );
			if ( null !== $fila ) {
				$salida[] = $fila;
			}
		}

		return $salida;
	}

	/**
	 * CSV del curso.
	 *
	 * @param array<int, array<string, mixed>> $rows Filas.
	 * @return string
	 */
	public static function csv( array $rows ): string {
		$lineas   = array();
		$lineas[] = Gradebook::csv_line(
			array(
				'Alumno',
				'Correo',
				'Estado',
				'Progreso %',
				'Nota final',
				'Aprobado',
				'Conexiones',
				'Última conexión',
				'Tiempo SCORM',
				'SCORM',
				'Encuesta',
				'Comentario',
			)
		);

		foreach ( $rows as $fila ) {
			$scorm_txt = array();
			$tiempo    = 0;
			foreach ( (array) $fila['scorm'] as $pieza ) {
				$scorm_txt[] = $pieza['leccion'] . ': ' . $pieza['estado'];
				$tiempo     += self::segundos( (string) $pieza['tiempo'] );
			}

			$encuesta = $fila['encuesta'];
			$answers  = is_array( $encuesta ) && isset( $encuesta['answers'] ) ? $encuesta['answers'] : array();
			$media    = array() !== (array) $answers ? round( array_sum( array_map( 'intval', (array) $answers ) ) / count( (array) $answers ), 2 ) : '';

			$lineas[] = Gradebook::csv_line(
				array(
					(string) $fila['nombre'],
					(string) $fila['correo'],
					(string) $fila['estado'],
					(string) $fila['progreso'],
					null === $fila['notas']['nota'] ? '' : (string) $fila['notas']['nota'],
					$fila['notas']['aprobado'] ? 'sí' : 'no',
					(string) $fila['conexiones']['total'],
					(string) $fila['conexiones']['ultima'],
					self::duracion( $tiempo ),
					implode( ' | ', $scorm_txt ),
					'' === $media ? '' : (string) $media,
					is_array( $encuesta ) ? (string) ( $encuesta['comentario'] ?? '' ) : '',
				)
			);
		}

		return implode( "\n", $lineas ) . "\n";
	}

	/**
	 * Descarga CSV de un curso.
	 *
	 * @return void
	 */
	public static function handle_csv(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- se comprueba justo después.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$nonce     = isset( $_GET['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! Pro::enabled( 'informes_alumno' ) || ! Pro::can_grade() || ! wp_verify_nonce( $nonce, self::ACTION_CSV . '_' . $course_id ) ) {
			wp_die( esc_html__( 'No tienes permiso para descargar este informe.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		$csv = self::csv( self::rows( $course_id ) );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="informe-alumnos-' . (int) $course_id . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV: escaparlo lo rompería.
		exit;
	}

	/**
	 * Descarga PDF de un alumno.
	 *
	 * @return void
	 */
	public static function handle_pdf(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- se comprueba justo después.
		$course_id    = isset( $_GET['course_id'] ) ? absint( wp_unslash( (string) $_GET['course_id'] ) ) : 0;
		$user_id      = isset( $_GET['user_id'] ) ? absint( wp_unslash( (string) $_GET['user_id'] ) ) : 0;
		$identidad_id = isset( $_GET['identidad_id'] ) ? absint( wp_unslash( (string) $_GET['identidad_id'] ) ) : 0;
		$nonce        = isset( $_GET['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! Pro::enabled( 'informes_alumno' ) || ! Pro::can_grade() || ! wp_verify_nonce( $nonce, self::ACTION_PDF . '_' . $course_id . '_' . $user_id . '_' . $identidad_id ) ) {
			wp_die( esc_html__( 'No tienes permiso para descargar este informe.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		$datos = self::student( $course_id, $user_id, $identidad_id );
		if ( null === $datos ) {
			wp_die( esc_html__( 'No se ha encontrado al alumno.', 'convoca-courses' ), '', array( 'response' => 404 ) );
		}

		$pdf = self::pdf( $datos );
		if ( '' === $pdf ) {
			wp_die( esc_html__( 'No se ha podido generar el PDF. Descarga el CSV.', 'convoca-courses' ), '', array( 'response' => 500 ) );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . basename( $pdf ) . '"' );
		readfile( $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- fichero local de uploads.
		exit;
	}

	/**
	 * Genera el PDF de un alumno.
	 *
	 * @param array<string, mixed> $datos Datos.
	 * @return string Ruta o cadena vacía.
	 */
	public static function pdf( array $datos ): string {
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			return '';
		}

		$subida = wp_upload_dir();
		if ( ! empty( $subida['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $subida['basedir'] ) . 'convoca-courses/informes';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$nombre = 'informe-' . (int) $datos['course_id'] . '-' . (int) $datos['user_id'] . '-' . (int) $datos['identidad_id'] . '.pdf';
		$path   = trailingslashit( $dir ) . $nombre;
		$html   = Certificate::render_template( 'informe-alumno.php', $datos, false );

		try {
			$dompdf = new \Dompdf\Dompdf(
				array(
					'isRemoteEnabled'      => false,
					'isHtml5ParserEnabled' => true,
					'chroot'               => ABSPATH,
				)
			);
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			file_put_contents( $path, $dompdf->output() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichero local de uploads.
		} catch ( \Throwable $e ) {
			return '';
		}

		return $path;
	}

	/**
	 * Segundos de una duración SCORM (`HH:MM:SS` o ISO 8601).
	 *
	 * @param string $valor Duración.
	 * @return int
	 */
	public static function segundos( string $valor ): int {
		$valor = trim( $valor );
		if ( '' === $valor ) {
			return 0;
		}

		if ( preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $valor, $m ) ) {
			return (int) ( $m[1] ?? 0 ) * 3600 + (int) ( $m[2] ?? 0 ) * 60 + (int) ( $m[3] ?? 0 );
		}

		if ( preg_match( '/^(\d+):(\d+):(\d+)$/', $valor, $m ) ) {
			return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
		}

		return 0;
	}

	/**
	 * Formatea segundos.
	 *
	 * @param int $segundos Segundos.
	 * @return string
	 */
	public static function duracion( int $segundos ): string {
		$horas = intdiv( $segundos, 3600 );
		$min   = intdiv( $segundos % 3600, 60 );

		return $horas . ' h ' . $min . ' min';
	}
}
