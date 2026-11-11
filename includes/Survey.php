<?php
/**
 * Encuesta de calidad por curso.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encuesta vinculada al alumno, al completar el curso.
 */
class Survey {

	/**
	 * Meta del curso.
	 */
	public const META = '_convoca_encuesta';

	/**
	 * Acción del formulario.
	 */
	public const ACTION = 'convoca_courses_survey';

	/**
	 * Shortcode.
	 */
	public const SHORTCODE = 'convoca_encuesta_curso';

	/**
	 * Preguntas por defecto (Likert 1-5).
	 */
	public const DEFAULT_PREGUNTAS = array(
		'El contenido ha respondido a lo que esperaba.',
		'El material y las actividades han sido claros.',
		'El ritmo del curso ha sido adecuado.',
		'La plataforma ha sido fácil de usar.',
		'Recomendaría este curso.',
	);

	/**
	 * Engancha el shortcode, el formulario y el metabox.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . CPT_Curso::TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Configuración de la encuesta de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array{enabled:bool, titulo:string, preguntas:array<int,string>, comentario:bool, obligatoria:bool}
	 */
	public static function config( int $course_id ): array {
		$meta = get_post_meta( $course_id, self::META, true );
		$meta = is_array( $meta ) ? $meta : array();

		$preguntas = isset( $meta['preguntas'] ) && is_array( $meta['preguntas'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $meta['preguntas'] ) ) ) : array();

