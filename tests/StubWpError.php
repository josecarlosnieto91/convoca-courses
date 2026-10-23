<?php
/**
 * Doble mínimo de `WP_Error` para las pruebas unitarias.
 *
 * El plugin devuelve errores con `WP_Error` desde las operaciones que pueden fallar; sin núcleo
 * cargado hay que poder mirar al menos el código.
 *
 * @package Convoca\Courses\Tests
 */

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Error de WordPress con su código y su mensaje.
	 */
	class WP_Error {

		/**
		 * Código del error.
		 *
		 * @var string
		 */
		private $codigo;

		/**
		 * Mensaje del error.
		 *
		 * @var string
		 */
		private $mensaje;

		/**
		 * Constructor.
		 *
		 * @param string $codigo  Código.
		 * @param string $mensaje Mensaje.
		 */
		public function __construct( $codigo = '', $mensaje = '' ) {
			$this->codigo  = (string) $codigo;
			$this->mensaje = (string) $mensaje;
		}

		/**
		 * Código del error.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->codigo;
		}

		/**
		 * Mensaje del error.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->mensaje;
		}
	}
}
