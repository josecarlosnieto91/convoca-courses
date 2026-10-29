<?php
/**
 * Libro de notas: pesos, nota final y acta.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notas del curso.
 *
 * La nota final **no se guarda**: se calcula. Lo único que se congela es la que va al certificado,
 * porque un certificado no puede cambiar de nota después de emitirse.
 *
 * La parte de cálculo es pura y se prueba sin WordPress.
 */
class Gradebook {

	/**
	 * Nota final a partir de las piezas con su peso.
	 *
	 * Cada pieza trae su peso en la nota final (porcentaje) y su nota (0-100). Las piezas sin nota
	 * todavía no cuentan: la media se reparte entre las que sí la tienen, para no hundir a nadie por
	 * algo que el profesorado aún no ha corregido.
	 *
	 * @param array<int, array{peso: float, nota: float|null}> $pieces Piezas.
	 * @return array{nota: float|null, peso: float, completo: bool, piezas: int}
	 */
	public static function final( array $pieces ): array {
		$suma_pesos = 0.0;
		$acumulado  = 0.0;
		$con_nota   = 0;
		$peso_total = 0.0;

		foreach ( $pieces as $pieza ) {
			$peso        = max( 0.0, (float) $pieza['peso'] );
			$peso_total += $peso;
			$nota        = $pieza['nota'];

			if ( null === $nota || $peso <= 0.0 ) {
				continue;
			}

			$nota = CPT_Curso::normalize_score( (float) $nota );

			$suma_pesos += $peso;
			$acumulado  += $peso * $nota;
			++$con_nota;
		}

		return array(
			'nota'     => $suma_pesos > 0.0 ? round( $acumulado / $suma_pesos, 2 ) : null,
			'peso'     => round( $suma_pesos, 2 ),
			'completo' => $peso_total > 0.0 && $suma_pesos + 0.01 >= $peso_total,
			'piezas'   => $con_nota,
		);
	}

	/**
	 * ¿Los pesos de las piezas suman 100?
	 *
	 * Función pura. Se admite el margen del redondeo (0,01) para no rechazar 33,33 + 33,33 + 33,34.
	 *
	 * @param array<int, float> $weights Pesos.
	 * @return bool
	 */
	public static function weights_ok( array $weights ): bool {
		$suma = 0.0;

		foreach ( $weights as $peso ) {
			$suma += max( 0.0, (float) $peso );
		}

		return abs( $suma - 100.0 ) < 0.02;
	}

	/**
	 * ¿La nota llega al mínimo?
	 *
	 * Función pura: sin nota no se aprueba.
	 *
	 * @param float|null $nota    Nota final.
	 * @param float      $minimum Mínimo del curso.
	 * @return bool
	 */
	public static function passed( ?float $nota, float $minimum ): bool {
		if ( null === $nota ) {
			return false;
		}

		return $nota + 0.0001 >= $minimum;
	}

	/**
	 * Piezas de evaluación de un curso: exámenes y tareas con su peso.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function pieces( int $course_id ): array {
		$piezas = array();

		foreach ( CPT_Examen::of_course( $course_id ) as $examen ) {
			$piezas[] = array(
				'tipo'   => 'examen',
				'id'     => (int) $examen->ID,
				'titulo' => (string) $examen->post_title,
				'peso'   => CPT_Examen::weight( (int) $examen->ID ),
			);
		}

		foreach ( CPT_Tarea::of_course( $course_id ) as $tarea ) {
			$piezas[] = array(
				'tipo'   => 'tarea',
				'id'     => (int) $tarea->ID,
				'titulo' => (string) $tarea->post_title,
				'peso'   => CPT_Tarea::weight( (int) $tarea->ID ),
			);
		}

		return $piezas;
	}

	/**
	 * Nota final de una persona en un curso, con el detalle por pieza.
	 *
	 * @param int $user_id   Persona.
	 * @param int $course_id Curso.
	 * @return array{nota: float|null, peso: float, completo: bool, minimo: float, aprobado: bool, piezas: array<int, array<string, mixed>>}
	 */
	public static function for_user( int $user_id, int $course_id ): array {
		$notas_examen = Attempts::best_by_exam( $user_id, $course_id );
		$notas_tarea  = Task::grades_by_task( $user_id, $course_id );
		$piezas       = array();

		foreach ( self::pieces( $course_id ) as $pieza ) {
			$id   = (int) $pieza['id'];
			$nota = null;

			$nota = self::resolve_nota(
				(string) $pieza['tipo'],
				$id,
				'tarea' === $pieza['tipo'] ? CPT_Tarea::exam_id( $id ) : 0,
				$notas_examen,
				$notas_tarea
			);

			$piezas[] = array(
				'tipo'   => (string) $pieza['tipo'],
				'id'     => $id,
				'titulo' => (string) $pieza['titulo'],
				'peso'   => (float) $pieza['peso'],
				'nota'   => $nota,
			);
		}

		$final  = self::final( $piezas );
		$minimo = CPT_Curso::min_score( $course_id );

		return array(
			'nota'     => $final['nota'],
			'peso'     => $final['peso'],
			'completo' => $final['completo'],
			'minimo'   => $minimo,
			'aprobado' => self::passed( $final['nota'], $minimo ),
			'piezas'   => $piezas,
		);
	}

