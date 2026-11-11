<?php
/**
 * Plantilla: informe individual de alumno (PDF).
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $course, $nombre, $correo, $estado, $progreso, $notas, $conexiones, $scorm, $encuesta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_answers = is_array( $encuesta ) && isset( $encuesta['answers'] ) ? (array) $encuesta['answers'] : array();
$convoca_media   = array() !== $convoca_answers ? round( array_sum( array_map( 'intval', $convoca_answers ) ) / count( $convoca_answers ), 2 ) : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<style>
	body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
	h1 { font-size: 20px; margin: 0 0 4px; }
	h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
	table { width: 100%; border-collapse: collapse; }
	th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
	th { background: #f5f5f5; }
	.meta { color: #666; margin-bottom: 12px; }
	.badge { display: inline-block; padding: 2px 6px; border: 1px solid #999; border-radius: 3px; }
</style>
</head>
<body>
	<h1><?php echo esc_html__( 'Informe de alumno', 'convoca-courses' ); ?></h1>
	<p class="meta"><?php echo esc_html( (string) $course ); ?> · <?php echo esc_html( gmdate( 'Y-m-d H:i' ) ); ?> UTC</p>

	<table>
		<tr><th><?php esc_html_e( 'Alumno', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $nombre ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Correo', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $correo ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Estado de matrícula', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $estado ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Progreso', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $progreso ); ?> %</td></tr>
		<tr><th><?php esc_html_e( 'Nota final', 'convoca-courses' ); ?></th><td><?php echo esc_html( null === $notas['nota'] ? '—' : (string) $notas['nota'] ); ?><?php echo $notas['aprobado'] ? ' · ' . esc_html__( 'aprobado', 'convoca-courses' ) : ''; ?></td></tr>
	</table>

	<h2><?php esc_html_e( 'Conexiones', 'convoca-courses' ); ?></h2>
	<table>
		<tr><th><?php esc_html_e( 'Eventos registrados', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $conexiones['total'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Última conexión', 'convoca-courses' ); ?></th><td><?php echo esc_html( (string) $conexiones['ultima'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Tiempo SCORM', 'convoca-courses' ); ?></th><td>
			<?php
			$convoca_tiempo = 0;
			foreach ( (array) $scorm as $convoca_pieza ) {
				$convoca_tiempo += \Convoca\Courses\Reports::segundos( (string) $convoca_pieza['tiempo'] );
			}
			echo esc_html( \Convoca\Courses\Reports::duracion( $convoca_tiempo ) );
			?>
		</td></tr>
	</table>

	<h2><?php esc_html_e( 'SCORM', 'convoca-courses' ); ?></h2>
	<?php if ( array() === (array) $scorm ) : ?>
		<p><?php esc_html_e( 'Sin actividad SCORM registrada.', 'convoca-courses' ); ?></p>
	<?php else : ?>
		<table>
			<thead><tr><th><?php esc_html_e( 'Lección', 'convoca-courses' ); ?></th><th><?php esc_html_e( 'Estado', 'convoca-courses' ); ?></th><th><?php esc_html_e( 'Nota', 'convoca-courses' ); ?></th><th><?php esc_html_e( 'Tiempo', 'convoca-courses' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( (array) $scorm as $convoca_pieza ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $convoca_pieza['leccion'] ); ?></td>
					<td><?php echo esc_html( (string) $convoca_pieza['estado'] ); ?></td>
					<td><?php echo esc_html( (string) $convoca_pieza['nota'] ); ?></td>
					<td><?php echo esc_html( (string) $convoca_pieza['tiempo'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Encuesta de calidad', 'convoca-courses' ); ?></h2>
	<?php if ( '' === $convoca_media ) : ?>
		<p><?php esc_html_e( 'Sin respuesta.', 'convoca-courses' ); ?></p>
	<?php else : ?>
		<p><span class="badge"><?php echo esc_html( (string) $convoca_media ); ?> / 5</span></p>
		<?php if ( ! empty( $encuesta['comentario'] ) ) : ?>
			<p><?php echo esc_html( (string) $encuesta['comentario'] ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</body>
</html>
