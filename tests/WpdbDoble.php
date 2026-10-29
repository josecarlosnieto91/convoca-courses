<?php
/**
 * Doble de `$wpdb` para la tabla de identidades.
 *
 * Guarda las filas en memoria y entiende las tres consultas que usa `Identidad`: `insert`, `update` y
 * `get_row` con `prepare`.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

/**
 * Base de datos en memoria.
 */
class WpdbDoble {

	/**
	 * Prefijo de tablas.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Último id insertado.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Filas por tabla.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	public $filas = array();

	/**
	 * Último error.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Devuelve el SQL tal cual: el doble no interpreta marcadores de posición.
	 *
	 * @param string $query Consulta.
	 * @param mixed  ...$args Argumentos.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$query = (string) $query;

		foreach ( $args as $arg ) {
			$valor = is_int( $arg ) ? (string) $arg : "'" . (string) $arg . "'";
			$query = preg_replace( '/%[ds]/', $valor, $query, 1 );
		}

		return $query;
	}

	/**
	 * Inserta una fila.
	 *
	 * @param string                  $tabla Tabla.
	 * @param array<string, mixed>    $datos Datos.
	 * @param array<int, string>|null $formato Formato (se ignora).
	 * @return int
	 */
	public function insert( $tabla, $datos, $formato = null ) {
		unset( $formato );

		$this->filas[ $tabla ] = $this->filas[ $tabla ] ?? array();
		$id                    = count( $this->filas[ $tabla ] ) + 1;

		$datos['id']                  = $id;
		$this->filas[ $tabla ][ $id ] = $datos;
		$this->insert_id              = $id;

		return 1;
	}

	/**
	 * Actualiza filas por id.
	 *
	 * @param string                  $tabla Tabla.
	 * @param array<string, mixed>    $datos Datos.
	 * @param array<string, mixed>    $donde Condición.
	 * @param array<int, string>|null $formato Formato (se ignora).
	 * @param array<int, string>|null $formato_donde Formato (se ignora).
	 * @return int
	 */
	public function update( $tabla, $datos, $donde, $formato = null, $formato_donde = null ) {
		unset( $formato, $formato_donde );

		$id = isset( $donde['id'] ) ? (int) $donde['id'] : 0;

		if ( ! isset( $this->filas[ $tabla ][ $id ] ) ) {
			return 0;
		}

		foreach ( $datos as $campo => $valor ) {
			$this->filas[ $tabla ][ $id ][ $campo ] = $valor;
		}

		return 1;
	}

	/**
	 * Devuelve una fila según la consulta.
	 *
	 * @param string $query Consulta ya preparada.
	 * @param string $salida Formato de salida.
	 * @return array<string, mixed>|null
	 */
	public function get_row( $query, $salida = 'OBJECT' ) {
		unset( $salida );

		$query = (string) $query;

		if ( ! preg_match( '/FROM\s+(\S+)/i', $query, $m ) ) {
			return null;
		}

		$tabla = $m[1];
		$filas = $this->filas[ $tabla ] ?? array();

		if ( preg_match( '/WHERE\s+id\s*=\s*(\d+)/i', $query, $id ) ) {
			$fila = $filas[ (int) $id[1] ] ?? null;

			return is_array( $fila ) ? $fila : null;
		}

		if ( preg_match( "/WHERE\\s+email\\s*=\\s*'([^']*)'/i", $query, $email ) ) {
			foreach ( $filas as $fila ) {
				if ( isset( $fila['email'] ) && $fila['email'] === $email[1] ) {
					return $fila;
				}
			}
		}

		return null;
	}

	/**
	 * Ejecuta una consulta: entiende el INSERT IGNORE del progreso.
	 *
	 * @param string $query Consulta ya preparada.
	 * @return int|false
	 */
	public function query( $query ) {
		$query = (string) $query;

		if ( preg_match( '/INSERT IGNORE INTO\s+(\S+)\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $query, $m ) ) {
			$tabla    = $m[1];
			$columnas = array_map( 'trim', explode( ',', $m[2] ) );
			$valores  = array_map( 'trim', explode( ',', $m[3] ) );

			$this->filas[ $tabla ] = $this->filas[ $tabla ] ?? array();
			$id                    = count( $this->filas[ $tabla ] ) + 1;

			$fila = array( 'id' => $id );
			foreach ( $columnas as $i => $columna ) {
				$valor            = $valores[ $i ] ?? '';
				$fila[ $columna ] = is_numeric( $valor ) ? (int) $valor : trim( $valor, "\'" );
			}

			// Respeta el índice único (user_id, identidad_id, lesson_id).
			foreach ( $this->filas[ $tabla ] as $existente ) {
				if ( ( $existente['user_id'] ?? 0 ) === $fila['user_id']
					&& ( $existente['identidad_id'] ?? 0 ) === $fila['identidad_id']
					&& ( $existente['lesson_id'] ?? 0 ) === $fila['lesson_id'] ) {
					return 1;
				}
			}

			$this->filas[ $tabla ][ $id ] = $fila;

			return 1;
		}

		return 1;
	}

	/**
	 * Devuelve una columna: entiende el SELECT del progreso.
	 *
	 * @param string $query Consulta ya preparada.
	 * @return array<int, mixed>
	 */
	public function get_col( $query ) {
		$query = (string) $query;

		if ( ! preg_match( '/FROM\s+(\S+)/i', $query, $m ) ) {
			return array();
		}

		$tabla  = $m[1];
		$filas  = $this->filas[ $tabla ] ?? array();
		$salida = array();

		$identidad = preg_match( '/identidad_id\s*=\s*(\d+)/i', $query, $i ) ? (int) $i[1] : null;
		$curso     = preg_match( '/course_id\s*=\s*(\d+)/i', $query, $c ) ? (int) $c[1] : null;

		foreach ( $filas as $fila ) {
			if ( null !== $identidad && (int) ( $fila['identidad_id'] ?? 0 ) !== $identidad ) {
				continue;
			}

			if ( null !== $curso && (int) ( $fila['course_id'] ?? 0 ) !== $curso ) {
				continue;
			}

			$salida[] = $fila['lesson_id'] ?? 0;
		}

		return $salida;
	}
}
