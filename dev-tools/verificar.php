<?php
/**
 * Verificación funcional del módulo en un WordPress real (entorno de desarrollo).
 *
 * No forma parte del plugin: se ejecuta a mano contra el sitio de desarrollo y comprueba el recorrido
 * completo (curso → módulos → lecciones → matrícula → progreso → certificado).
 *
 * Uso: php wp-content/plugins/convoca-courses/dev-tools/verificar.php
 */

define( 'WP_USE_THEMES', false );
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

use Convoca\Courses\Access;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\Certificate;
use Convoca\Courses\Course_Payment;
use Convoca\Courses\Enrollment;
use Convoca\Courses\Progress;

$fallos = array();
$ok     = 0;

/**
 * Comprueba una condición y la apunta.
 *
 * @param bool   $condicion Condición.
 * @param string $mensaje   Descripción.
 * @return void
 */
function comprobar( bool $condicion, string $mensaje ): void {
	global $fallos, $ok;
	if ( $condicion ) {
		++$ok;
		echo "  OK   {$mensaje}\n";
		return;
	}
	$fallos[] = $mensaje;
	echo "  FALLO {$mensaje}\n";
}

echo "== Activación ==\n";
$resultado = activate_plugin( 'convoca-courses/convoca-courses.php' );
comprobar( null === $resultado || ! is_wp_error( $resultado ), 'el plugin se activa sin error' );
comprobar( is_plugin_active( 'convoca-courses/convoca-courses.php' ), 'el plugin figura activo' );

echo "\n== Tipos de contenido ==\n";
foreach ( array( 'convoca_curso', 'convoca_modulo', 'convoca_leccion', 'convoca_matricula' ) as $tipo ) {
	comprobar( post_type_exists( $tipo ), "el tipo {$tipo} está registrado" );
}

echo "\n== Datos de prueba ==\n";
$curso_id = wp_insert_post(
	array(
		'post_type'    => 'convoca_curso',
		'post_status'  => 'publish',
		'post_title'   => 'Curso de prueba',
		'post_content' => 'Curso de ejemplo para comprobar el módulo.',
	),
	true
);
comprobar( ! is_wp_error( $curso_id ) && $curso_id > 0, 'se crea el curso' );

update_post_meta( $curso_id, '_convoca_precio', 0 );
update_post_meta( $curso_id, '_convoca_moneda', 'EUR' );
update_post_meta( $curso_id, '_convoca_certificado', 1 );
update_post_meta( $curso_id, '_convoca_desbloqueo_secuencial', 0 );

$modulos = array();
for ( $m = 1; $m <= 3; $m++ ) {
	$modulo_id = wp_insert_post(
		array(
			'post_type'    => 'convoca_modulo',
			'post_status'  => 'publish',
			'post_title'   => 'Módulo ' . $m,
			'post_content' => 'Contenido del módulo ' . $m,
		),
		true
	);
	update_post_meta( $modulo_id, '_convoca_curso_id', $curso_id );
	update_post_meta( $modulo_id, '_convoca_orden', $m );
	$modulos[ $m ] = $modulo_id;
}
comprobar( 3 === count( $modulos ), 'se crean 3 módulos' );

$lecciones = array();
$tipos     = array( 'texto', 'texto', 'pdf', 'adjunto' );
for ( $m = 1; $m <= 3; $m++ ) {
	for ( $l = 1; $l <= 4; $l++ ) {
		$indice     = ( ( $m - 1 ) * 4 ) + $l;
		$leccion_id = wp_insert_post(
			array(
				'post_type'    => 'convoca_leccion',
				'post_status'  => 'publish',
				'post_title'   => 'Lección ' . $indice,
				'post_content' => 'Contenido de la lección ' . $indice,
			),
			true
		);
		update_post_meta( $leccion_id, '_convoca_modulo_id', $modulos[ $m ] );
		update_post_meta( $leccion_id, '_convoca_tipo', $tipos[ ( $indice - 1 ) % 4 ] );
		update_post_meta( $leccion_id, '_convoca_orden', $l );
		update_post_meta( $leccion_id, '_convoca_duracion', 10 + $indice );
		$lecciones[] = $leccion_id;
	}
}
comprobar( 12 === count( $lecciones ), 'se crean 12 lecciones' );
comprobar( 3 === count( CPT_Modulo::of_course( $curso_id ) ), 'el curso lista sus 3 módulos' );
comprobar( 12 === count( CPT_Leccion::of_course( $curso_id ) ), 'el curso lista sus 12 lecciones' );

echo "\n== Páginas con shortcodes ==\n";
$pagina_curso   = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Curso de prueba (portada)',
		'post_content' => '[convoca_curso id="' . $curso_id . '"]',
	),
	true
);
$pagina_leccion = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Lección 1',
		'post_content' => '[convoca_curso_leccion id="' . $lecciones[0] . '"]',
	),
	true
);
$pagina_indice  = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Índice del curso',
		'post_content' => '[convoca_curso_lecciones id="' . $curso_id . '"]',
	),
	true
);
comprobar( $pagina_curso > 0 && $pagina_leccion > 0 && $pagina_indice > 0, 'se crean las tres páginas' );

