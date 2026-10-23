<?php
/**
 * Una entrada de WordPress (doble con las propiedades que leen los assets y las plantillas).
 *
 * @package Convoca\Courses\Tests
 */

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Una entrada de WordPress (doble con las propiedades que se leen aquí).
	 */
	class WP_Post {

		/**
		 * Identificador.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Tipo de contenido.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Estado.
		 *
		 * @var string
		 */
		public $post_status = 'publish';

		/**
		 * Título.
		 *
		 * @var string
		 */
		public $post_title = '';

		/**
		 * Contenido.
		 *
		 * @var string
		 */
		public $post_content = '';

		/**
		 * Construye la entrada.
		 *
		 * @param array $datos Propiedades.
		 */
		public function __construct( array $datos = array() ) {
			foreach ( $datos as $clave => $valor ) {
				$this->$clave = $valor;
			}
		}
	}
}
