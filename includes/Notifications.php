<?php
/**
 * Avisos al alumnado por la cola de correo del ecosistema.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Avisos del módulo.
 *
 * En este ecosistema no se llama a `wp_mail()` desde un plugin: el correo sale por
 * `Convoca\Core\Mailer`, que es el punto único (identidad visual, remitente y copia a la asociación).
 * Si el núcleo no está, el aviso **no se pierde**: se apunta en una lista de pendientes para que se
 * pueda reenviar cuando vuelva, y queda en el registro.
 */
class Notifications {

	/**
	 * Opción donde se apuntan los avisos que no se han podido enviar.
	 */
	public const PENDING_OPTION = 'convoca_courses_pending_notices';

	/**
	 * Manda un aviso a una persona.
	 *
	 * @param int                  $user_id   Destinatario.
	 * @param string               $subject   Asunto.
	 * @param string               $body      Cuerpo (texto o HTML).
	 * @param string               $template  Nombre de la plantilla, para el registro.
	 * @param array<string, mixed> $extra     Datos que se guardan si el aviso queda pendiente.
	 * @return bool Verdadero si salió.
	 */
	public static function send( int $user_id, string $subject, string $body, string $template, array $extra = array() ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user || '' === (string) $user->user_email ) {
			self::record_pending( $user_id, $subject, $body, $template, $extra, 'sin correo' );
			return false;
		}

		if ( ! class_exists( '\Convoca\Core\Mailer' ) ) {
			self::record_pending( $user_id, $subject, $body, $template, $extra, 'sin Mailer del nucleo' );
			return false;
		}

		$sent = (bool) \Convoca\Core\Mailer::send(
			(string) $user->user_email,
			$subject,
			$body,
			array(
				'plugin'    => 'convoca-courses',
				'template'  => $template,
				'entity_id' => $user_id,
			)
		);

		if ( ! $sent ) {
			self::record_pending( $user_id, $subject, $body, $template, $extra, 'envio fallido' );
		}

