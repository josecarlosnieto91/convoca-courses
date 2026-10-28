<?php
/**
 * API pública del módulo: shortcodes.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes de portada, índice y lección.
 */
class Shortcodes {

	/**
	 * Evita que la portada se llame a sí misma al expandir el contenido del curso.
	 *
	 * Sin esta guarda, un `[convoca_curso]` dentro del propio curso desborda la pila: `the_content`
	 * vuelve a ejecutar el shortcode y no hay tope.
	 *
	 * @var bool
	 */
	private static bool $renderizando = false;

	/**
	 * Mapa lección → página, para no consultar una vez por lección.
	 *
	 * @var array<int, int>|null
	 */
	private static ?array $mapa_lecciones = null;

	/**
	 * Registra los shortcodes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'convoca_curso', array( __CLASS__, 'course' ) );
		add_shortcode( 'convoca_curso_lecciones', array( __CLASS__, 'lessons' ) );
		add_shortcode( 'convoca_curso_leccion', array( __CLASS__, 'lesson' ) );
		add_shortcode( 'convoca_curso_examen', array( __CLASS__, 'exam' ) );
		add_shortcode( 'convoca_curso_tareas', array( __CLASS__, 'tasks' ) );
		add_shortcode( 'convoca_curso_notas', array( __CLASS__, 'grades' ) );
		add_shortcode( 'convoca_curso_acta', array( __CLASS__, 'gradebook' ) );
	}

	/**
	 * Mis notas del curso.
	 *
	 * @param array<string, string>|string $atts Atributos (`id` = curso).
	 * @return string
	 */
	public static function grades( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_notas'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		$user_id = get_current_user_id();
		$notas   = Gradebook::for_user( $user_id, $course_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del aviso a pintar.
		$aviso = isset( $_GET['convoca_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) : '';

		return Certificate::render_template(
			'notas.php',
			array(
				'course_id' => $course_id,
				'logged_in' => $user_id > 0,
				'enrolled'  => Access::is_enrolled( $user_id, $course_id ),
				'pieces'    => $notas['piezas'],
				'final'     => $notas['nota'],
				'weight'    => $notas['peso'],
				'minimum'   => $notas['minimo'],
				'passed'    => $notas['aprobado'],
				'complete'  => $notas['completo'],
				'notice'    => '' === $aviso ? '' : Handlers::message( $aviso ),
			),
			false
		);
	}

	/**
	 * Acta del curso: tabla del profesorado con la nota de cada alumno y su CSV.
	 *
	 * @param array<string, string>|string $atts Atributos (`id` = curso).
	 * @return string
	 */
	public static function gradebook( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_acta'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		if ( ! Access::can_manage() ) {
			return '<div class="convoca-courses"><div class="convoca-courses-notice convoca-courses-notice--locked">'
				. esc_html__( 'El acta de notas solo la ve quien gestiona el curso.', 'convoca-courses' )
				. '</div></div>';
		}

		$pesos = array();
		foreach ( Gradebook::pieces( $course_id ) as $pieza ) {
			$pesos[] = (float) $pieza['peso'];
		}

		return Certificate::render_template(
			'acta.php',
			array(
				'course_id'   => $course_id,
				'rows'        => Gradebook::rows( $course_id ),
				'weights_sum' => array_sum( $pesos ),
				'weights_ok'  => Gradebook::weights_ok( $pesos ),
				'csv_url'     => Handlers::gradebook_csv_url( $course_id ),
			),
			false
		);
	}

	/**
	 * Tareas del curso: entrega, estado y corrección.
	 *
	 * @param array<string, string>|string $atts Atributos (`id` = curso).
	 * @return string
	 */
	public static function tasks( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_tareas'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		return Certificate::render_template( 'tareas.php', Task_View::data( $course_id, get_current_user_id() ), false );
	}

	/**
	 * Examen del curso: abrir intento, responder y ver el resultado.
	 *
	 * @param array<string, string>|string $atts Atributos (`id` = examen).
	 * @return string
	 */
	public static function exam( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_examen'
		);

		$exam_id = (int) $atts['id'];
		if ( ! Hierarchy::is_type( $exam_id, CPT_Examen::TYPE ) ) {
			return self::error_box();
		}

		return Certificate::render_template( 'examen.php', Exam_View::data( $exam_id, get_current_user_id() ), false );
	}

	/**
	 * Portada del curso.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function course( $atts ): string {
		if ( self::$renderizando ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		self::$renderizando = true;
		$html               = self::render_course( $course_id );
		self::$renderizando = false;

		return $html;
	}

	/**
	 * Pinta la portada de un curso ya resuelto.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	private static function render_course( int $course_id ): string {
		$user_id = get_current_user_id();
		$state   = Access::summary( $user_id, $course_id );
		$free    = Enrollment::free_seats( $course_id );
		$precio  = Pricing::for_user( $course_id, $user_id );

		$enrollment_id = Enrollment::find( $user_id, $course_id );
		$status        = $enrollment_id > 0 ? CPT_Matricula::status( $enrollment_id ) : '';
		$payment_url   = $enrollment_id > 0 ? Course_Payment::payment_url( $enrollment_id ) : '';
		$receipt_url   = $enrollment_id > 0 ? Course_Payment::receipt_url( $enrollment_id ) : '';

		return Certificate::render_template(
			'curso.php',
			array(
				'course_id'         => $course_id,
				'title'             => (string) get_the_title( $course_id ),
				'content'           => (string) apply_filters( 'the_content', self::contenido_del_curso( $course_id ) ),
				'price'             => (float) $precio['precio'] > 0 ? CPT_Curso::format_amount( (float) $precio['precio'], CPT_Curso::currency( $course_id ) ) : '',
				'price_reason'      => Pricing::label( (string) $precio['motivo'] ),
				'seats'             => $free,
				'enrolled'          => $state['enrolled'],
				'progress'          => $state,
				'certificate'       => CPT_Curso::issues_certificate( $course_id ),
				'modules'           => self::modules_data( $course_id, $user_id ),
				'logged_in'         => $user_id > 0,
				'enroll_url'        => Handlers::enroll_form_action(),
				'enroll_nonce'      => Handlers::enroll_nonce(),
				'needs_payment'     => Course_Payment::course_needs_payment( $course_id, $user_id ),
				'enrollment_status' => $status,
				'payment_url'       => $payment_url,
			),
			false
		);
	}

	/**
	 * Índice de lecciones con progreso.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function lessons( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_lecciones'
		);

		$course_id = self::resolve_course_id( (int) $atts['id'] );
		if ( 0 === $course_id ) {
			return self::error_box();
		}

		$user_id = get_current_user_id();

		return Certificate::render_template(
			'lecciones.php',
			array(
				'course_id' => $course_id,
				'title'     => (string) get_the_title( $course_id ),
				'enrolled'  => Access::is_enrolled( $user_id, $course_id ),
				'progress'  => Access::summary( $user_id, $course_id ),
				'modules'   => self::modules_data( $course_id, $user_id ),
			),
			false
		);
	}

	/**
	 * Render de una lección.
	 *
	 * @param array<string, string>|string $atts Atributos.
	 * @return string
	 */
	public static function lesson( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			$atts,
			'convoca_curso_leccion'
		);

		$lesson_id = (int) $atts['id'];
		if ( ! Hierarchy::is_lesson( $lesson_id ) ) {
			return self::error_box();
		}

		$user_id   = get_current_user_id();
		$course_id = Hierarchy::course_of_lesson( $lesson_id );

		if ( ! Access::can_view_lesson( $user_id, $lesson_id ) ) {
			return Certificate::render_template(
				'leccion-bloqueada.php',
				array(
					'course_id'  => $course_id,
					'title'      => (string) get_the_title( $lesson_id ),
					'enrolled'   => Access::is_enrolled( $user_id, $course_id ),
					'course_url' => $course_id > 0 ? (string) get_permalink( $course_id ) : '',
				),
				false
			);
		}

		$type       = CPT_Leccion::type( $lesson_id );
		$attachment = CPT_Leccion::get_int( $lesson_id, 'adjunto_id', 0 );
		$video      = array(
			'src'      => '',
			'external' => false,
		);

		$video_allowed = License::video_allowed();

		if ( 'video' === $type && $video_allowed ) {
			$video = Video::lesson_source( $lesson_id );
		}

		return Certificate::render_template(
			'leccion.php',
			array(
				'lesson_id'      => $lesson_id,
				'course_id'      => $course_id,
				'title'          => (string) get_the_title( $lesson_id ),
				'type'           => $type,
				'content'        => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $lesson_id ) ),
				'attachment_id'  => $attachment,
				'attachment'     => $attachment > 0 ? (string) wp_get_attachment_url( $attachment ) : '',
				'video'          => $video,
				'video_allowed'  => $video_allowed,
				'completed'      => Progress::is_completed( $user_id, $lesson_id ),
				'complete_url'   => Handlers::complete_form_action(),
				'complete_nonce' => Handlers::complete_nonce( $lesson_id ),
				'duration'       => CPT_Leccion::get_int( $lesson_id, 'duracion', 0 ),
			),
			false
		);
	}

	/**
	 * Datos de módulos y lecciones para las plantillas.
	 *
	 * @param int $course_id Curso.
	 * @param int $user_id   Persona.
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Contenido del curso listo para `the_content`, sin la portada dentro.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	private static function contenido_del_curso( int $course_id ): string {
		$contenido = (string) get_post_field( 'post_content', $course_id );

		// Solo el shortcode de portada `[convoca_curso ...]`; `[convoca_curso_lecciones]` y compañía se quedan.
		return (string) preg_replace( '/\[convoca_curso(?=[\s\]])[^\]]*\]/i', '', $contenido );
	}

	/**
	 * URL de una lección, si alguna página la publica.
	 *
	 * Se busca la página que contiene `[convoca_curso_leccion id="N"]`, igual que el resto del
	 * ecosistema resuelve páginas por shortcode. Si no existe, se devuelve cadena vacía: mejor un
	 * título sin enlace que un enlace muerto.
	 *
	 * @param int $lesson_id Lección.
	 * @return string
	 */
	public static function lesson_url( int $lesson_id ): string {
		return Lesson_Links::url( $lesson_id );
	}

	/**
	 * Página que publica una lección según el contenido, para el contenido anterior al metadato.
	 *
	 * @param int $lesson_id Lección.
	 * @return int
	 */
	public static function pagina_por_contenido( int $lesson_id ): int {
		$mapa = self::mapa_lecciones();

		return isset( $mapa[ $lesson_id ] ) ? (int) $mapa[ $lesson_id ] : 0;
	}

	/**
	 * Construye el mapa lección → página leyendo el contenido, una sola vez.
	 *
	 * Respaldo para el contenido anterior al metadato `_convoca_pagina_id`.
	 *
	 * @return array<int, int>
	 */
	private static function mapa_lecciones(): array {
		if ( null !== self::$mapa_lecciones ) {
			return self::$mapa_lecciones;
		}

		self::$mapa_lecciones = array();

		global $wpdb;

		// El espacio final deja fuera [convoca_curso_lecciones]: el índice del curso no es una lección.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- una lectura por petición, cacheada en el mapa.
		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s",
				'%[convoca_curso_leccion %'
			)
		);

		foreach ( (array) $filas as $fila ) {
			if ( ! preg_match_all( '/\[convoca_curso_leccion\b[^\]]*id\s*=\s*["\'](\d+)["\']/', (string) $fila->post_content, $coincidencias ) ) {
				continue;
			}

			foreach ( $coincidencias[1] as $id ) {
				$id = (int) $id;
				if ( $id > 0 && ! isset( self::$mapa_lecciones[ $id ] ) ) {
					self::$mapa_lecciones[ $id ] = (int) $fila->ID;
				}
			}
		}

		return self::$mapa_lecciones;
	}

	/**
	 * Datos de los módulos y sus lecciones para el índice y la portada.
	 *
	 * @param int $course_id Curso.
	 * @param int $user_id   Usuario.
	 * @return array<int, array<string, mixed>>
	 */
	public static function modules_data( int $course_id, int $user_id ): array {
		$modules = array();
		$done    = Progress::completed_lesson_ids( $user_id, $course_id );
		$index   = 0;

		foreach ( CPT_Modulo::of_course( $course_id ) as $module ) {
			$lessons = array();

			foreach ( CPT_Leccion::of_module( (int) $module->ID ) as $lesson ) {
				++$index;
				$lessons[] = array(
					'id'        => (int) $lesson->ID,
					'title'     => (string) $lesson->post_title,
					'type'      => CPT_Leccion::type( (int) $lesson->ID ),
					'duration'  => CPT_Leccion::get_int( (int) $lesson->ID, 'duracion', 0 ),
					'completed' => in_array( (int) $lesson->ID, $done, true ),
					'locked'    => ! Access::is_unlocked( $user_id, $course_id, (int) $lesson->ID ),
					'number'    => $index,
					'url'       => self::lesson_url( (int) $lesson->ID ),
				);
			}

			$modules[] = array(
				'id'      => (int) $module->ID,
				'title'   => (string) $module->post_title,
				'content' => (string) $module->post_content,
				'lessons' => $lessons,
			);
		}

		return $modules;
	}

	/**
	 * Resuelve el curso: el atributo manda; si falta, el curso de la entrada actual.
	 *
	 * @param int $course_id Identificador del atributo.
	 * @return int
	 */
	private static function resolve_course_id( int $course_id ): int {
		if ( Hierarchy::is_course( $course_id ) ) {
			return $course_id;
		}

		$current = get_the_ID();
		if ( is_int( $current ) && Hierarchy::is_course( $current ) ) {
			return $current;
		}

		return 0;
	}

	/**
	 * Aviso cuando falta el curso o la lección.
	 *
	 * @return string
	 */
	private static function error_box(): string {
		return '<div class="convoca-courses-notice convoca-courses-notice--error">'
			. esc_html__( 'No se ha encontrado el curso o la lección indicados.', 'convoca-courses' )
			. '</div>';
	}
}
