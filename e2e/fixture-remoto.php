<?php
/**
 * Siembra el fixture de la suite E2E dentro de la demo (se ejecuta con wp eval-file).
 *
 * Crea curso, módulo, lecciones, examen con preguntas, dos tareas, página con los shortcodes y dos
 * personas de prueba (alumno y profesor). Imprime un JSON con lo necesario para la suite.
 *
 * @package Convoca\Courses
 */

use Convoca\Courses\CPT_Base;
use Convoca\Courses\CPT_Curso;
use Convoca\Courses\CPT_Examen;
use Convoca\Courses\CPT_Leccion;
use Convoca\Courses\CPT_Modulo;
use Convoca\Courses\CPT_Tarea;
use Convoca\Courses\Enrollment;
use Convoca\Courses\Progress;
use Convoca\Courses\Questions;
use Convoca\Courses\Task;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$marca = 'E2E-' . gmdate( 'Ymd-His' );

/**
 * Crea una entrada publicada y devuelve su id.
 *
 * @param string $tipo   Tipo de contenido.
 * @param string $titulo Título.
 * @param string $texto  Contenido.
 * @return int
 */
function e2e_post( string $tipo, string $titulo, string $texto = '' ): int {
	return (int) wp_insert_post(
		array(
			'post_type'    => $tipo,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_content' => $texto,
		)
	);
}

/**
 * Crea una persona de prueba y devuelve [id, login, contraseña].
 *
 * @param string $prefijo Prefijo del login.
 * @param string $rol     Rol.
 * @return array{0: int, 1: string, 2: string}
 */
function e2e_persona( string $prefijo, string $rol ): array {
	$login    = $prefijo . '_' . strtolower( wp_generate_password( 5, false ) );
	$password = bin2hex( random_bytes( 8 ) );
	$id       = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => $password,
			'user_email'   => $login . '@ejemplo.test',
			'display_name' => 'Persona ' . $prefijo,
			'role'         => $rol,
		)
	);

	return array( is_wp_error( $id ) ? 0 : (int) $id, $login, $password );
}

// Curso, módulo y lecciones.
$course_id = e2e_post( CPT_Curso::TYPE, 'Curso ' . $marca, 'Curso de la suite E2E. Se borra al terminar la pasada.' );
foreach ( array(
	'precio'                => 0,
	'certificado'           => 1,
	'desbloqueo_secuencial' => '1',
	'nota_minima'           => 50,
	'acceso_examen'         => 'todas',
) as $clave => $valor ) {
	update_post_meta( $course_id, CPT_Base::META_PREFIX . $clave, $valor );
}

$module_id = e2e_post( CPT_Modulo::TYPE, 'Módulo ' . $marca );
update_post_meta( $module_id, CPT_Base::META_PREFIX . 'curso_id', $course_id );

$lecciones = array();
foreach ( array( 'Lección 1 ' . $marca, 'Lección 2 ' . $marca ) as $titulo ) {
	$lesson_id = e2e_post( CPT_Leccion::TYPE, $titulo, 'Contenido de la lección para la suite E2E.' );
	update_post_meta( $lesson_id, CPT_Base::META_PREFIX . 'modulo_id', $module_id );
	update_post_meta( $lesson_id, CPT_Base::META_PREFIX . 'tipo', 'texto' );
	$lecciones[] = $lesson_id;
}

// Examen con cuatro preguntas: única, múltiple, V/F y única.
$exam_id = e2e_post( CPT_Examen::TYPE, 'Examen ' . $marca, 'Examen de la suite E2E.' );
foreach ( array(
	'curso_id'          => $course_id,
	'num_preguntas'     => 0,
	'aleatorio'         => '0',
	'intentos'          => 10,
	'nota_minima'       => 0,
	'tiempo_maximo'     => 0,
	'retroalimentacion' => 'tras_enviar',
	'peso'              => 60,
) as $clave => $valor ) {
	update_post_meta( $exam_id, CPT_Base::META_PREFIX . $clave, $valor );
}

Questions::create(
	array( 'course_id' => $course_id, 'exam_id' => $exam_id, 'tipo' => 'unica', 'enunciado' => '¿Dónde está Oviedo?', 'opciones' => array( 'Sevilla', 'Asturias', 'Murcia' ), 'correctas' => array( '1' ), 'puntos' => 1 )
);
Questions::create(
	array( 'course_id' => $course_id, 'exam_id' => $exam_id, 'tipo' => 'multiple', 'enunciado' => 'Marca los números pares', 'opciones' => array( '2', '3', '4' ), 'correctas' => array( '0', '2' ), 'puntos' => 2 )
);
Questions::create(
	array( 'course_id' => $course_id, 'exam_id' => $exam_id, 'tipo' => 'verdadero_falso', 'enunciado' => 'Oviedo es la capital de Asturias', 'opciones' => array( 'Verdadero', 'Falso' ), 'correctas' => array( '0' ), 'puntos' => 1, 'explicacion' => 'Lo es desde 1833.' )
);
Questions::create(
	array( 'course_id' => $course_id, 'exam_id' => $exam_id, 'tipo' => 'unica', 'enunciado' => '¿Cuántas patas tiene un perro?', 'opciones' => array( 'Dos', 'Tres', 'Cuatro' ), 'correctas' => array( '2' ), 'puntos' => 1 )
);

