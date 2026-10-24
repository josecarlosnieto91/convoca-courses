<?php
/**
 * Redirección que corta el flujo, como el `exit` de WordPress.
 *
 * La excepción lleva el destino para poder afirmar a dónde se vuelve.
 *
 * @package Convoca\Courses
 */

/**
 * Corta el flujo tras una redirección.
 */
class Convoca_Courses_Redirect extends \RuntimeException {

	/**
	 * Destino.
	 *
	 * @var string
	 */
	public string $destino;

	/**
	 * Constructor.
	 *
	 * @param string $destino URL de destino.
	 */
	public function __construct( string $destino ) {
		parent::__construct( 'redirect: ' . $destino );
		$this->destino = $destino;
	}
}
