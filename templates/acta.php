<?php
/**
 * Plantilla: acta del curso (profesorado).
 *
 * Variables: ver Shortcodes::gradebook().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses-gradebook">
	<p class="convoca-courses-gradebook__actions">
		<a class="convoca-button" href="<?php echo esc_url( (string) $csv_url ); ?>">
			<?php esc_html_e( 'Descargar acta en CSV', 'convoca-courses' ); ?>
		</a>
		<?php if ( $weights_ok ) : ?>
			<span class="convoca-courses-notice convoca-courses-notice--ok"><?php esc_html_e( 'Los pesos suman 100.', 'convoca-courses' ); ?></span>
		<?php else : ?>
			<span class="convoca-courses-notice convoca-courses-notice--warning">
				<?php
				printf(
					/* translators: %s: sum of weights. */
					esc_html__( 'Los pesos suman %s: revisa el peso de cada examen y cada tarea.', 'convoca-courses' ),
					esc_html( number_format_i18n( (float) $weights_sum, 2 ) )
				);
				?>
			</span>
		<?php endif; ?>
	</p>

	<?php if ( empty( $rows ) ) : ?>
		<p><?php esc_html_e( 'Este curso no tiene alumnado matriculado.', 'convoca-courses' ); ?></p>
	<?php else : ?>
		<table class="convoca-courses-table convoca-courses-gradebook__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Alumno', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Progreso', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Nota final', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Estado', 'convoca-courses' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $convoca_fila ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $convoca_fila['nombre'] ); ?></td>
						<td>
							<?php
							printf(
								/* translators: %s: percentage. */
								esc_html__( '%s %%', 'convoca-courses' ),
								esc_html( number_format_i18n( (float) $convoca_fila['progreso'], 1 ) )
							);
							?>
						</td>
						<td>
							<?php if ( null === $convoca_fila['nota'] ) : ?>
								<?php esc_html_e( 'sin nota', 'convoca-courses' ); ?>
							<?php else : ?>
								<?php echo esc_html( number_format_i18n( (float) $convoca_fila['nota'], 2 ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo $convoca_fila['aprobado'] ? esc_html__( 'aprobado', 'convoca-courses' ) : esc_html__( 'no aprobado', 'convoca-courses' ); ?>
							<?php if ( empty( $convoca_fila['completo'] ) ) : ?>
								<small><?php esc_html_e( '(provisional)', 'convoca-courses' ); ?></small>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