// Dos tareas: una ya corregida (para las notas) y otra abierta (para entregar en el navegador).
$tarea_corregida = e2e_post( CPT_Tarea::TYPE, 'Tarea corregida ' . $marca, 'Tarea que ya viene evaluada en el fixture.' );
update_post_meta( $tarea_corregida, CPT_Base::META_PREFIX . 'curso_id', $course_id );
update_post_meta( $tarea_corregida, CPT_Base::META_PREFIX . 'peso', 20 );
update_post_meta( $tarea_corregida, CPT_Base::META_PREFIX . 'plazo', gmdate( 'Y-m-d', time() + ( 14 * DAY_IN_SECONDS ) ) );

$tarea_abierta = e2e_post( CPT_Tarea::TYPE, 'Tarea abierta ' . $marca, 'Tarea que la suite entrega desde el navegador.' );
update_post_meta( $tarea_abierta, CPT_Base::META_PREFIX . 'curso_id', $course_id );
update_post_meta( $tarea_abierta, CPT_Base::META_PREFIX . 'peso', 20 );
update_post_meta( $tarea_abierta, CPT_Base::META_PREFIX . 'plazo', gmdate( 'Y-m-d', time() + ( 14 * DAY_IN_SECONDS ) ) );
update_post_meta( $tarea_abierta, CPT_Base::META_PREFIX . 'adjunto', '1' );

// Personas: alumno y profesor.
list( $alumno_id, $alumno_login, $alumno_pass )   = e2e_persona( 'e2e_alumno', 'subscriber' );
list( $profe_id, $profe_login, $profe_pass )      = e2e_persona( 'e2e_profe', 'editor' );
$profe = get_userdata( $profe_id );
if ( $profe ) {
	$profe->add_cap( 'convoca_manage_courses' );
	$profe->add_cap( 'convoca_grade_courses' );
}

$matricula = Enrollment::enroll( $alumno_id, $course_id, array( 'estado' => 'activa' ) );
foreach ( $lecciones as $lesson_id ) {
	Progress::complete( $alumno_id, $course_id, $lesson_id );
}

// La tarea corregida: entrega + nota, para que el acta y las notas tengan una pieza cerrada.
Task::deliver( $tarea_corregida, $alumno_id, 'Resumen entregado por el fixture.', 0 );
$entrega = Task::submission( $tarea_corregida, $alumno_id );
if ( null !== $entrega ) {
	Task::grade( (int) $entrega['id'], 90, 'Corrección del fixture.', $profe_id );
}

// Página con los shortcodes del módulo.
$contenido = '[convoca_curso id="' . $course_id . '"]' . "\n\n"
	. '[convoca_curso_lecciones id="' . $course_id . '"]' . "\n\n"
	. '[convoca_curso_examen id="' . $exam_id . '"]' . "\n\n"
	. '[convoca_curso_tareas id="' . $course_id . '"]' . "\n\n"
	. '[convoca_curso_notas id="' . $course_id . '"]' . "\n\n"
	. '[convoca_curso_acta id="' . $course_id . '"]';

$page_id = e2e_post( 'page', 'Página ' . $marca, $contenido );

echo wp_json_encode(
	array(
		'marca'          => $marca,
		'base'           => home_url(),
		'course_id'      => $course_id,
		'module_id'      => $module_id,
		'lessons'        => $lecciones,
		'exam_id'        => $exam_id,
		'task_open'      => $tarea_abierta,
		'task_graded'    => $tarea_corregida,
		'page_id'        => $page_id,
		'page_url'       => get_permalink( $page_id ),
		'course_url'     => get_permalink( $course_id ),
		'archive_url'    => get_post_type_archive_link( CPT_Curso::TYPE ),
		'preguntas'      => Questions::count_for_exam( $exam_id ),
		'alumno'         => array( 'id' => $alumno_id, 'login' => $alumno_login, 'pass' => $alumno_pass ),
		'profe'          => array( 'id' => $profe_id, 'login' => $profe_login, 'pass' => $profe_pass ),
		'matricula'      => is_wp_error( $matricula ) ? 0 : (int) $matricula,
	)
) . "\n";
