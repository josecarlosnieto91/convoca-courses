<?php
/**
 * Pruebas de las capacidades por entrada del módulo.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses\Tests\Unit;

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\CPT_Tarea;
use PHPUnit\Framework\TestCase;

/**
 * El 22-10-2026 la demo se quedó sin panel: con el módulo activo, `current_user_can( 'read' )`
 * respondía que no a todo el mundo, administrador incluido, porque el tipo de contenido declaraba
 * `'read_post' => 'read'`. WordPress mete el valor de las tres primitivas por entrada en
 * `$post_type_meta_caps`, y a partir de ahí la comprobación global de esa capacidad se resuelve
 * como `do_not_allow` (con aviso en el registro: «read_post debe comprobarse para una entrada»).
 *
 * Estas pruebas vigilan lo único que evita la recaída: que las tres primitivas por entrada tengan
 * nombre propio y que el puente las traduzca a lo que de verdad significan.
 *
 * @covers \Convoca\Courses\CPT_Base
 */
final class MetaCapsTest extends TestCase {

	/**
	 * Capacidades globales del sitio: si una primitiva por entrada toma uno de estos nombres,
	 * el panel entero se cae.
	 *
	 * @var array<int, string>
	 */
	private const GLOBALES = array(
		'read',
		'read_post',
		'edit_post',
		'delete_post',
		'edit_posts',
		'publish_posts',
		'convoca_manage_courses',
		'manage_options',
	);

	/**
	 * Los seis tipos que usan CPT_Base declaran las tres primitivas por entrada con nombre propio.
	 *
	 * @return void
	 */
	public function test_las_primitivas_por_entrada_no_son_capacidades_globales(): void {
		$tipos = array( CPT_Curso::class, CPT_Modulo::class, CPT_Leccion::class, CPT_Matricula::class, CPT_Examen::class, CPT_Tarea::class );

		foreach ( $tipos as $clase ) {
			// Sin setAccessible(): desde PHP 8.1 la reflexión llega a los métodos protegidos y la
			// llamada está deprecada (salía como deprecation con el PHP 8.5 local).
			$metodo = new \ReflectionMethod( $clase, 'capabilities' );
			$caps   = $metodo->invoke( null );

			foreach ( array( 'edit_post', 'read_post', 'delete_post' ) as $por_entrada ) {
				$this->assertArrayHasKey( $por_entrada, $caps, $clase . ' no declara ' . $por_entrada );
				$this->assertNotContains(
					$caps[ $por_entrada ],
					self::GLOBALES,
					$clase . ' declara ' . $por_entrada . ' como capacidad global (' . $caps[ $por_entrada ] . '): eso deja el panel sin acceso'
				);
			}
		}
	}

	/**
	 * Leer una entrada del módulo es la primitiva `read`.
	 *
	 * @return void
	 */
	public function test_el_puente_traduce_la_lectura_a_read(): void {
		$this->assertSame(
			array( 'read' ),
			CPT_Base::bridge_meta_caps( array( CPT_Base::CAP_READ_POST ), CPT_Base::CAP_READ_POST )
		);
	}

	/**
	 * Editar y borrar una entrada del módulo es la puerta del módulo.
	 *
	 * @return void
	 */
	public function test_el_puente_traduce_edicion_y_borrado_a_la_puerta(): void {
		foreach ( array( CPT_Base::CAP_EDIT_POST, CPT_Base::CAP_DELETE_POST ) as $cap ) {
			$this->assertSame(
				array( 'convoca_manage_courses' ),
				CPT_Base::bridge_meta_caps( array( $cap ), $cap )
			);
		}
	}

	/**
	 * El puente no toca capacidades que no son suyas.
	 *
	 * @return void
	 */
	public function test_el_puente_deja_pasar_lo_ajeno(): void {
		$this->assertSame(
			array( 'edit_posts' ),
			CPT_Base::bridge_meta_caps( array( 'edit_posts' ), 'edit_posts' )
		);
	}
}
