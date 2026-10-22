<?php
/**
 * Plantilla: tareas del curso.
 *
 * Variables: ver \Convoca\Courses\Task_View::data().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses-tasks">
	<?php if ( '' !== $notice ) : ?>
		<div class="convoca-courses-notice"><?php echo esc_html( (string) $notice ); ?></div>
	<?php endif; ?>

	<?php if ( empty( $tasks ) ) : ?>
		<p><?php esc_html_e( 'Este curso no tiene tareas.', 'convoca-courses' ); ?></p>
	<?php elseif ( ! $logged_in ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Inicia sesión para ver y entregar las tareas.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( ! $enrolled ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Necesitas estar matriculado en el curso para entregar tareas.', 'convoca-courses' ); ?>
		</p>
	<?php endif; ?>

	<?php foreach ( $tasks as $convoca_tarea ) : ?>
		<?php
		$convoca_entrega = $convoca_tarea['submission'];
		$convoca_puede   = $can_submit;
		?>
		<section class="convoca-courses-task" id="convoca-tarea-<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>">
			<h4><?php echo esc_html( (string) $convoca_tarea['title'] ); ?></h4>

			<p class="convoca-courses-task__meta">
				<?php if ( '' !== (string) $convoca_tarea['deadline'] ) : ?>
					<?php
					printf(
						/* translators: %s: deadline date. */
						esc_html__( 'Plazo: %s', 'convoca-courses' ),
						esc_html( (string) $convoca_tarea['deadline'] )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Sin plazo de entrega.', 'convoca-courses' ); ?>
				<?php endif; ?>

				<?php if ( (float) $convoca_tarea['weight'] > 0 ) : ?>
					—
					<?php
					printf(
						/* translators: %s: weight, 0-100. */
						esc_html__( 'Peso en la nota: %s', 'convoca-courses' ),
						esc_html( number_format_i18n( (float) $convoca_tarea['weight'], 2 ) )
					);
					?>
				<?php endif; ?>
			</p>

			<?php if ( '' !== (string) $convoca_tarea['intro'] ) : ?>
				<div class="convoca-courses-task__intro"><?php echo wp_kses_post( (string) $convoca_tarea['intro'] ); ?></div>
			<?php endif; ?>

			<?php if ( null !== $convoca_entrega ) : ?>
				<div class="convoca-courses-task__submission">
					<p>
						<?php
						printf(
							/* translators: %s: submission date and time. */
							esc_html__( 'Entregada el %s.', 'convoca-courses' ),
							esc_html( (string) $convoca_entrega['entregada_en'] )
						);
						?>
						<?php if ( 'fuera_de_plazo' === $convoca_entrega['estado'] ) : ?>
							<strong><?php esc_html_e( '(fuera de plazo)', 'convoca-courses' ); ?></strong>
						<?php endif; ?>
					</p>

					<?php if ( '' !== (string) $convoca_entrega['contenido'] ) : ?>
						<p><?php echo esc_html( (string) $convoca_entrega['contenido'] ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== (string) $convoca_tarea['attachment'] ) : ?>
						<p>
							<a href="<?php echo esc_url( (string) $convoca_tarea['attachment'] ); ?>">
								<?php esc_html_e( 'Fichero entregado', 'convoca-courses' ); ?>
							</a>
						</p>
					<?php endif; ?>

					<?php if ( 'corregida' === $convoca_entrega['estado'] ) : ?>
						<p class="convoca-courses-task__grade">
							<?php
							printf(
								/* translators: %s: score, 0-100. */
								esc_html__( 'Nota: %s', 'convoca-courses' ),
								esc_html( number_format_i18n( (float) $convoca_entrega['nota'], 2 ) )
							);
							?>
						</p>
						<?php if ( '' !== (string) $convoca_entrega['comentario'] ) : ?>
							<blockquote><?php echo esc_html( (string) $convoca_entrega['comentario'] ); ?></blockquote>
						<?php endif; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Pendiente de corrección.', 'convoca-courses' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $convoca_puede ) : ?>
				<p class="convoca-courses-notice convoca-courses-notice--warning">
					<?php esc_html_e( 'Tu acceso al curso no permite entregar tareas ahora mismo.', 'convoca-courses' ); ?>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( (string) $form_url ); ?>" enctype="multipart/form-data" class="convoca-courses-task__form">
					<input type="hidden" name="action" value="<?php echo esc_attr( \Convoca\Courses\Handlers::ACTION_TASK_SUBMIT ); ?>" />
					<input type="hidden" name="task_id" value="<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>" />
					<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>" />
					<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $convoca_tarea['nonce'] ); ?>" />

					<p>
						<label for="convoca_entrega_<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>">
							<?php echo null !== $convoca_entrega ? esc_html__( 'Volver a entregar (sustituye la entrega anterior)', 'convoca-courses' ) : esc_html__( 'Tu entrega', 'convoca-courses' ); ?>
						</label><br />
						<textarea
							id="convoca_entrega_<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>"
							name="convoca_entrega"
							rows="4"
							class="large-text"></textarea>
					</p>

					<?php if ( (bool) $convoca_tarea['allows_file'] ) : ?>
						<p>
							<label for="convoca_adjunto_<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>">
								<?php esc_html_e( 'Fichero (opcional)', 'convoca-courses' ); ?>
							</label><br />
							<input type="file" id="convoca_adjunto_<?php echo esc_attr( (string) $convoca_tarea['id'] ); ?>" name="convoca_adjunto" />
						</p>
					<?php endif; ?>

					<p>
						<button type="submit" class="convoca-button">
							<?php esc_html_e( 'Entregar', 'convoca-courses' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
