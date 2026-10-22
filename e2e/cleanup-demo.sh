#!/usr/bin/env bash
# Borra de la demo lo que sembró la suite E2E (curso, examen, tareas, página, personas y sus datos).
set -euo pipefail

HOST="${E2E_SSH_HOST:-andromeda}"
WP_PATH="${E2E_WP_PATH:-/var/www/demo.getconvoca.app/public}"
WP_USER="${E2E_WP_USER:-http}"
AQUI="$(cd "$(dirname "$0")" && pwd)"

test -f "$AQUI/.fixtures.json" || { echo "no hay fixture que borrar"; exit 0; }

cat > /tmp/e2e-cleanup-courses.php <<'PHP'
<?php
use Convoca\Courses\Installer;

global $wpdb;
$ids = array( (int) getenv( 'F_COURSE' ), (int) getenv( 'F_MODULE' ), (int) getenv( 'F_EXAM' ), (int) getenv( 'F_TASK_OPEN' ), (int) getenv( 'F_TASK_GRADED' ), (int) getenv( 'F_PAGE' ) );
$lecciones = array_filter( array_map( 'intval', explode( ',', (string) getenv( 'F_LESSONS' ) ) ) );

foreach ( array_merge( $ids, $lecciones ) as $id ) {
	if ( $id > 0 ) { wp_delete_post( $id, true ); }
}

foreach ( explode( ',', (string) getenv( 'F_USERS' ) ) as $uid ) {
	$uid = (int) $uid;
	if ( $uid > 0 && get_userdata( $uid ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
	}
}

$tablas = array( Installer::QUESTIONS_TABLE, Installer::ATTEMPTS_TABLE, Installer::ANSWERS_TABLE, Installer::SUBMISSIONS_TABLE );
foreach ( $tablas as $tabla ) {
	$nombre = $wpdb->prefix . $tabla;
	$wpdb->query( "DELETE FROM {$nombre} WHERE course_id = " . (int) getenv( 'F_COURSE' ) );
}
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . Installer::ATTEMPTS_TABLE . ' WHERE exam_id = ' . (int) getenv( 'F_EXAM' ) );
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . Installer::QUESTIONS_TABLE . ' WHERE exam_id = ' . (int) getenv( 'F_EXAM' ) );
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . Installer::SUBMISSIONS_TABLE . ' WHERE task_id IN (' . (int) getenv( 'F_TASK_OPEN' ) . ',' . (int) getenv( 'F_TASK_GRADED' ) . ')' );

echo "limpieza hecha\n";
PHP

json=$(cat "$AQUI/.fixtures.json")
val() { printf '%s' "$json" | python3 -c "import json,sys;d=json.load(sys.stdin);print(d$1)"; }

scp /tmp/e2e-cleanup-courses.php "$HOST:/tmp/e2e-cleanup-courses.php" >/dev/null
ssh "$HOST" "sudo F_COURSE=$(val "['course_id']") F_MODULE=$(val "['module_id']") F_EXAM=$(val "['exam_id']") F_TASK_OPEN=$(val "['task_open']") F_TASK_GRADED=$(val "['task_graded']") F_PAGE=$(val "['page_id']") F_LESSONS=$(val "['lessons']" | tr -d ' []') F_USERS=$(val "['alumno']['id']"),$(val "['profe']['id']") -u $WP_USER wp --path=$WP_PATH eval-file /tmp/e2e-cleanup-courses.php" 2>&1 | tail -2

rm -f "$AQUI/.fixtures.json"
echo "fixture local borrado"
