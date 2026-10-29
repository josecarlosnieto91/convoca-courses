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
}