	/**
	 * Nota de una pieza del acta.
	 *
	 * Una tarea con examen vinculado es un test: manda la nota del examen. Si no lo tiene, o no se ha hecho,
	 * se mira la corrección manual, para no romper las tareas antiguas.
	 *
	 * @param string            $tipo         'examen' o 'tarea'.
	 * @param int               $id           Identificador de la pieza.
	 * @param int               $examen_id    Examen que corrige la tarea (0 si ninguno).
	 * @param array<int, float> $notas_examen Notas por examen.
	 * @param array<int, float> $notas_tarea  Notas por tarea.
	 * @return float|null
	 */
	public static function resolve_nota( string $tipo, int $id, int $examen_id, array $notas_examen, array $notas_tarea ): ?float {
		if ( 'examen' === $tipo ) {
			return isset( $notas_examen[ $id ] ) ? (float) $notas_examen[ $id ] : null;
		}

		if ( 'tarea' === $tipo ) {
			if ( $examen_id > 0 && isset( $notas_examen[ $examen_id ] ) ) {
				return (float) $notas_examen[ $examen_id ];
			}

			return isset( $notas_tarea[ $id ] ) ? (float) $notas_tarea[ $id ] : null;
		}

		return null;
	}

	/**
	 * ¿Este curso evalúa?
	 *
	 * Un curso sin exámenes ni tareas se comporta como siempre: certificado por progreso.
	 *
	 * @param int $course_id Curso.
	 * @return bool
	 */
	public static function has_evaluation( int $course_id ): bool {
		return ! empty( self::pieces( $course_id ) );
	}

	/**
	 * Filas del acta: una por alumno.
	 *
	 * @param int $course_id Curso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( int $course_id ): array {
		$filas = array();

		foreach ( Roster::students( $course_id ) as $alumno ) {
			$nota    = self::for_user( (int) $alumno['user_id'], $course_id );
			$filas[] = array(
				'user_id'  => (int) $alumno['user_id'],
				'nombre'   => (string) $alumno['nombre'],
				'correo'   => (string) $alumno['correo'],
				'progreso' => (float) $alumno['progreso'],
				'nota'     => $nota['nota'],
				'aprobado' => $nota['aprobado'],
				'completo' => $nota['completo'],
			);
		}

		return $filas;
	}

	/**
	 * CSV del acta, con cabecera.
	 *
	 * Función pura: recibe las filas y devuelve el texto. Se prueba sin WordPress.
	 *
	 * @param array<int, array<string, mixed>> $rows Filas del acta.
	 * @return string
	 */
	public static function csv( array $rows ): string {
		$lineas   = array();
		$lineas[] = self::csv_line( array( 'Alumno', 'Correo', 'Progreso %', 'Nota final', 'Aprobado', 'Nota completa' ) );

		foreach ( $rows as $fila ) {
			$valor = $fila['nota'];
			$nota  = null === $valor ? '' : number_format( (float) $valor, 2, '.', '' );

			$lineas[] = self::csv_line(
				array(
					(string) ( $fila['nombre'] ?? '' ),
					(string) ( $fila['correo'] ?? '' ),
					number_format( (float) ( $fila['progreso'] ?? 0 ), 1, '.', '' ),
					$nota,
					! empty( $fila['aprobado'] ) ? 'sí' : 'no',
					! empty( $fila['completo'] ) ? 'sí' : 'no',
				)
			);
		}

		return implode( "\r\n", $lineas ) . "\r\n";
	}

	/**
	 * Una línea CSV, escapada como pide el formato.
	 *
	 * @param array<int, string> $fields Campos.
	 * @return string
	 */
	public static function csv_line( array $fields ): string {
		$salida = array();

		foreach ( $fields as $field ) {
			$valor    = (string) $field;
			$salida[] = '"' . str_replace( '"', '""', $valor ) . '"';
		}

		return implode( ';', $salida );
	}
}
