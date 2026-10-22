<?php
/**
 * Plantilla: mis notas.
 *
 * Variables: ver \Convoca\Courses\Shortcodes::grades().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses convoca-courses-grades">
	<?php if ( '' !== (string) $notice ) : ?>
		<div class="convoca-courses-notice"><?php echo esc_html( (string) $notice ); ?></div>
	<?php endif; ?>

	<?php if ( ! $logged_in ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Inicia sesión para ver tus notas.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( ! $enrolled ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Necesitas estar matriculado en el curso para ver tus notas.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( empty( $pieces ) ) : ?>
		<p><?php esc_html_e( 'Este curso no tiene exámenes ni tareas: no hay nota que calcular.', 'convoca-courses' ); ?></p>
	<?php else : ?>
		<table class="convoca-courses-table convoca-courses-grades__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Pieza', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Peso', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Nota', 'convoca-courses' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pieces as $convoca_pieza ) : ?>
					<tr>
						<td>
							<?php echo esc_html( (string) $convoca_pieza['titulo'] ); ?>
							<small>(<?php echo 'examen' === $convoca_pieza['tipo'] ? esc_html__( 'examen', 'convoca-courses' ) : esc_html__( 'tarea', 'convoca-courses' ); ?>)</small>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $convoca_pieza['peso'], 2 ) ); ?></td>
						<td>
							<?php if ( null === $convoca_pieza['nota'] ) : ?>
								<?php esc_html_e( 'pendiente', 'convoca-courses' ); ?>
							<?php else : ?>
								<?php echo esc_html( number_format_i18n( (float) $convoca_pieza['nota'], 2 ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th><?php esc_html_e( 'Nota final', 'convoca-courses' ); ?></th>
					<th><?php echo esc_html( number_format_i18n( (float) $weight, 2 ) ); ?></th>
					<th>
						<?php if ( null === $final ) : ?>
							<?php esc_html_e( 'sin nota todavía', 'convoca-courses' ); ?>
						<?php else : ?>
							<?php echo esc_html( number_format_i18n( (float) $final, 2 ) ); ?>
						<?php endif; ?>
					</th>
				</tr>
			</tfoot>
		</table>

		<p class="convoca-courses-grades__summary">
			<?php
			printf(
				/* translators: %s: minimum score. */
				esc_html__( 'Mínimo para aprobar: %s sobre 100.', 'convoca-courses' ),
				esc_html( number_format_i18n( (float) $minimum, 2 ) )
			);
			?>
			<?php if ( $passed ) : ?>
				<strong><?php esc_html_e( 'Aprobado.', 'convoca-courses' ); ?></strong>
			<?php else : ?>
				<?php esc_html_e( 'Todavía no aprobado.', 'convoca-courses' ); ?>
			<?php endif; ?>
			<?php if ( ! $complete ) : ?>
				<br /><small><?php esc_html_e( 'La nota es provisional: faltan piezas sin corregir.', 'convoca-courses' ); ?></small>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
