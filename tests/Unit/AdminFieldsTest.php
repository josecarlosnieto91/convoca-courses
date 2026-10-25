<?php
/**
 * Los campos del editor de cursos: permisos, saneado y jerarquía.
 *
 * Lo que se protege: que un guardado sin nonce o sin capacidad no toque nada; que un precio o un aforo no puedan
 * quedar negativos ni un porcentaje pasar de 100; y que una relación imposible (módulo sin curso, lección sin
 * módulo) **no se guarde a medias**: se retira y se avisa.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Courses\Admin_Fields;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Hierarchy;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
require_once dirname( __DIR__, 2 ) . '/includes/Hierarchy.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Modulo.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Leccion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-fields.php';

/**
 * Campos del editor.
 */
final class AdminFieldsTest extends TestCase {

	/**
	 * Campos.
	 *
	 * @var Admin_Fields
	 */
	private Admin_Fields $campos;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_meta']         = array();
		$GLOBALS['convoca_test_post_types']   = array();
		$GLOBALS['convoca_test_caps']         = array( 'convoca_manage_courses' => true );
		$GLOBALS['convoca_courses_nonce_ok']  = true;
		$GLOBALS['convoca_test_transients']   = array();
		$GLOBALS['convoca_test_meta_borrada'] = array();
		$GLOBALS['convoca_test_cajas']        = array();
		$GLOBALS['convoca_courses_acciones']  = array();
		$_POST                                = array();
		$_GET                                 = array();
		$this->campos                         = new Admin_Fields();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		foreach ( array(
			'convoca_test_meta',
			'convoca_test_post_types',
			'convoca_test_caps',
			'convoca_test_nonce_ok',
			'convoca_test_transients',
			'convoca_test_meta_borrada',
			'convoca_test_cajas',
			'convoca_courses_acciones',
		) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}
		unset( $GLOBALS['convoca_courses_pantalla'], $GLOBALS['convoca_test_user_id'], $GLOBALS['convoca_courses_nonce_ok'], $GLOBALS['convoca_test_titulos'] );
		$_POST = array();
		$_GET  = array();
	}

	/**
	 * Registra los tres enganches.
	 */
	public function test_registra_los_enganches(): void {
		$this->campos->register();

		foreach ( array( 'add_meta_boxes', 'save_post', 'admin_notices' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['convoca_courses_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * Tres cajas, cada una en su tipo de contenido.
	 */
	public function test_las_tres_cajas(): void {
		$this->campos->add_meta_boxes();

		$this->assertSame( CPT_Curso::TYPE, $GLOBALS['convoca_test_cajas']['convoca_courses_course']['pantalla'] );
		$this->assertSame( CPT_Modulo::TYPE, $GLOBALS['convoca_test_cajas']['convoca_courses_module']['pantalla'] );
		$this->assertSame( CPT_Leccion::TYPE, $GLOBALS['convoca_test_cajas']['convoca_courses_lesson']['pantalla'] );
		$this->assertSame( 'high', $GLOBALS['convoca_test_cajas']['convoca_courses_course']['prioridad'] );
	}

	/**
	 * Sin nonce en el formulario no se guarda nada.
	 */
	public function test_sin_nonce(): void {
		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Con un nonce que no vale, tampoco.
	 */
	public function test_nonce_invalido(): void {
		$_POST                               = array( Admin_Fields::NONCE => 'malo' );
		$GLOBALS['convoca_courses_nonce_ok'] = 0;

		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Sin la capacidad del módulo, tampoco.
	 */
	public function test_sin_capacidad(): void {
		$_POST = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_precio'    => '100',
		);
		$GLOBALS['convoca_test_caps']['convoca_manage_courses'] = false;

		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Un tipo de contenido que no tiene caja no escribe nada.
	 */
	public function test_otro_tipo_no_guarda(): void {
		$_POST = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_precio'    => '100',
		);

		$this->campos->save( 61, $this->post( 'post' ) );

		$this->assertSame( array(), $GLOBALS['convoca_test_meta'] );
	}

	/**
	 * Crea un post del tipo pedido.
	 *
	 * @param string $tipo Tipo.
	 * @return \WP_Post
	 */
	private function post( string $tipo ): \WP_Post {
		$post            = new \WP_Post();
		$post->ID        = 61;
		$post->post_type = $tipo;

		return $post;
	}

	/**
	 * Guardado del curso: números saneados y booleanos como 1/0.
	 */
	public function test_guarda_el_curso(): void {
		$_POST = array(
			Admin_Fields::NONCE                => 'bueno',
			'convoca_precio'                   => '120.50',
			'convoca_moneda'                   => 'eur',
			'convoca_plazas'                   => '-5',
			'convoca_certificado'              => '1',
			'convoca_secuencial'               => '',
			'convoca_apertura_matricula'       => '2026-11-01',
			'convoca_cierre_matricula'         => '2026-11-20',
			'convoca_fecha_fin'                => '2027-01-15',
			'convoca_caducidad_dias'           => '-3',
			'convoca_nota_minima'              => '75',
			'convoca_acceso_examen'            => 'inventado',
			'convoca_acceso_examen_porcentaje' => '150',
			'convoca_precio_socio'             => '-2',
			'convoca_descuento_socio'          => '150',
			'convoca_gratis_socios'            => '1',
		);

		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$m = $GLOBALS['convoca_test_meta'][61];
		$this->assertSame( 120.5, (float) $m['_convoca_precio'] );
		$this->assertSame( 'EUR', $m['_convoca_moneda'], 'la moneda se normaliza a mayúsculas de 3' );
		// **Un aforo negativo se guarda como 0, no como 5.** `absint()` quita el signo —`absint( -5 )` es 5— así que
		// el `max( 0, ... )` de después no servía de nada: un formulario manipulado dejaba el curso con 5 plazas de
		// más (courses#5).
		$this->assertSame( 0, (int) $m['_convoca_plazas'], 'un aforo negativo no puede convertirse en plazas' );
		$this->assertSame( 1, (int) $m['_convoca_certificado'] );
		$this->assertSame( 0, (int) $m['_convoca_desbloqueo_secuencial'] );
		$this->assertSame( 0, (int) $m['_convoca_caducidad_dias'], 'ni una caducidad negativa se vuelve positiva' );
		$this->assertSame( 75, (int) $m['_convoca_nota_minima'] );
		$this->assertSame( 100, (int) $m['_convoca_acceso_examen_porcentaje'], 'el porcentaje se recorta' );
		$this->assertSame( -1.0, (float) $m['_convoca_precio_socio'], 'un precio de socio negativo es «no aplica»' );
		$this->assertSame( 100, (int) $m['_convoca_descuento_socio'] );
		$this->assertSame( 1, (int) $m['_convoca_gratis_socios'] );
		$this->assertSame( '2026-11-01', $m['_convoca_fecha_apertura_matricula'] );
		$this->assertSame( '2027-01-15', $m['_convoca_fecha_fin'] );
	}

	/**
	 * **Ningún campo numérico se cuela en positivo cuando llega en negativo.**
	 *
	 * `absint()` está pensado para identificadores: quita el signo, así que `absint( -5 )` es 5, y el `max( 0, ... )`
	 * de después ya no tiene nada que hacer. En un aforo eso son 5 plazas de más; en un descuento, un 20 % que nadie
	 * autorizó (courses#5).
	 */
	public function test_los_campos_numericos_negativos_quedan_en_cero(): void {
		$_POST = array(
			Admin_Fields::NONCE                => 'bueno',
			'convoca_plazas'                   => '-5',
			'convoca_caducidad_dias'           => '-30',
			'convoca_acceso_examen_porcentaje' => '-20',
			'convoca_descuento_socio'          => '-20',
		);

		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$m = $GLOBALS['convoca_test_meta'][61] ?? array();

		$this->assertArrayHasKey( '_convoca_plazas', $m, 'el guardado tiene que haber ocurrido' );
		$this->assertSame( 0, (int) $m['_convoca_plazas'], 'plazas: -5 no son 5 plazas' );
		$this->assertSame( 0, (int) $m['_convoca_caducidad_dias'], 'caducidad: -30 no son 30 días' );
		$this->assertSame( 0, (int) $m['_convoca_acceso_examen_porcentaje'], 'porcentaje: un negativo no acaba en positivo' );
		$this->assertSame( 0, (int) $m['_convoca_descuento_socio'], 'descuento: -20 no es un 20 % de descuento' );
	}

	/**
	 * Sin campos en el formulario, los valores por defecto son sensatos (no ceros a medias).
	 */
	public function test_valores_por_defecto(): void {
		$_POST = array( Admin_Fields::NONCE => 'bueno' );

		$this->campos->save( 61, $this->post( CPT_Curso::TYPE ) );

		$m = $GLOBALS['convoca_test_meta'][61];
		$this->assertSame( 'EUR', $m['_convoca_moneda'] );
		$this->assertSame( -1.0, (float) $m['_convoca_precio_socio'] );
		$this->assertSame( 100, (int) $m['_convoca_acceso_examen_porcentaje'] );
	}

	/**
	 * Módulo con curso válido: se guarda la relación y no se avisa.
	 */
	public function test_modulo_valido(): void {
		$GLOBALS['convoca_test_post_types'][61] = CPT_Curso::TYPE;
		$_POST                                  = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_curso_id'  => '61',
			'convoca_orden'     => '2',
		);

		$this->campos->save( 70, $this->post( CPT_Modulo::TYPE ) );

		$this->assertSame( 61, (int) $GLOBALS['convoca_test_meta'][70]['_convoca_curso_id'] );
		$this->assertSame( 2, (int) $GLOBALS['convoca_test_meta'][70]['_convoca_orden'] );
		$this->assertArrayNotHasKey( '_convoca_hierarchy_error', $GLOBALS['convoca_test_meta'][70] );
	}

	/**
	 * Módulo sin curso: **no se guarda una relación imposible**, se retira y se avisa.
	 */
	public function test_modulo_huerfano(): void {
		$_POST = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_curso_id'  => '999',
		);

		$this->campos->save( 70, $this->post( CPT_Modulo::TYPE ) );

		$this->assertSame( 1, (int) $GLOBALS['convoca_test_meta'][70]['_convoca_hierarchy_error'] );
		$this->assertArrayNotHasKey( '_convoca_curso_id', $GLOBALS['convoca_test_meta'][70] );
		$this->assertNotEmpty( $GLOBALS['convoca_test_transients'], 'queda el aviso para el editor' );
	}

	/**
	 * Lección con módulo válido: se guarda el tipo y la relación.
	 */
	public function test_leccion_valida(): void {
		$GLOBALS['convoca_test_post_types'][62] = CPT_Modulo::TYPE;
		$_POST                                  = array(
			Admin_Fields::NONCE  => 'bueno',
			'convoca_modulo_id'  => '62',
			'convoca_tipo'       => 'video',
			'convoca_duracion'   => '15',
			'convoca_video_url'  => 'https://video.example.test/clase',
			'convoca_video_key'  => 'clave',
			'convoca_adjunto_id' => '7',
		);

		$this->campos->save( 80, $this->post( CPT_Leccion::TYPE ) );

		$m = $GLOBALS['convoca_test_meta'][80];
		$this->assertSame( 'video', $m['_convoca_tipo'] );
		$this->assertSame( 62, (int) $m['_convoca_modulo_id'] );
		$this->assertSame( 15, (int) $m['_convoca_duracion'] );
		$this->assertSame( 'https://video.example.test/clase', $m['_convoca_video_url'] );
		$this->assertSame( 7, (int) $m['_convoca_adjunto_id'] );
	}

	/**
	 * Un tipo de lección que no existe no se guarda: cae a «texto».
	 */
	public function test_leccion_con_tipo_invalido(): void {
		$GLOBALS['convoca_test_post_types'][62] = CPT_Modulo::TYPE;
		$_POST                                  = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_modulo_id' => '62',
			'convoca_tipo'      => 'holograma',
		);

		$this->campos->save( 80, $this->post( CPT_Leccion::TYPE ) );

		$this->assertSame( 'texto', $GLOBALS['convoca_test_meta'][80]['_convoca_tipo'] );
	}

	/**
	 * Lección sin módulo: se retira la relación y se avisa.
	 */
	public function test_leccion_huerfana(): void {
		$_POST = array(
			Admin_Fields::NONCE => 'bueno',
			'convoca_modulo_id' => '999',
		);

		$this->campos->save( 80, $this->post( CPT_Leccion::TYPE ) );

		$this->assertSame( 1, (int) $GLOBALS['convoca_test_meta'][80]['_convoca_hierarchy_error'] );
		$this->assertArrayNotHasKey( '_convoca_modulo_id', $GLOBALS['convoca_test_meta'][80] );
	}

	/**
	 * El aviso solo sale en las pantallas de módulo y lección.
	 */
	public function test_el_aviso_fuera_de_su_pantalla(): void {
		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => 'post' );

		ob_start();
		try {
			$this->campos->hierarchy_notice();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertSame( '', $html );
	}

	/**
	 * Con un módulo sin curso, el aviso se pinta.
	 */
	public function test_el_aviso_de_jerarquia(): void {
		$GLOBALS['convoca_courses_pantalla']    = (object) array( 'post_type' => CPT_Modulo::TYPE );
		$GLOBALS['convoca_test_post_types'][70] = CPT_Modulo::TYPE;
		$_GET['post']                           = '70';

		ob_start();
		try {
			$this->campos->hierarchy_notice();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'no cuelga de ningún curso', $html );
	}

	/**
	 * Sin errores no se pinta nada.
	 */
	public function test_sin_errores_no_hay_aviso(): void {
		$GLOBALS['convoca_courses_pantalla'] = (object) array( 'post_type' => CPT_Modulo::TYPE );
		$_GET['post']                        = '0';

		ob_start();
		try {
			$this->campos->hierarchy_notice();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertSame( '', $html );
	}

	/**
	 * Los selectores: cursos y módulos agrupados por curso, con los huérfanos aparte.
	 */
	public function test_los_selectores(): void {
		$GLOBALS['convoca_test_post_types']                    = array(
			61 => CPT_Curso::TYPE,
			62 => CPT_Curso::TYPE,
			70 => CPT_Modulo::TYPE,
			71 => CPT_Modulo::TYPE,
		);
		$GLOBALS['convoca_test_titulos']                       = array(
			61 => 'Curso A',
			62 => 'Curso B',
		);
		$GLOBALS['convoca_test_meta'][70]['_convoca_curso_id'] = '61';

		$cursos = $this->privado( 'courses_options' );
		$this->assertArrayHasKey( 0, $cursos, 'la opción vacía va primera' );
		$this->assertArrayHasKey( 61, $cursos );

		$grupos = $this->privado( 'modules_options' );
		$this->assertNotEmpty( $grupos );
		$ultimo = end( $grupos );
		$this->assertArrayHasKey( 71, $ultimo['modulos'], 'el módulo sin curso se ofrece aparte' );
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @return mixed
	 */
	private function privado( string $metodo ) {
		$reflexion = new \ReflectionMethod( Admin_Fields::class, $metodo );
		return $reflexion->invoke( $this->campos );
	}
}
