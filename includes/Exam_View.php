<?php
/**
 * Shortcode de examen: pintar el intento, el resultado y el bloqueo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Datos del examen para la plantilla.
 *
 * Vive aparte del shortcode para que sea la misma información la que se pinte en el curso y en la
 * página del examen: dos sitios que dicen cosas distintas del mismo examen es un defecto garantizado.
 */
class Exam_View {

	/**
	 * Datos completos de un examen para una persona.
	 *
	 * @param int $exam_id Examen.
	 * @param int $user_id Persona.
	 * @return array<string, mixed>
	 */
	public static function data( int $exam_id, int $user_id ): array {
		$course_id  = CPT_Examen::course_id( $exam_id );
		$module_id  = CPT_Examen::module_id( $exam_id );
		$minimo     = CPT_Examen::min_score( $exam_id );
		$permitidos = CPT_Examen::max_attempts( $exam_id );
		$usados     = Attempts::count_for( $user_id, $exam_id );
		$abierto    = Attempts::current( $user_id, $exam_id );
		$puede      = $course_id > 0 && Access::can_take_exam( $user_id, $course_id, $module_id );
		$quedan     = Attempts::remaining( $permitidos, $usados );

		$preguntas = array();
		if ( null !== $abierto ) {
			foreach ( Attempts::question_ids( $abierto ) as $question_id ) {
				$pregunta = Questions::get( $question_id );
				if ( null !== $pregunta ) {
					$preguntas[] = $pregunta;
				}
			}
		}

		$intentos = self::attempts_data( $user_id, $exam_id );

		return array(
			'exam_id'       => $exam_id,
			'course_id'     => $course_id,
			'title'         => (string) get_the_title( $exam_id ),
			'intro'         => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $exam_id ) ),
			'enrolled'      => $course_id > 0 && Access::is_enrolled( $user_id, $course_id ),
			'logged_in'     => $user_id > 0,
			'can_take'      => $puede,
			'expired'       => $course_id > 0 && $user_id > 0 && Access::is_expired( $user_id, $course_id ),
			'min_score'     => $minimo,
			'time_limit'    => CPT_Examen::time_limit( $exam_id ),
			'feedback'      => CPT_Examen::feedback( $exam_id ),
			'attempts_used' => $usados,
			'attempts_left' => $quedan,
			'attempts_max'  => $permitidos,
			'open'          => $abierto,
			'questions'     => $preguntas,
			'attempts'      => $intentos,
			'best'          => Attempts::best_score( $user_id, $exam_id ),
			'passed'        => Attempts::has_passed( $user_id, $exam_id ),
			'open_url'      => Handlers::exam_form_action(),
			'open_nonce'    => Handlers::exam_open_nonce( $exam_id ),
			'course_url'    => $course_id > 0 ? (string) get_permalink( $course_id ) : '',
			'notice'        => self::notice(),
			'result'        => self::requested_attempt( $user_id, $exam_id ),
		);
	}

	/**
	 * Historial de intentos cerrados, del más reciente al más antiguo.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return array<int, array<string, mixed>>
	 */
	public static function attempts_data( int $user_id, int $exam_id ): array {
		global $wpdb;

		if ( $user_id <= 0 || $exam_id <= 0 ) {
			return array();
		}

		$table = Installer::table( Installer::ATTEMPTS_TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia, valores preparados.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND exam_id = %d AND estado <> 'abierto' ORDER BY id DESC",
				$user_id,
				$exam_id
			),
			'ARRAY_A'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( Attempts::class, 'decode' ), $rows );
	}

	/**
	 * Intento concreto pedido en la URL, si es de esta persona.
	 *
	 * @param int $user_id Persona.
	 * @param int $exam_id Examen.
	 * @return array<string, mixed>|null
	 */
	private static function requested_attempt( int $user_id, int $exam_id ): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de un identificador para pintar un resultado propio.
		$attempt_id = isset( $_GET['convoca_intento'] ) ? absint( wp_unslash( (string) $_GET['convoca_intento'] ) ) : 0;

		if ( $attempt_id <= 0 ) {
			return null;
		}

		$attempt = Attempts::get( $attempt_id );
		if ( null === $attempt ) {
			return null;
		}

		if ( (int) $attempt['user_id'] !== $user_id || (int) $attempt['exam_id'] !== $exam_id ) {
			return null;
		}

		// Detalle de la corrección, reconstruido de las respuestas guardadas: es lo que el alumno
		// necesita para ver qué falló cuando la corrección está configurada «al enviar».
		if ( 'enviado' === (string) $attempt['estado'] && 'tras_enviar' === CPT_Examen::feedback( $exam_id ) ) {
			$detalle = array();
			foreach ( Attempts::answers( (int) $attempt['id'] ) as $linea ) {
				$pregunta  = Questions::get( (int) $linea['question_id'] );
				$detalle[] = array(
					'question_id' => (int) $linea['question_id'],
					'correcta'    => (bool) $linea['correcta'],
					'puntos'      => (float) $linea['puntos'],
					'enunciado'   => null === $pregunta ? '' : (string) $pregunta['enunciado'],
					'explicacion' => null === $pregunta ? '' : (string) $pregunta['explicacion'],
				);
			}
			$attempt['detalle'] = $detalle;
		}

		return $attempt;
	}

	/**
	 * Aviso pedido en la URL (enviado, expirado, bloqueado).
	 *
	 * @return string
	 */
	private static function notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del aviso a pintar.
		$key = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : '';

		return '' === $key ? '' : Handlers::message( $key );
	}
}
