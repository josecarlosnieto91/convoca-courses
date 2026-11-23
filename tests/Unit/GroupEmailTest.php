<?php
/**
 * Correo al grupo: tiene que llegar también a quien está matriculado por correo.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Mailer;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Group_Email;
use Convoca\Courses\Notifications;
use PHPUnit\Framework\TestCase;

/**
 * Correo al grupo con matrículas por usuario y por identidad.
 */
final class GroupEmailTest extends TestCase {

	/**
	 * Prepara un curso con una matrícula por usuario, otra por identidad y una huérfana.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_courses_opciones'] = array();
		$GLOBALS['convoca_courses_usuarios'] = array();
		$GLOBALS['convoca_courses_titulos']  = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['convoca_test_meta']        = array();
		$GLOBALS['wpdb']->filas              = array();
		$GLOBALS['wpdb']->vars               = array();
		$GLOBALS['wpdb']->consultas          = array();
		Mailer::$enviados                    = array();

		$GLOBALS['convoca_courses_usuarios'][584] = (object) array(
			'ID'           => 584,
			'display_name' => 'Con cuenta',
			'user_email'   => 'con-cuenta@example.test',
		);
		// `Identidad::por_id()` se consulta dos veces: al componer el listado y al enviar.
		$identidad                = array(
			'id'     => 12,
			'email'  => 'por-identidad@example.test',
			'nombre' => 'Sin cuenta',
		);
		$GLOBALS['wpdb']->filas[] = $identidad;
		$GLOBALS['wpdb']->filas[] = $identidad;

		$prefijo = CPT_Base::META_PREFIX;

		$GLOBALS['convoca_test_post_types'] = array(
			101 => CPT_Matricula::TYPE,
			102 => CPT_Matricula::TYPE,
			103 => CPT_Matricula::TYPE,
		);
		$GLOBALS['convoca_test_meta']       = array(
			101 => array(
				$prefijo . 'curso_id' => 3,
				$prefijo . 'user_id'  => 584,
				$prefijo . 'estado'   => 'activa',
			),
			102 => array(
				$prefijo . 'curso_id'   => 3,
				$prefijo . 'estado'     => 'activa',
				'_convoca_identidad_id' => 12,
			),
			// Matrícula sin usuario ni identidad: no hay a quién escribir.
			103 => array(
				$prefijo . 'curso_id' => 3,
				$prefijo . 'estado'   => 'activa',
			),
		);
	}

	/**
	 * Limpia el PRO forzado para no contaminar otros casos.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_courses_filtros'] = array();
		parent::tearDown();
	}

	/**
	 * Sin licencia PRO, el correo al grupo no es una función disponible.
	 */
	public function test_sin_pro_el_correo_al_grupo_no_sale(): void {
		$resultado = Group_Email::send( 3, 'Aviso', 'Mensaje' );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertCount( 0, Mailer::$enviados );
	}

	/**
	 * Llega a las dos formas de estar matriculado; la matrícula sin persona no cuenta.
	 */
	public function test_llega_a_quien_tiene_cuenta_y_a_quien_esta_matriculado_por_correo(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$enviados = Group_Email::send( 3, 'Aviso', 'El aula cambia de hora' );

		$this->assertSame( 2, $enviados, 'la matrícula sin usuario ni identidad no cuenta' );
		$destinatarios = array_column( Mailer::$enviados, 0 );
		sort( $destinatarios );
		$this->assertSame( array( 'con-cuenta@example.test', 'por-identidad@example.test' ), $destinatarios );
		$this->assertSame( array(), Notifications::pending(), 'ni un aviso sin destinatario en la cola' );
	}

	/**
	 * Un mensaje de grupo a una identidad sale por su correo con su plantilla.
	 */
	public function test_el_mensaje_de_grupo_a_una_identidad_usa_su_plantilla(): void {
		$this->assertTrue( Notifications::group_message( 0, 3, 'Aviso', 'Mensaje', 12 ) );

		$this->assertCount( 1, Mailer::$enviados );
		$this->assertSame( 'por-identidad@example.test', Mailer::$enviados[0][0] );
		$this->assertSame( 'mensaje-grupo', Mailer::$enviados[0][3]['template'] );
	}
}