		return $sent;
	}

	/**
	 * Aviso de tarea corregida.
	 *
	 * @param int    $user_id   Alumno.
	 * @param int    $task_id   Tarea.
	 * @param int    $course_id Curso.
	 * @param float  $nota      Nota puesta.
	 * @param string $comentario Comentario del corrector.
	 * @return bool
	 */
	public static function task_graded( int $user_id, int $task_id, int $course_id, float $nota, string $comentario ): bool {
		$subject = sprintf(
			/* translators: %s: task title. */
			__( 'Tarea corregida: %s', 'convoca-courses' ),
			(string) get_the_title( $task_id )
		);

		$lineas = array(
			sprintf(
				/* translators: 1: course title, 2: task title. */
				__( 'Curso: %1$s. Tarea: %2$s.', 'convoca-courses' ),
				(string) get_the_title( $course_id ),
				(string) get_the_title( $task_id )
			),
			sprintf(
				/* translators: %s: score, 0-100. */
				__( 'Nota: %s sobre 100.', 'convoca-courses' ),
				number_format_i18n( $nota, 2 )
			),
		);

		if ( '' !== trim( $comentario ) ) {
			$lineas[] = __( 'Comentario:', 'convoca-courses' ) . ' ' . $comentario;
		}

		$course_url = $course_id > 0 ? (string) get_permalink( $course_id ) : '';

		return self::send(
			$user_id,
			$subject,
			implode( "\n\n", $lineas ),
			'tarea-corregida',
			array(
				'task_id'   => $task_id,
				'course_id' => $course_id,
				'url'       => $course_url,
			)
		);
	}

	/**
	 * Aviso de certificado emitido.
	 *
	 * @param int    $user_id       Alumno.
	 * @param int    $course_id     Curso.
	 * @param string $code          Código del certificado.
	 * @param string $verify_url    URL de verificación.
	 * @return bool
	 */
	public static function certificate_issued( int $user_id, int $course_id, string $code, string $verify_url = '' ): bool {
		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Certificado emitido: %s', 'convoca-courses' ),
			(string) get_the_title( $course_id )
		);

		$lineas = array(
			sprintf(
				/* translators: %s: certificate code. */
				__( 'Tu certificado del curso ya está disponible. Código: %s', 'convoca-courses' ),
				$code
			),
		);

		if ( '' !== $verify_url ) {
			$lineas[] = $verify_url;
		}

		return self::send(
			$user_id,
			$subject,
			implode( "\n\n", $lineas ),
			'certificado',
			array(
				'course_id' => $course_id,
				'meta'      => array( 'cert_codigo' => $code ),
			)
		);
	}

	/**
	 * Recordatorio de inactividad.
	 *
	 * @param int $user_id   Alumno.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function reminder_inactive( int $user_id, int $course_id ): bool {
		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Te esperamos en %s', 'convoca-courses' ),
			(string) get_the_title( $course_id )
		);

		$body = __( 'Hace días que no avanzas en el curso. Cuando quieras, lo tienes donde lo dejaste.', 'convoca-courses' );

		$course_url = (string) get_permalink( $course_id );
		if ( '' !== $course_url ) {
			$body .= "\n\n" . $course_url;
		}

		return self::send( $user_id, $subject, $body, 'recordatorio-inactividad', array( 'course_id' => $course_id ) );
	}

	/**
	 * Recordatorio de examen pendiente.
	 *
	 * @param int $user_id   Alumno.
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function reminder_exam( int $user_id, int $course_id ): bool {
		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Te queda el examen de %s', 'convoca-courses' ),
			(string) get_the_title( $course_id )
		);

		$body = __( 'Has completado las lecciones y el examen sigue pendiente. Puedes hacerlo cuando quieras.', 'convoca-courses' );

		$course_url = (string) get_permalink( $course_id );
		if ( '' !== $course_url ) {
			$body .= "\n\n" . $course_url;
		}

		return self::send( $user_id, $subject, $body, 'recordatorio-examen', array( 'course_id' => $course_id ) );
	}

	/**
	 * Mensaje del profesorado al alumnado de un curso.
	 *
	 * @param int    $user_id   Alumno.
	 * @param int    $course_id Curso.
	 * @param string $subject   Asunto.
	 * @param string $body      Cuerpo.
	 * @return bool
	 */
	public static function group_message( int $user_id, int $course_id, string $subject, string $body ): bool {
		return self::send( $user_id, $subject, $body, 'mensaje-grupo', array( 'course_id' => $course_id ) );
	}

	/**
	 * Apunta un aviso que no se ha podido mandar.
	 *
	 * @param int                  $user_id  Destinatario.
	 * @param string               $subject  Asunto.
	 * @param string               $body     Cuerpo.
	 * @param string               $template Plantilla.
	 * @param array<string, mixed> $extra    Datos extra.
	 * @param string               $motivo   Por qué no salió.
	 * @return void
	 */
	private static function record_pending( int $user_id, string $subject, string $body, string $template, array $extra, string $motivo ): void {
		$pending = get_option( self::PENDING_OPTION, array() );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		$pending[] = array(
			'user_id'  => $user_id,
			'subject'  => $subject,
			'body'     => $body,
			'template' => $template,
			'extra'    => $extra,
			'motivo'   => $motivo,
			'creado'   => (string) current_time( 'mysql' ),
		);

		// Se guardan los últimos 200: una lista sin tope en una opción es una bomba de relojería.
		if ( count( $pending ) > 200 ) {
			$pending = array_slice( $pending, -200 );
		}

		update_option( self::PENDING_OPTION, $pending, false );

		if ( class_exists( '\Convoca\Core\Logger' ) ) {
			\Convoca\Core\Logger::warning(
				sprintf( 'Aviso "%s" apuntado como pendiente (%s).', $subject, $motivo ),
				'Convoca Courses',
				$user_id
			);
		}
	}

	/**
	 * Avisos pendientes de envío.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function pending(): array {
		$pending = get_option( self::PENDING_OPTION, array() );

		return is_array( $pending ) ? $pending : array();
	}

	/**
	 * Vacía la lista de pendientes (cuando ya se han reenviado o se descartan a mano).
	 *
	 * @return void
	 */
	public static function clear_pending(): void {
		delete_option( self::PENDING_OPTION );
	}
}
