<?php
/**
 * Cursos que se ofrecen a un socio en su área privada.
 *
 * `members` no conoce los cursos: lanza el filtro `convoca_cursos_para_socio` desde el panel del
 * socio y aquí se contesta con los cursos publicados y su enlace. Al abrir el enlace, la matrícula
 * reconoce al socio por la sesión y no le pide otra vez sus datos.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listado de cursos para el área del socio.
 */
class Cursos_Para_Socio {

	/**
	 * Filtro que consume `members`.
	 */
	public const FILTER = 'convoca_cursos_para_socio';

	/**
	 * Engancha el filtro.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( self::FILTER, array( __CLASS__, 'cursos' ), 10, 3 );
	}

	/**
	 * Devuelve los cursos publicados con el precio que le toca a esa persona.
	 *
	 * @param mixed $cursos    Lo que traiga otro (vacío por defecto).
	 * @param int   $user_id   Usuario de WordPress.
	 * @param int   $member_id Ficha de socio (no se usa aquí; viaja por si otro filtro la necesita).
	 * @return array<int,array{titulo:string,url:string,precio:string}>
	 */
	public static function cursos( $cursos, $user_id = 0, $member_id = 0 ): array {
		unset( $member_id );

		if ( is_array( $cursos ) && array() !== $cursos ) {
			return $cursos;
		}

		$lista = get_posts(
			array(
				'post_type'      => CPT_Curso::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$salida = array();

		foreach ( $lista as $curso ) {
			$precio = Pricing::for_user( (int) $curso->ID, (int) $user_id );

			$salida[] = array(
				'titulo' => (string) get_the_title( $curso ),
				'url'    => (string) get_permalink( $curso ),
				'precio' => self::etiqueta_precio( (float) $precio['precio'] ),
			);
		}

		return $salida;
	}

	/**
	 * Precio legible.
	 *
	 * @param float $precio Importe.
	 * @return string
	 */
	private static function etiqueta_precio( float $precio ): string {
		if ( $precio <= 0 ) {
			return __( 'Gratis', 'convoca-courses' );
		}

		return number_format( $precio, 2, ',', '.' ) . ' €';
	}
}
