<?php
/**
 * Plantilla: programa del curso en acordeón.
 *
 * Cada módulo es un `<details>` nativo: se abre y se cierra sin JavaScript, funciona con teclado y con
 * lector de pantalla. Se abre el módulo de la primera lección pendiente y el resto quedan cerrados.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $course_id, $modules, $progress, $enrolled, $abierto, $exams_por_modulo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses convoca-courses--programa">
	<h2 class="convoca-courses__subtitle"><?php esc_html_e( 'Programa del curso', 'convoca-courses' ); ?></h2>

	<?php if ( ! empty( $enrolled ) ) : ?>
		<div class="convoca-courses__progress" role="status">
			<div class="convoca-courses__bar">
				<span style="width:<?php echo esc_attr( (string) (float) $progress['percent'] ); ?>%"></span>
			</div>
			<p>
				<?php
				printf(
					/* translators: 1: completed lessons, 2: total lessons, 3: percentage. */
					esc_html__( '%1$d de %2$d lecciones completadas (%3$s %%).', 'convoca-courses' ),
					(int) $progress['done'],
					(int) $progress['total'],
					esc_html( (string) $progress['percent'] )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="convoca-courses__modules">
		<?php foreach ( $modules as $convoca_module ) : ?>
			<?php
			$convoca_module_id   = (int) $convoca_module['id'];
			$convoca_completadas = 0;
			$convoca_lecciones   = (array) $convoca_module['lessons'];
			$convoca_exams       = isset( $exams_por_modulo[ $convoca_module_id ] ) ? (array) $exams_por_modulo[ $convoca_module_id ] : array();
			foreach ( $convoca_lecciones as $convoca_leccion ) {
				if ( ! empty( $convoca_leccion['completed'] ) ) {
					++$convoca_completadas;
				}
			}
			?>
			<details class="convoca-courses__module"<?php echo ( (int) $abierto === $convoca_module_id ) ? ' open' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributo booleano fijo. ?>>
				<summary class="convoca-courses__module-summary">
					<span class="convoca-courses__module-title"><?php echo esc_html( (string) $convoca_module['title'] ); ?></span>
					<?php if ( ! empty( $convoca_lecciones ) ) : ?>
						<span class="convoca-courses__module-count">
							<?php
							printf(
								/* translators: 1: completed lessons, 2: total lessons. */
								esc_html__( '%1$d/%2$d', 'convoca-courses' ),
								(int) $convoca_completadas,
								count( $convoca_lecciones )
							);
							?>
						</span>
					<?php endif; ?>
				</summary>

				<div class="convoca-courses__module-body">
					<?php if ( '' !== trim( (string) $convoca_module['content'] ) ) : ?>
						<div class="convoca-courses__module-intro"><?php echo wp_kses_post( (string) $convoca_module['content'] ); ?></div>
					<?php endif; ?>

					<?php if ( empty( $convoca_lecciones ) ) : ?>
						<p class="convoca-courses__empty"><?php esc_html_e( 'Este módulo todavía no tiene lecciones.', 'convoca-courses' ); ?></p>
					<?php else : ?>
						<ol class="convoca-courses__lessons">
							<?php foreach ( $convoca_lecciones as $convoca_leccion ) : ?>
								<li id="leccion-<?php echo esc_attr( (string) $convoca_leccion['id'] ); ?>" class="convoca-courses__lesson<?php echo $convoca_leccion['completed'] ? ' is-completed' : ''; ?><?php echo $convoca_leccion['locked'] ? ' is-locked' : ''; ?>">
									<?php $convoca_url = (string) ( $convoca_leccion['url'] ?? '' ); ?>
									<?php if ( '' !== $convoca_url && empty( $convoca_leccion['locked'] ) ) : ?>
										<a class="convoca-courses__lesson-link" href="<?php echo esc_url( $convoca_url ); ?>">
											<span class="convoca-courses__lesson-number"><?php echo esc_html( (string) $convoca_leccion['number'] ); ?></span>
											<span class="convoca-courses__lesson-title"><?php echo esc_html( (string) $convoca_leccion['title'] ); ?></span>
										</a>
									<?php else : ?>
										<span class="convoca-courses__lesson-number"><?php echo esc_html( (string) $convoca_leccion['number'] ); ?></span>
										<span class="convoca-courses__lesson-title"><?php echo esc_html( (string) $convoca_leccion['title'] ); ?></span>
									<?php endif; ?>
									<?php if ( (int) $convoca_leccion['duration'] > 0 ) : ?>
										<span class="convoca-courses__lesson-duration">
											<?php
											printf(
												/* translators: %d: minutes. */
												esc_html__( '%d min', 'convoca-courses' ),
												(int) $convoca_leccion['duration']
											);
											?>
										</span>
									<?php endif; ?>
									<?php if ( ! empty( $convoca_leccion['completed'] ) ) : ?>
										<span class="convoca-courses__badge convoca-courses__badge--done"><?php esc_html_e( 'completada', 'convoca-courses' ); ?></span>
									<?php elseif ( ! empty( $convoca_leccion['locked'] ) ) : ?>
										<span class="convoca-courses__badge convoca-courses__badge--locked"><?php esc_html_e( 'bloqueada', 'convoca-courses' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>

					<?php foreach ( $convoca_exams as $convoca_exam_id ) : ?>
						<div class="convoca-courses__exam">
							<?php echo do_shortcode( '[convoca_curso_examen id="' . (int) $convoca_exam_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del shortcode. ?>
						</div>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</div>
