<?php
/**
 * Corrección de exámenes: selección de preguntas y nota.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lógica pura del examen.
 *
 * Aquí no se toca la base de datos ni WordPress: entrar preguntas y respuestas, salir nota y detalle.
 * Es lo que cuesta dinero (una nota mal puesta se ve en un certificado), así que se prueba sin
 * necesitar un sitio montado.
 */
class Quiz {

	/**
	 * Tipos de pregunta admitidos.
	 */
	public const TIPOS = array( 'unica', 'multiple', 'verdadero_falso' );

	/**
	 * Elige las preguntas de un intento.
	 *
	 * Con `$shuffle` se baraja antes de recortar; sin él se respeta el orden recibido. El barajado se
	 * puede inyectar para que la prueba sea determinista.
	 *
	 * @param array<int, int> $question_ids Preguntas candidatas.
	 * @param int             $n            Cuántas se quieren (0 o menos = todas).
	 * @param bool            $shuffle      Si se barajan.
	 * @param callable|null   $barajar      Función de barajado; por defecto `shuffle`.
	 * @return array<int, int>
	 */
	public static function pick( array $question_ids, int $n, bool $shuffle, ?callable $barajar = null ): array {
		$ids = array_values( array_unique( array_map( 'intval', $question_ids ) ) );

		if ( $shuffle && count( $ids ) > 1 ) {
			if ( null !== $barajar ) {
				$barajadas = (array) call_user_func( $barajar, $ids );
				$ids       = array_values( array_map( 'intval', $barajadas ) );
			} else {
				shuffle( $ids );
			}
		}

		if ( $n > 0 ) {
			$ids = array_slice( $ids, 0, $n );
		}

		return $ids;
	}

	/**
	 * Normaliza una respuesta a una lista de índices de opción ordenada.
	 *
	 * La respuesta llega del formulario tal cual: una cadena para única y V/F, un array para múltiple.
	 *
	 * @param mixed $respuesta Respuesta recibida.
	 * @return array<int, string>
	 */
	public static function normalize_answer( $respuesta ): array {
		if ( is_array( $respuesta ) ) {
			$valores = $respuesta;
		} elseif ( null === $respuesta || '' === $respuesta ) {
			return array();
		} else {
			$valores = array( $respuesta );
		}

		$limpios = array();
		foreach ( $valores as $valor ) {
			if ( is_array( $valor ) ) {
				continue;
			}
			$texto = trim( (string) $valor );
			if ( '' === $texto ) {
				continue;
			}
			$limpios[] = $texto;
		}

		$limpios = array_values( array_unique( $limpios ) );
		sort( $limpios );

		return $limpios;
	}

	/**
	 * Normaliza la lista de respuestas correctas de una pregunta.
	 *
	 * @param mixed $correctas Respuestas correctas guardadas.
	 * @return array<int, string>
	 */
	public static function normalize_correct( $correctas ): array {
		return self::normalize_answer( $correctas );
	}

	/**
	 * ¿La respuesta acierta la pregunta?
	 *
	 * Única y V/F son todo o nada con **una** respuesta. Múltiple exige el conjunto exacto: una de más
	 * anula la pregunta, porque marcar todo no puede puntuar.
	 *
	 * @param array<string, mixed> $pregunta Pregunta.
	 * @param mixed                $respuesta Respuesta dada.
	 * @return bool
	 */
	public static function is_correct( array $pregunta, $respuesta ): bool {
		$tipo      = isset( $pregunta['tipo'] ) ? (string) $pregunta['tipo'] : 'unica';
		$correctas = self::normalize_correct( isset( $pregunta['correctas'] ) ? $pregunta['correctas'] : array() );
		$dadas     = self::normalize_answer( $respuesta );

		if ( empty( $correctas ) || empty( $dadas ) ) {
			return false;
		}

		if ( in_array( $tipo, array( 'unica', 'verdadero_falso' ), true ) ) {
			return 1 === count( $dadas ) && $dadas === $correctas;
		}

		return $dadas === $correctas;
	}

	/**
	 * Corrige un examen entero.
	 *
	 * @param array<int, array<string, mixed>> $preguntas Preguntas del intento.
	 * @param array<int|string, mixed>         $respuestas Respuestas indexadas por identificador de pregunta.
	 * @param float                            $min_score  Nota mínima para aprobar (0-100).
	 * @return array{puntuacion: float, puntos: float, total: float, aprobado: bool, detalle: array<int, array<string, mixed>>}
	 */
	public static function grade( array $preguntas, array $respuestas, float $min_score ): array {
		$detalle = array();
		$puntos  = 0.0;
		$total   = 0.0;

		foreach ( $preguntas as $pregunta ) {
			$id      = isset( $pregunta['id'] ) ? (int) $pregunta['id'] : 0;
			$valor   = isset( $pregunta['puntos'] ) ? (float) $pregunta['puntos'] : 1.0;
			$valor   = max( 0.0, $valor );
			$total  += $valor;
			$dada    = array_key_exists( $id, $respuestas ) ? $respuestas[ $id ] : '';
			$acierta = self::is_correct( $pregunta, $dada );
			$ganados = $acierta ? $valor : 0.0;
			$puntos += $ganados;

			$detalle[] = array(
				'question_id' => $id,
				'correcta'    => $acierta,
				'puntos'      => round( $ganados, 2 ),
				'valor'       => round( $valor, 2 ),
				'respuesta'   => self::normalize_answer( $dada ),
				'esperada'    => self::normalize_correct( isset( $pregunta['correctas'] ) ? $pregunta['correctas'] : array() ),
			);
		}

		$puntuacion = self::scale( $puntos, $total );

		return array(
			'puntuacion' => $puntuacion,
			'puntos'     => round( $puntos, 2 ),
			'total'      => round( $total, 2 ),
			'aprobado'   => self::passed( $puntuacion, $min_score ),
			'detalle'    => $detalle,
		);
	}

	/**
	 * Lleva los puntos obtenidos a la escala 0-100.
	 *
	 * @param float $puntos Puntos conseguidos.
	 * @param float $total  Puntos posibles.
	 * @return float
	 */
	public static function scale( float $puntos, float $total ): float {
		if ( $total <= 0.0 ) {
			return 0.0;
		}

		return round( max( 0.0, min( 100.0, ( $puntos / $total ) * 100 ) ), 2 );
	}

	/**
	 * ¿La nota llega al mínimo? Un examen sin preguntas no se aprueba nunca.
	 *
	 * @param float $puntuacion Nota 0-100.
	 * @param float $min_score  Mínimo.
	 * @return bool
	 */
	public static function passed( float $puntuacion, float $min_score ): bool {
		if ( $puntuacion <= 0.0 ) {
			return false;
		}

		return $puntuacion + 0.0001 >= $min_score;
	}
}
