<?php
/**
 * Vínculo entre una persona de WordPress y su ficha de socio en Members.
 *
 * Se resuelve por correo, que es el dato que comparten las dos partes. No crea nada: si no hay socio con
 * ese correo, la matrícula sigue siendo de una persona sin ficha.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identidad de socio reutilizada.
 */
class Socio {

	/**
	 * Meta de la matrícula con la ficha de socio vinculada.
	 */
	public const META_MEMBER = '_convoca_member_id';

	/**
	 * Ficha de socio cuyo correo coincide, o 0.
	 *
	 * @param string $email Correo.
	 * @return int
	 */
	public static function member_id_por_email( string $email ): int {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return 0;
		}

		$fichas = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- búsqueda por correo, una vez por matrícula.
				'meta_query'     => array(
					array(
						'key'   => '_convoca_email',
						'value' => $email,
					),
				),
			)
		);

		return isset( $fichas[0] ) ? (int) $fichas[0] : 0;
	}

	/**
	 * Ficha de socio de una persona de WordPress, o 0.
	 *
	 * @param int $user_id Persona.
	 * @return int
	 */
	public static function member_id_de_usuario( int $user_id ): int {
		$usuario = get_userdata( $user_id );

		if ( ! is_object( $usuario ) || ! property_exists( $usuario, 'user_email' ) ) {
			return 0;
		}

		return self::member_id_por_email( (string) $usuario->user_email );
	}

	/**
	 * Anota el vínculo en una matrícula.
	 *
	 * @param int $enrollment_id Matrícula.
	 * @param int $user_id       Persona.
	 * @return int Ficha vinculada (0 si no hay).
	 */
	public static function vincular_matricula( int $enrollment_id, int $user_id ): int {
		$member_id = self::member_id_de_usuario( $user_id );

		if ( $member_id > 0 && $enrollment_id > 0 ) {
			update_post_meta( $enrollment_id, self::META_MEMBER, $member_id );
		}

		return $member_id;
	}

	/**
	 * Cursos en los que está matriculada una ficha de socio.
	 *
	 * @param int $member_id Ficha.
	 * @return array<int, int>
	 */
	public static function cursos_de_socio( int $member_id ): array {
		if ( $member_id <= 0 ) {
			return array();
		}

		$matriculas = get_posts(
			array(
				'post_type'      => CPT_Matricula::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- catálogo pequeño.
				'meta_query'     => array(
					array(
						'key'   => self::META_MEMBER,
						'value' => $member_id,
					),
				),
			)
		);

		$cursos = array();
		foreach ( $matriculas as $matricula ) {
			$curso = CPT_Matricula::course_id( (int) $matricula );
			if ( $curso > 0 ) {
				$cursos[] = $curso;
			}
		}

		return array_values( array_unique( $cursos ) );
	}
}