echo "\n== Matrícula ==\n";
$alumno_id = username_exists( 'alumno-prueba' );
if ( ! $alumno_id ) {
	$alumno_id = wp_insert_user(
		array(
			'user_login' => 'alumno-prueba',
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => 'alumno@example.org',
			'role'       => 'subscriber',
		)
	);
}
comprobar( is_int( $alumno_id ) && $alumno_id > 0, 'existe la persona de prueba' );

$matricula = Enrollment::enroll( (int) $alumno_id, (int) $curso_id );
comprobar( ! is_wp_error( $matricula ) || 'convoca_courses_already' === $matricula->get_error_code(), 'la matrícula se crea (o ya existía)' );

$matricula_id = Enrollment::find( (int) $alumno_id, (int) $curso_id );
comprobar( $matricula_id > 0, 'la matrícula se puede localizar' );
comprobar( 'activa' === \Convoca\Courses\CPT_Matricula::status( $matricula_id ), 'la matrícula queda activa' );
comprobar( Access::is_enrolled( (int) $alumno_id, (int) $curso_id ), 'la persona matriculada tiene acceso' );

$duplicada = Enrollment::enroll( (int) $alumno_id, (int) $curso_id );
comprobar( is_wp_error( $duplicada ) && 'convoca_courses_already' === $duplicada->get_error_code(), 'no se puede matricular dos veces' );

echo "\n== Preparar la prueba web (persona sin matrícula) ==\n";
// La prueba por HTTP tiene que empezar siempre desde el mismo sitio: persona creada, sin matrícula y
// sin progreso en este curso. Si no, la segunda pasada comprueba otra cosa y miente.
$web_user = username_exists( 'alumno2-prueba' );
if ( ! $web_user ) {
	$web_user = wp_insert_user(
		array(
			'user_login' => 'alumno2-prueba',
			'user_pass'  => 'Prueba-Segura-2026',
			'user_email' => 'alumno2@example.org',
			'role'       => 'subscriber',
		)
	);
}
comprobar( is_int( $web_user ) && $web_user > 0, 'existe la persona de la prueba web' );

$previa = Enrollment::find( (int) $web_user, (int) $curso_id );
if ( $previa > 0 ) {
	wp_delete_post( $previa, true );
}
Progress::delete_for_course( (int) $curso_id );
comprobar( 0 === Enrollment::find( (int) $web_user, (int) $curso_id ), 'la persona de la prueba web empieza sin matrícula' );

echo "\n== Progreso ==\n";
Progress::complete( (int) $alumno_id, (int) $curso_id, (int) $lecciones[0] );
$parcial = Progress::for_course( (int) $alumno_id, (int) $curso_id );
comprobar( 1 === $parcial['done'], 'una lección completada se cuenta' );
comprobar( 12 === $parcial['total'], 'el total de lecciones es 12' );
comprobar( abs( $parcial['percent'] - 8.3 ) < 0.01, 'el porcentaje parcial es 8,3 %' );

Progress::complete( (int) $alumno_id, (int) $curso_id, (int) $lecciones[0] );
comprobar( 1 === Progress::for_course( (int) $alumno_id, (int) $curso_id )['done'], 'marcar dos veces no duplica el progreso' );

foreach ( $lecciones as $leccion_id ) {
	Progress::complete( (int) $alumno_id, (int) $curso_id, (int) $leccion_id );
}
$completo = Progress::for_course( (int) $alumno_id, (int) $curso_id );
comprobar( 100.0 === $completo['percent'], 'el curso llega al 100 %' );

echo "\n== Certificado ==\n";
$codigo = Certificate::maybe_issue( $matricula_id );
comprobar( '' !== $codigo, 'se emite el certificado al completar' );
comprobar( null !== Certificate::data( $codigo ), 'el certificado se localiza por su código' );
comprobar( 0 === Certificate::find_by_code( 'CC-INVENTADO' ), 'un código falso no existe' );
$pdf = Certificate::pdf_path( $codigo );
comprobar( '' !== $pdf && file_exists( $pdf ), 'el PDF del certificado se genera: ' . $pdf );

echo "\n== Cobro con Convoca Gateway ==\n";
comprobar( Course_Payment::gateway_available(), 'el gateway está disponible en este sitio' );
comprobar( Course_Payment::needs_payment( 25.0, true ), 'un curso de pago con gateway necesita cobro' );
comprobar( 1999 === Course_Payment::amount_cents( 19.99 ), 'el importe se convierte a céntimos sin perder uno' );

// Curso de pago.
$curso_pago = wp_insert_post(
	array(
		'post_type'    => 'convoca_curso',
		'post_status'  => 'publish',
		'post_title'   => 'Curso de pago (prueba)',
		'post_content' => 'Curso con precio para comprobar el cobro.',
	),
	true
);
update_post_meta( $curso_pago, '_convoca_precio', 25.0 );
update_post_meta( $curso_pago, '_convoca_moneda', 'EUR' );
update_post_meta( $curso_pago, '_convoca_certificado', 0 );
comprobar( Course_Payment::course_needs_payment( (int) $curso_pago ), 'el curso de pago se detecta como cobrable' );