		return array(
			'enabled'     => ! empty( $meta['enabled'] ),
			'titulo'      => isset( $meta['titulo'] ) && '' !== (string) $meta['titulo'] ? (string) $meta['titulo'] : __( 'Encuesta de calidad', 'convoca-courses' ),
			'preguntas'   => array() !== $preguntas ? $preguntas : self::DEFAULT_PREGUNTAS,
			'comentario'  => ! isset( $meta['comentario'] ) || ! empty( $meta['comentario'] ),
			'obligatoria' => ! empty( $meta['obligatoria'] ),
		);
	}

	/**
	 * Convierte un textarea en preguntas.
	 *
	 * @param string $texto Texto, una pregunta por línea.
	 * @return array<int, string>
	 */
	public static function normalizar_preguntas( string $texto ): array {
		$lineas = preg_split( '/\r\n|\r|\n/', $texto );
		$salida = array();

		foreach ( (array) $lineas as $linea ) {
			$linea = trim( sanitize_text_field( $linea ) );
			if ( '' !== $linea ) {
				$salida[] = $linea;
			}
		}

		return array() !== $salida ? array_values( array_unique( $salida ) ) : self::DEFAULT_PREGUNTAS;
	}

	/**
	 * Shortcode de la encuesta.
	 *
	 * @param array<string, mixed> $atts Atributos.
	 * @return string
	 */
	public static function shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );

		return self::render( absint( $atts['id'] ) );
	}

	/**
	 * Pinta la encuesta si el curso la tiene activa y la persona la ha terminado.
	 *
	 * @param int $course_id Curso.
	 * @return string
	 */
	public static function render( int $course_id ): string {
		if ( $course_id <= 0 || ! Pro::enabled( 'encuesta_calidad' ) ) {
			return '';
		}

		$config = self::config( $course_id );
		if ( ! $config['enabled'] ) {
			return '';
		}

		$user_id      = get_current_user_id();
		$identidad_id = Identidad::actual();

		if ( ! Access::is_enrolled( $user_id, $course_id ) ) {
			return '';
		}

		$progreso = $identidad_id > 0
			? Progress::for_course_identidad( $identidad_id, $course_id )
			: Progress::for_course( $user_id, $course_id );

		if ( (float) ( $progreso['percent'] ?? 0 ) < 100 ) {
			return '';
		}

		$respuesta = self::respuesta( $course_id, $user_id, $identidad_id );
		$html      = '<section class="convoca-courses__survey"><h2 class="convoca-courses__subtitle">' . esc_html( $config['titulo'] ) . '</h2>';

		if ( null !== $respuesta ) {
			$html .= '<p class="convoca-courses-notice">' . esc_html__( 'Gracias. Tu respuesta ya está registrada.', 'convoca-courses' ) . '</p></section>';
			return $html;
		}

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$html .= '<input type="hidden" name="course_id" value="' . esc_attr( (string) $course_id ) . '" />';
		$html .= wp_nonce_field( self::ACTION . '_' . $course_id, '_convoca_nonce', true, false );

		foreach ( $config['preguntas'] as $indice => $pregunta ) {
			$html .= '<fieldset class="convoca-courses__survey-question"><legend>' . esc_html( $pregunta ) . '</legend>';
			for ( $valor = 1; $valor <= 5; $valor++ ) {
				$html .= '<label><input type="radio" name="q' . (int) $indice . '" value="' . (int) $valor . '" ' . ( 5 === $valor ? 'required' : '' ) . ' /> ' . (int) $valor . '</label>';
			}
			$html .= '</fieldset>';
		}

		if ( $config['comentario'] ) {
			$html .= '<p><label for="convoca-survey-comment">' . esc_html__( 'Comentario (opcional)', 'convoca-courses' ) . '</label>';
			$html .= '<textarea id="convoca-survey-comment" name="comentario" rows="4" class="large-text"></textarea></p>';
		}

		$html .= '<p><button type="submit" class="button convoca-courses__button">' . esc_html__( 'Enviar encuesta', 'convoca-courses' ) . '</button></p>';
		$html .= '</form></section>';

		return $html;
	}

	/**
	 * Procesa el formulario.
	 *
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- se comprueba justo después.
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( (string) $_POST['course_id'] ) ) : 0;
		$nonce     = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $course_id <= 0 || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $course_id ) || ! Pro::enabled( 'encuesta_calidad' ) ) {
			wp_die( esc_html__( 'La petición ha caducado.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		if ( ! Access::is_enrolled( get_current_user_id(), $course_id ) ) {
			wp_die( esc_html__( 'No tienes acceso a este curso.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		$identidad_id = Identidad::actual();
		$progreso     = $identidad_id > 0
			? Progress::for_course_identidad( $identidad_id, $course_id )
			: Progress::for_course( get_current_user_id(), $course_id );

		if ( (float) ( $progreso['percent'] ?? 0 ) < 100 ) {
			wp_die( esc_html__( 'La encuesta se abre al completar el curso.', 'convoca-courses' ), '', array( 'response' => 403 ) );
		}

		$config     = self::config( $course_id );
		$respuestas = array();
		foreach ( $config['preguntas'] as $indice => $pregunta ) {
			$clave = 'q' . (int) $indice;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ya verificado arriba.
			$valor = isset( $_POST[ $clave ] ) ? absint( wp_unslash( (string) $_POST[ $clave ] ) ) : 0;
			if ( ! in_array( $valor, array( 1, 2, 3, 4, 5 ), true ) ) {
				wp_die( esc_html__( 'Faltan respuestas obligatorias.', 'convoca-courses' ), '', array( 'response' => 400 ) );
			}
			$respuestas[] = $valor;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ya verificado arriba.
		$comentario = isset( $_POST['comentario'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['comentario'] ) ) : '';

		self::guardar( $course_id, $respuestas, $comentario );

		$destino = (string) get_permalink( $course_id );
		if ( '' === $destino ) {
			$destino = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'convoca_msg', 'survey_ok', $destino ) );
		exit;
	}

	/**
	 * Guarda o actualiza una respuesta.
	 *
	 * @param int            $course_id  Curso.
	 * @param array<int,int> $respuestas Respuestas 1-5.
	 * @param string         $comentario Comentario.
	 * @return void
	 */
	public static function guardar( int $course_id, array $respuestas, string $comentario = '' ): void {
		global $wpdb;

		$user_id      = get_current_user_id();
		$identidad_id = Identidad::actual();
		$tabla        = $wpdb->prefix . Installer::SURVEYS_TABLE;
		$ahora        = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia del plugin.
		$wpdb->replace(
			$tabla,
			array(
				'course_id'    => $course_id,
				'user_id'      => $user_id,
				'identidad_id' => $identidad_id,
				'answers'      => (string) wp_json_encode( array_map( 'absint', $respuestas ) ),
				'comentario'   => $comentario,
				'created_utc'  => $ahora,
				'updated_utc'  => $ahora,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Respuesta de una persona.
	 *
	 * @param int $course_id    Curso.
	 * @param int $user_id      Usuario.
	 * @param int $identidad_id Identidad.
	 * @return array<string, mixed>|null
	 */
	public static function respuesta( int $course_id, int $user_id, int $identidad_id = 0 ): ?array {
		global $wpdb;

		$tabla = $wpdb->prefix . Installer::SURVEYS_TABLE;
		$where = $identidad_id > 0 ? $wpdb->prepare( 'identidad_id = %d', $identidad_id ) : $wpdb->prepare( 'user_id = %d', $user_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$fila = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE course_id = %d AND {$where} LIMIT 1", $course_id ), 'ARRAY_A' );

		if ( ! is_array( $fila ) ) {
			return null;
		}

		$fila['answers'] = json_decode( (string) $fila['answers'], true );

		return $fila;
	}

	/**
	 * Resultados agregados de un curso.
	 *
	 * @param int $course_id Curso.
	 * @return array{total:int, medias:array<int,float>, comentarios:array<int,string>}
	 */
	public static function results( int $course_id ): array {
		global $wpdb;

		$tabla = $wpdb->prefix . Installer::SURVEYS_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia; solo lectura.
		$filas = $wpdb->get_results( $wpdb->prepare( "SELECT answers, comentario FROM {$tabla} WHERE course_id = %d ORDER BY id ASC", $course_id ), 'ARRAY_A' );

		return self::agregar( is_array( $filas ) ? $filas : array() );
	}

	/**
	 * Agrega respuestas ya decodificadas o JSON.
	 *
	 * @param array<int, array<string, mixed>> $filas Filas.
	 * @return array{total:int, medias:array<int,float>, comentarios:array<int,string>}
	 */
	public static function agregar( array $filas ): array {
		$sumas       = array();
		$cuentas     = array();
		$comentarios = array();

		foreach ( $filas as $fila ) {
			$answers = $fila['answers'] ?? array();
			if ( is_string( $answers ) ) {
				$answers = json_decode( $answers, true );
			}
			if ( ! is_array( $answers ) ) {
				continue;
			}

			foreach ( array_values( $answers ) as $i => $valor ) {
				$valor = (int) $valor;
				if ( $valor >= 1 && $valor <= 5 ) {
					$sumas[ $i ]   = (int) ( $sumas[ $i ] ?? 0 ) + $valor;
					$cuentas[ $i ] = (int) ( $cuentas[ $i ] ?? 0 ) + 1;
				}
			}

			$comentario = trim( (string) ( $fila['comentario'] ?? '' ) );
			if ( '' !== $comentario ) {
				$comentarios[] = $comentario;
			}
		}

		$medias = array();
		foreach ( $sumas as $i => $suma ) {
			$medias[ $i ] = round( $suma / max( 1, $cuentas[ $i ] ), 2 );
		}

		return array(
			'total'       => count( $filas ),
			'medias'      => $medias,
			'comentarios' => $comentarios,
		);
	}

	/**
	 * Metabox del curso.
	 *
	 * @return void
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			'convoca-courses-survey',
			__( 'Encuesta de calidad (PRO)', 'convoca-courses' ),
			array( __CLASS__, 'render_meta_box' ),
			CPT_Curso::TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Pinta el metabox.
	 *
	 * @param \WP_Post $post Curso.
	 * @return void
	 */
	public static function render_meta_box( $post ): void {
		$config = self::config( (int) $post->ID );
		wp_nonce_field( 'convoca_survey_meta', '_convoca_survey_nonce' );

		echo '<p><label><input type="checkbox" name="convoca_survey_enabled" value="1" ' . checked( $config['enabled'], true, false ) . ' /> ' . esc_html__( 'Activar encuesta al completar', 'convoca-courses' ) . '</label></p>';
		echo '<p><label>' . esc_html__( 'Título', 'convoca-courses' ) . '<input type="text" class="widefat" name="convoca_survey_titulo" value="' . esc_attr( $config['titulo'] ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Preguntas (una por línea)', 'convoca-courses' ) . '<textarea class="widefat" rows="7" name="convoca_survey_preguntas">' . esc_textarea( implode( "\n", $config['preguntas'] ) ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="convoca_survey_comentario" value="1" ' . checked( $config['comentario'], true, false ) . ' /> ' . esc_html__( 'Pedir comentario', 'convoca-courses' ) . '</label></p>';
	}

	/**
	 * Guarda el metabox.
	 *
	 * @param int      $post_id Curso.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function save_meta( int $post_id, $post ): void {
		unset( $post );

		if ( ! isset( $_POST['_convoca_survey_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_convoca_survey_nonce'] ) ), 'convoca_survey_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta(
			$post_id,
			self::META,
			array(
				'enabled'     => ! empty( $_POST['convoca_survey_enabled'] ),
				'titulo'      => isset( $_POST['convoca_survey_titulo'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_survey_titulo'] ) ) : '',
				'preguntas'   => isset( $_POST['convoca_survey_preguntas'] ) ? self::normalizar_preguntas( sanitize_textarea_field( wp_unslash( (string) $_POST['convoca_survey_preguntas'] ) ) ) : self::DEFAULT_PREGUNTAS,
				'comentario'  => ! empty( $_POST['convoca_survey_comentario'] ),
				'obligatoria' => false,
			)
		);
	}
}
