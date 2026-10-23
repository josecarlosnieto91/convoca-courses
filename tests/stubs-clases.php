<?php
/**
 * Dobles de clases de WordPress para las pruebas unitarias de courses.
 *
 * Va aparte del bootstrap a propósito: PHPCS exige un fichero de funciones o de clases, no las dos, y
 * el bootstrap es de funciones.
 *
 * @package Convoca\Courses
 */

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Consulta de mentira que **cuenta de verdad**: recorre los posts que fija la prueba
	 * (`$GLOBALS['_cc_posts'][ tipo ]`) y aplica los `meta_query` con `relation AND`. Sirve para
	 * probar el aforo de un curso sin base de datos: si la cuenta se hace mal, la prueba lo ve.
	 */
	class WP_Query {
		/**
		 * Posts encontrados.
		 *
		 * @var array
		 */
		public $posts = array();

		/**
		 * Total encontrado.
		 *
		 * @var int
		 */
		public $found_posts = 0;

		/**
		 * Ejecuta la consulta al construirse, como WordPress.
		 *
		 * @param array $args Argumentos.
		 */
		public function __construct( $args = array() ) {
			$tipo = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post';
			$todo = isset( $GLOBALS['_cc_posts'][ $tipo ] ) ? $GLOBALS['_cc_posts'][ $tipo ] : array();
			$meta = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

			// Consulta por un solo metadato (meta_key / meta_value), como hace CPT_Modulo::of_course().
			if ( isset( $args['meta_key'] ) ) {
				$meta[] = array(
					'key'   => $args['meta_key'],
					'value' => isset( $args['meta_value'] ) ? $args['meta_value'] : '',
				);
			}
			$state = isset( $args['post_status'] ) && 'any' !== $args['post_status'] ? (string) $args['post_status'] : '';
			$hit   = array();

			foreach ( $todo as $post ) {
				if ( '' !== $state && isset( $post['status'] ) && $post['status'] !== $state ) {
					continue;
				}
				$pasa = true;
				foreach ( $meta as $clave => $regla ) {
					if ( 'relation' === $clave || ! is_array( $regla ) ) {
						continue;
					}
					$valor  = isset( $post['meta'][ $regla['key'] ] ) ? $post['meta'][ $regla['key'] ] : null;
					$espera = $regla['value'];
					$cmp    = isset( $regla['compare'] ) ? $regla['compare'] : '=';
					if ( 'IN' === $cmp ) {
						$pasa = in_array( (string) $valor, array_map( 'strval', (array) $espera ), true );
					} else {
						$pasa = (string) $valor === (string) $espera;
					}
					if ( ! $pasa ) {
						break;
					}
				}
				if ( $pasa ) {
					// Si la prueba sembró el objeto entero, se devuelve el objeto (como WordPress);
					// si solo sembró el id, el id.
					$hit[] = isset( $post['objeto'] ) ? $post['objeto'] : ( isset( $post['id'] ) ? $post['id'] : 0 );
				}
			}

			$this->posts       = $hit;
			$this->found_posts = count( $hit );
		}

		/**
		 * Devuelve los posts.
		 *
		 * @return array
		 */
		public function get_posts() {
			return $this->posts;
		}
	}
}