$matricula_pago = Enrollment::enroll( (int) $alumno_id, (int) $curso_pago, array( 'estado' => 'pendiente' ) );
comprobar( ! is_wp_error( $matricula_pago ), 'la matrícula de pago se crea' );

if ( ! is_wp_error( $matricula_pago ) ) {
	$matricula_pago = (int) $matricula_pago;
	comprobar( 'pendiente' === \Convoca\Courses\CPT_Matricula::status( $matricula_pago ), 'la matrícula nace pendiente' );
	comprobar( ! Access::is_enrolled( (int) $alumno_id, (int) $curso_pago ), 'pendiente no da acceso al contenido' );

	$pago = Course_Payment::create( $matricula_pago, (int) $curso_pago );
	comprobar( ! is_wp_error( $pago ), 'se crea el pago en el gateway' );

	if ( ! is_wp_error( $pago ) ) {
		$pago_id = (int) $pago['pago_id'];
		comprobar( $pago_id > 0, 'el gateway devuelve un pago con identificador' );
		comprobar( 'pago' === get_post_type( $pago_id ), 'el pago existe como entrada del gateway' );
		comprobar( 'courses' === (string) get_post_meta( $pago_id, '_convoca_origin', true ), 'el pago lleva el origen courses' );
		comprobar( $matricula_pago === (int) get_post_meta( $pago_id, '_convoca_origin_id', true ), 'el pago apunta a la matrícula' );
		comprobar( 2500 === (int) get_post_meta( $pago_id, '_convoca_amount_cents', true ), 'el importe llega al gateway en céntimos' );
		comprobar( '' !== (string) $pago['payment_url'], 'hay enlace de pago' );
		comprobar( '' !== Course_Payment::payment_url( $matricula_pago ), 'el enlace queda guardado en la matrícula' );

		// Pago completado: el gateway dispara este gancho al confirmar.
		do_action( 'convoca_payment_completed', $pago_id, 'courses', $matricula_pago, array( 'order_id' => 'PRUEBA-1' ) );
		comprobar( 'activa' === \Convoca\Courses\CPT_Matricula::status( $matricula_pago ), 'el pago confirmado activa la matrícula' );
		comprobar( Access::is_enrolled( (int) $alumno_id, (int) $curso_pago ), 'con el pago hecho hay acceso al curso' );
		comprobar( 'PRUEBA-1' === \Convoca\Courses\CPT_Matricula::get_string( $matricula_pago, 'referencia_pago' ), 'se guarda la referencia del pago' );

		// Un pago de OTRO origen no debe tocar esta matrícula.
		$matricula_2 = Enrollment::enroll( (int) $alumno_id, (int) $curso_pago );
		do_action( 'convoca_payment_completed', 999999, 'members', 424242, array() );
		comprobar( is_wp_error( $matricula_2 ) && 'convoca_courses_already' === $matricula_2->get_error_code(), 'un pago de otro origen no altera matrículas de cursos' );

		// Pago fallido: no activa nada.
		$curso_pago2 = wp_insert_post(
			array(
				'post_type'   => 'convoca_curso',
				'post_status' => 'publish',
				'post_title'  => 'Curso de pago 2 (prueba)',
			),
			true
		);
		update_post_meta( $curso_pago2, '_convoca_precio', 10.0 );
		$matricula_3 = Enrollment::enroll( (int) $alumno_id, (int) $curso_pago2, array( 'estado' => 'pendiente' ) );
		if ( ! is_wp_error( $matricula_3 ) ) {
			$pago3 = Course_Payment::create( (int) $matricula_3, (int) $curso_pago2 );
			if ( ! is_wp_error( $pago3 ) ) {
				do_action( 'convoca_payment_failed', (int) $pago3['pago_id'], 'SIS0051' );
			}
			comprobar( 'pendiente' === \Convoca\Courses\CPT_Matricula::status( (int) $matricula_3 ), 'un pago fallido deja la matrícula pendiente' );
		}
	}
}

echo "\n== Resumen ==\n";
printf( "Comprobaciones: %d correctas, %d fallidas\n", $ok, count( $fallos ) );
foreach ( $fallos as $fallo ) {
	echo "  - {$fallo}\n";
}

file_put_contents(
	dirname( __DIR__ ) . '/dev-tools/resultado.json',
	json_encode(
		array(
			'ok'             => $ok,
			'fallos'         => $fallos,
			'curso_id'       => $curso_id,
			'pagina_curso'   => $pagina_curso,
			'pagina_leccion' => $pagina_leccion,
			'pagina_indice'  => $pagina_indice,
			'codigo'         => $codigo,
			'lecciones'      => $lecciones,
			'curso_pago'     => $curso_pago,
			'alumno_id'      => $alumno_id,
			'matricula_id'   => $matricula_id,
			'pdf'            => $pdf,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
	)
);

exit( empty( $fallos ) ? 0 : 1 );
