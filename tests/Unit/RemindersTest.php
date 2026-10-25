<?php
/**
 * Recordatorios: la puerta del PRO, el cron, las dos reglas y el registro de lo enviado.
 *
 * `Reminders` estaba al 0 % (97 statements). Dos cosas importan: que no se avise dos veces de lo
 * mismo en una semana, y que un curso sin actividad no dispare avisos por error.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Courses\Tests;

use Convoca\Core\Mailer;
use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Matricula;
use Convoca\Courses\Pro;
use Convoca\Courses\Reminders;
use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de los recordatorios.
 */
class RemindersTest extends TestCase {

	/**
	 * Momento fijo de las pruebas (marca de tiempo).
	 *
	 * @var int
	 */
	private const AHORA = 1_761_220_800;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$GLOBALS['convoca_courses_ahora'] = self::AHORA;
		require_once dirname( __DIR__, 2 ) . '/includes/Access.php';
		require_once dirname( __DIR__, 2 ) . '/includes/License.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Pro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Base.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Curso.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Examen.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Matricula.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Enrollment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Attempts.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Roster.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Reminders.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		$GLOBALS['convoca_courses_opciones']      = array();
		$GLOBALS['convoca_courses_filtros']       = array();
		$GLOBALS['convoca_courses_cron']          = array();
		$GLOBALS['convoca_courses_cron_quitados'] = array();
		$GLOBALS['convoca_courses_fechas']        = array();
		// Los usuarios y la bandeja del mailer son de ESTOS casos y no se limpiaban: `curso_con_alumna_inactiva()`
		// siembra `convoca_courses_usuarios[9]` y un caso posterior lo heredaba. `limpiar()` tiene que devolver
		// todo lo que el caso toca, no solo lo que parece del módulo (courses#12).
		$GLOBALS['convoca_courses_usuarios']  = array();
		$GLOBALS['convoca_courses_mailer_ok'] = true;
		$GLOBALS['convoca_courses_lock_ok']   = true;
		$GLOBALS['convoca_test_post_types']   = array();
		$GLOBALS['convoca_test_meta']         = array();
		$GLOBALS['wpdb']->vars                = array();
		$GLOBALS['wpdb']->consultas           = array();
		// El buzón del doble es una PROPIEDAD ESTÁTICA: no la limpia nadie y los correos de un caso se veían en el
		// siguiente. `test_la_pasada_con_un_curso_no_rompe` esperaba 1 envío y encontraba 2 según qué hubiera
		// corrido antes (courses#12).
		Mailer::$enviados = array();
	}

	/**
	 * Los recordatorios dependen del PRO.
	 */
	public function test_los_recordatorios_dependen_del_pro(): void {
		$this->assertFalse( Reminders::enabled() );

		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$this->assertTrue( Reminders::enabled() );
	}

	/**
	 * Sin PRO se quita el cron: no se manda nada.
	 */
	public function test_sin_pro_se_quita_el_cron(): void {
		$GLOBALS['convoca_courses_cron'][ Reminders::HOOK ] = array( self::AHORA, 'daily' );

		Reminders::register();

		$this->assertSame( array(), $GLOBALS['convoca_courses_cron'], 'sin licencia no queda cron vivo' );
		$this->assertCount( 1, $GLOBALS['convoca_courses_cron_quitados'] );
	}

	/**
	 * Con PRO y sin cron programado, se programa diario.
	 */
	public function test_con_pro_se_programa_el_cron(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		Reminders::register();

		$this->assertArrayHasKey( Reminders::HOOK, $GLOBALS['convoca_courses_cron'] );
		$this->assertSame( 'daily', $GLOBALS['convoca_courses_cron'][ Reminders::HOOK ][1] );
		$cuando = (int) $GLOBALS['convoca_courses_cron'][ Reminders::HOOK ][0];
		$this->assertEqualsWithDelta( time() + 3600, $cuando, 120, 'la primera pasada es dentro de una hora' );
	}

	/**
	 * Con PRO y el cron ya puesto, no se duplica.
	 */
	public function test_con_pro_y_cron_ya_puesto_no_se_duplica(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;
		$GLOBALS['convoca_courses_cron'][ Reminders::HOOK ]            = array( 123, 'daily' );

		Reminders::register();

		$this->assertSame( array( 123, 'daily' ), $GLOBALS['convoca_courses_cron'][ Reminders::HOOK ], 'se respeta el ya programado' );
	}

	/**
	 * Quitar el cron sin cron no hace nada.
	 */
	public function test_quitar_el_cron_sin_cron_no_hace_nada(): void {
		Reminders::unschedule();

		$this->assertSame( array(), $GLOBALS['convoca_courses_cron_quitados'] );
	}

	/**
	 * Sin PRO, la pasada no envía ni consulta.
	 */
	public function test_sin_pro_la_pasada_no_envia(): void {
		// Se monta un curso que SÍ avisaría si la puerta del PRO no estuviera: si solo se comprobara
		// el 0, la mutación que quita la guarda seguiría en verde (sin cursos también da 0).
		$this->curso_con_alumna_inactiva();
		Mailer::$enviados = array();

		$this->assertSame( 0, Reminders::run() );
		$this->assertSame( array(), Mailer::$enviados, 'sin licencia no sale ni un correo' );
		$this->assertSame( array(), $GLOBALS['wpdb']->consultas, 'ni se consulta' );
	}

	/**
	 * Con PRO y sin cursos publicados no hay nada que enviar.
	 */
	public function test_con_pro_y_sin_cursos_no_se_envia(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;

		$this->assertSame( 0, Reminders::run() );
	}

	/**
	 * Un curso sin alumnado no envía nada.
	 */
	public function test_un_curso_sin_alumnado_no_envia(): void {
		$this->assertSame( 0, Reminders::run_course( 0 ) );
	}

	/**
	 * Un aviso enviado hace poco no se repite.
	 */
	public function test_un_aviso_reciente_no_se_repite(): void {
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = array(
			'9:3:inactividad' => self::AHORA - ( 3 * 86400 ),
		);

		$this->assertTrue( Reminders::recently_sent( 9, 3, 'inactividad' ) );
	}

	/**
	 * Pasada la semana, el aviso vuelve a tocar.
	 */
	public function test_pasada_la_semana_el_aviso_vuelve_a_tocar(): void {
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = array(
			'9:3:inactividad' => self::AHORA - ( 8 * 86400 ),
		);

		$this->assertFalse( Reminders::recently_sent( 9, 3, 'inactividad' ) );
	}

	/**
	 * Un registro que no es una lista no rompe nada.
	 */
	public function test_un_registro_roto_no_rompe(): void {
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = 'basura';

		$this->assertFalse( Reminders::recently_sent( 9, 3, 'inactividad' ) );
	}

	/**
	 * Sin entrada en el registro, no se ha avisado.
	 */
	public function test_sin_entrada_en_el_registro_no_se_ha_avisado(): void {
		$this->assertFalse( Reminders::recently_sent( 9, 3, 'examen' ) );
	}

	/**
	 * Sin actividad conocida no se avisa de inactividad: no hay nada que medir.
	 */
	public function test_sin_actividad_no_se_avisa(): void {
		$this->assertFalse( Reminders::should_remind_inactive( 9, 3 ) );
	}

	/**
	 * Con actividad de hace ocho días, toca avisar.
	 */
	public function test_ocho_dias_sin_entrar_toca_avisar(): void {
		$this->actividad( gmdate( 'Y-m-d H:i:s', self::AHORA - ( 8 * 86400 ) ) );

		$this->assertTrue( Reminders::should_remind_inactive( 9, 3 ) );
	}

	/**
	 * Con actividad de ayer, no.
	 */
	public function test_ayer_no_toca_avisar(): void {
		$this->actividad( gmdate( 'Y-m-d H:i:s', self::AHORA - 86400 ) );

		$this->assertFalse( Reminders::should_remind_inactive( 9, 3 ) );
	}

	/**
	 * Si ya se avisó, aunque esté inactiva, no se repite.
	 */
	public function test_si_ya_se_aviso_no_se_repite(): void {
		$this->actividad( gmdate( 'Y-m-d H:i:s', self::AHORA - ( 30 * 86400 ) ) );
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = array(
			'9:3:inactividad' => self::AHORA - 86400,
		);

		$this->assertFalse( Reminders::should_remind_inactive( 9, 3 ) );
	}

	/**
	 * Sin exámenes en el curso no hay aviso de examen.
	 */
	public function test_sin_examenes_no_hay_aviso(): void {
		$this->assertFalse( Reminders::should_remind_exam( 9, 3 ) );
	}

	/**
	 * Con un examen sin aprobar, toca avisar.
	 */
	public function test_un_examen_sin_aprobar_hace_avisar(): void {
		$this->examen( 5, 3 );
		$GLOBALS['wpdb']->vars = array( null );

		$this->assertTrue( Reminders::should_remind_exam( 9, 3 ) );
	}

	/**
	 * Con todos los exámenes aprobados, no hay nada que recordar.
	 */
	public function test_todos_aprobados_no_hay_aviso(): void {
		$this->examen( 5, 3 );
		$GLOBALS['wpdb']->vars = array( 1 );

		$this->assertFalse( Reminders::should_remind_exam( 9, 3 ) );
	}

	/**
	 * Si ya se avisó del examen, no se repite.
	 */
	public function test_el_aviso_de_examen_tampoco_se_repite(): void {
		$this->examen( 5, 3 );
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = array(
			'9:3:examen' => self::AHORA - 86400,
		);

		$this->assertFalse( Reminders::should_remind_exam( 9, 3 ) );
	}

	/**
	 * La última actividad se queda con la marca más reciente de progreso e intentos.
	 */
	public function test_la_ultima_actividad_es_la_mas_reciente(): void {
		$progreso              = gmdate( 'Y-m-d H:i:s', self::AHORA - ( 20 * 86400 ) );
		$intento               = gmdate( 'Y-m-d H:i:s', self::AHORA - ( 5 * 86400 ) );
		$GLOBALS['wpdb']->vars = array( $progreso, $intento );

		$this->assertSame( (int) strtotime( $intento ), Reminders::last_activity( 9, 3 ) );
	}

	/**
	 * Sin marcas pero con matrícula, cuenta la fecha de la matrícula.
	 */
	public function test_sin_actividad_cuenta_la_matricula(): void {
		$GLOBALS['wpdb']->vars                  = array( null, null );
		$GLOBALS['convoca_test_post_types'][44] = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'curso_id' ] = '3';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'user_id' ]  = '9';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
		$GLOBALS['convoca_courses_fechas'][44]                                  = 1_700_000_000;

		$this->assertSame( 1_700_000_000, Reminders::last_activity( 9, 3 ) );
	}

	/**
	 * Sin actividad y sin matrícula no hay última actividad.
	 */
	public function test_sin_nada_no_hay_ultima_actividad(): void {
		$GLOBALS['wpdb']->vars = array( null, null );

		$this->assertSame( 0, Reminders::last_activity( 9, 3 ) );
	}

	/**
	 * Un curso con una matrícula activa no rompe la pasada.
	 */
	public function test_la_pasada_con_un_curso_no_rompe(): void {
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;
		$this->curso_con_alumna_inactiva();

		$this->assertSame( 1, Reminders::run(), 'la alumna inactiva recibe su recordatorio' );
		$this->assertCount( 1, Mailer::$enviados );
		$this->assertSame( 'recordatorio-inactividad', Mailer::$enviados[0][3]['template'] );
		$this->assertArrayHasKey(
			'9:3:inactividad',
			$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ],
			'y queda apuntado para no repetirlo en una semana'
		);
	}

	/**
	 * Monta un curso con una alumna inactiva y un examen sin aprobar.
	 */
	private function curso_con_alumna_inactiva(): void {
		$this->examen( 5, 3 );
		$GLOBALS['convoca_test_post_types'][3]                                  = \Convoca\Courses\CPT_Curso::TYPE;
		$GLOBALS['convoca_test_post_types'][44]                                 = CPT_Matricula::TYPE;
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'curso_id' ] = '3';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'user_id' ]  = '9';
		$GLOBALS['convoca_test_meta'][44][ CPT_Base::META_PREFIX . 'estado' ]   = 'activa';
		$GLOBALS['convoca_courses_fechas'][44]                                  = self::AHORA - ( 30 * 86400 );
		$GLOBALS['convoca_courses_usuarios'][9]                                 = (object) array(
			'ID'           => 9,
			'display_name' => 'Una alumna',
			'user_email'   => 'alumna@example.test',
		);
		$GLOBALS['wpdb']->vars = array( gmdate( 'Y-m-d H:i:s', self::AHORA - ( 30 * 86400 ) ), null );
	}

	/**
	 * El registro no crece sin fin: guarda las 500 entradas más recientes.
	 */
	public function test_el_registro_tiene_tope(): void {
		$llenos = array();
		for ( $i = 0; $i < 500; $i++ ) {
			$llenos[ 'antiguo' . $i ] = self::AHORA - ( 60 * 86400 ) + $i;
		}
		$GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ] = $llenos;
		$GLOBALS['convoca_courses_filtros']['convoca_courses_has_pro'] = true;
		$this->curso_con_alumna_inactiva();

		Reminders::run();

		$registro = $GLOBALS['convoca_courses_opciones'][ Reminders::SENT_OPTION ];

		$this->assertCount( 500, $registro, 'una opción sin tope se lleva la fila de wp_options por delante' );
		$this->assertArrayNotHasKey( 'antiguo0', $registro, 'se tira lo viejo' );
		$this->assertArrayHasKey( '9:3:inactividad', $registro, 'y lo nuevo se queda' );
	}

	/**
	 * Anota una actividad de progreso y deja los intentos vacíos.
	 *
	 * @param string $fecha Fecha de la última actividad registrada.
	 */
	private function actividad( string $fecha ): void {
		$GLOBALS['wpdb']->vars = array( $fecha, null );
	}

	/**
	 * Da de alta un examen del curso.
	 *
	 * @param int $examen  Identificador del examen.
	 * @param int $curso   Curso.
	 */
	private function examen( int $examen, int $curso ): void {
		$GLOBALS['convoca_test_post_types'][ $examen ]                                 = CPT_Examen::TYPE;
		$GLOBALS['convoca_test_meta'][ $examen ][ CPT_Base::META_PREFIX . 'curso_id' ] = (string) $curso;
	}
}
