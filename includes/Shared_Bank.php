<?php
/**
 * Banco de preguntas compartido e importación CSV (PRO).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Llevar preguntas de un curso a otro.
 *
 * El formato es deliberadamente plano y legible, con punto y coma (el Excel en castellano ya separa
 * así): se puede editar en una hoja de cálculo sin pelearse con comillas.
 *
 * Columnas: `tipo;enunciado;opcion1;opcion2;opcion3;opcion4;correctas;puntos;explicacion`
 * `correctas` son números de opción **empezando en 1**, separados por comas.
 */
class Shared_Bank {

	/**
	 * Cabecera del CSV.
	 */
	public const HEADERS = array( 'tipo', 'enunciado', 'opcion1', 'opcion2', 'opcion3', 'opcion4', 'correctas', 'puntos', 'explicacion' );

	/**
	 * ¿Se puede usar?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Pro::enabled( 'banco_compartido' );
	}

	/**
	 * CSV con las preguntas de un examen y del banco de su curso.
	 *
	 * @param int $course_id Curso.
	 * @param int $exam_id   Examen (0 = solo el banco del curso).
	 * @return string|\WP_Error
	 */
	public static function export_csv( int $course_id, int $exam_id = 0 ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'convoca_courses_pro_required', __( 'El banco compartido es una función PRO.', 'convoca-courses' ) );
		}

		$preguntas = Questions::all( $exam_id );

		if ( 0 === $exam_id ) {
			$preguntas = Questions::of_course( $course_id );
		}

		if ( empty( $preguntas ) ) {
			return new \WP_Error( 'convoca_courses_bank_empty', __( 'No hay preguntas que exportar.', 'convoca-courses' ) );
		}

		return self::to_csv( $preguntas );
	}

	/**
	 * CSV a partir de preguntas ya normalizadas.
	 *
	 * Función pura: se prueba sin WordPress.
	 *
	 * @param array<int, array<string, mixed>> $preguntas Preguntas.
	 * @return string
	 */
	public static function to_csv( array $preguntas ): string {
		$lineas   = array();
		$lineas[] = Gradebook::csv_line( self::HEADERS );

		foreach ( $preguntas as $pregunta ) {
			$opciones = array_values( (array) $pregunta['opciones'] );
			$opciones = array_pad( $opciones, 4, '' );

			$correctas = array_map(
				static function ( $indice ) {
					return (string) ( (int) $indice + 1 );
				},
				(array) $pregunta['correctas']
			);

			$lineas[] = Gradebook::csv_line(
				array(
					(string) $pregunta['tipo'],
					(string) $pregunta['enunciado'],
					(string) $opciones[0],
					(string) $opciones[1],
					(string) $opciones[2],
					(string) $opciones[3],
					implode( ',', $correctas ),
					number_format( (float) $pregunta['puntos'], 2, '.', '' ),
					(string) $pregunta['explicacion'],
				)
			);
		}

		return implode( "\\r\\n", $lineas ) . "\\r\\n";
	}

	/**
	 * Lee un CSV y devuelve las preguntas listas para validar.
	 *
	 * Función pura: la validación de cada pregunta la hace `Questions::validate()` después.
	 *
	 * @param string $csv Contenido.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse_csv( string $csv ): array {
		$filas = preg_split( '/\\r\\n|\\n|\\r/', trim( $csv ) );
		if ( ! is_array( $filas ) || empty( $filas ) ) {
			return array();
		}

		$preguntas = array();
		$primera   = true;

		foreach ( $filas as $fila ) {
			$campos = str_getcsv( $fila, ';', '"', '\\\\' );

			if ( $primera ) {
				$primera = false;
				if ( isset( $campos[0] ) && 'tipo' === strtolower( trim( (string) $campos[0] ) ) ) {
					continue;
				}
			}

			$campos = array_pad( array_map( 'trim', array_map( 'strval', $campos ) ), count( self::HEADERS ), '' );

			if ( '' === $campos[1] ) {
				continue;
			}

			$opciones = array();
			for ( $i = 2; $i <= 5; $i++ ) {
				if ( '' !== $campos[ $i ] ) {
					$opciones[] = $campos[ $i ];
				}
			}

			$correctas = array();
			foreach ( explode( ',', $campos[6] ) as $numero ) {
				$numero = (int) trim( $numero );
				if ( $numero > 0 ) {
					$correctas[] = (string) ( $numero - 1 );
				}
			}

			$preguntas[] = array(
				'tipo'        => $campos[0],
				'enunciado'   => $campos[1],
				'opciones'    => $opciones,
				'correctas'   => $correctas,
				'puntos'      => '' !== $campos[7] ? (float) $campos[7] : 1.0,
				'explicacion' => $campos[8],
			);
		}

		return $preguntas;
	}

	/**
	 * Importa preguntas de un CSV a un examen o al banco del curso.
	 *
	 * @param int    $course_id Curso.
	 * @param int    $exam_id   Examen (0 = banco del curso).
	 * @param string $csv       Contenido.
	 * @return array{creadas: int, errores: array<int, string>}|\WP_Error
	 */
	public static function import_csv( int $course_id, int $exam_id, string $csv ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'convoca_courses_pro_required', __( 'El banco compartido es una función PRO.', 'convoca-courses' ) );
		}

		$preguntas = self::parse_csv( $csv );
		if ( empty( $preguntas ) ) {
			return new \WP_Error( 'convoca_courses_bank_parse', __( 'El CSV no tiene preguntas legibles.', 'convoca-courses' ) );
		}

		$creadas = 0;
		$errores = array();

		foreach ( $preguntas as $indice => $pregunta ) {
			$pregunta['course_id'] = $course_id;
			$pregunta['exam_id']   = $exam_id;

			$resultado = Questions::create( $pregunta );

			if ( is_wp_error( $resultado ) ) {
				$errores[] = sprintf( 'Fila %d: %s', $indice + 2, $resultado->get_error_message() );
				continue;
			}

			++$creadas;
		}

		return array(
			'creadas' => $creadas,
			'errores' => $errores,
		);
	}
}
