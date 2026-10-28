<?php
/**
 * Plantilla: índice de lecciones con progreso.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $course_id, $title, $enrolled, $progress, $modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses convoca-courses--index">
	<h3 class="convoca-courses__subtitle"><?php echo esc_html( (string) $title ); ?></h3>

	<?php if ( $enrolled ) : ?>
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

	<?php if ( empty( $modules ) ) : ?>
		<p><?php esc_html_e( 'Este curso todavía no tiene lecciones publicadas.', 'convoca-courses' ); ?></p>
	<?php else : ?>
		<ol class="convoca-courses__modules">
			<?php foreach ( $modules as $convoca_courses_module ) : ?>
				<li class="convoca-courses__module">
					<h4><?php echo esc_html( (string) $convoca_courses_module['title'] ); ?></h4>
					<ul class="convoca-courses__lessons">
						<?php foreach ( $convoca_courses_module['lessons'] as $convoca_courses_lesson ) : ?>
							<li id="leccion-<?php echo esc_attr( (string) $convoca_courses_lesson['id'] ); ?>" class="convoca-courses__lesson<?php echo $convoca_courses_lesson['completed'] ? ' is-completed' : ''; ?><?php echo $convoca_courses_lesson['locked'] ? ' is-locked' : ''; ?>">
								<?php
								$convoca_courses_url = (string) ( $convoca_courses_lesson['url'] ?? '' );
								if ( '' !== $convoca_courses_url && ! $convoca_courses_lesson['locked'] ) :
									?>
									<a class="convoca-courses__lesson-link" href="<?php echo esc_url( $convoca_courses_url ); ?>">
										<span class="convoca-courses__lesson-number"><?php echo esc_html( (string) $convoca_courses_lesson['number'] ); ?></span>
										<span class="convoca-courses__lesson-title"><?php echo esc_html( (string) $convoca_courses_lesson['title'] ); ?></span>
									</a>
								<?php else : ?>
									<span class="convoca-courses__lesson-number"><?php echo esc_html( (string) $convoca_courses_lesson['number'] ); ?></span>
									<span class="convoca-courses__lesson-title"><?php echo esc_html( (string) $convoca_courses_lesson['title'] ); ?></span>
								<?php endif; ?>
								<?php if ( $convoca_courses_lesson['completed'] ) : ?>
									<span class="convoca-courses__badge"><?php esc_html_e( 'completada', 'convoca-courses' ); ?></span>
								<?php elseif ( $convoca_courses_lesson['locked'] ) : ?>
									<span class="convoca-courses__badge convoca-courses__badge--locked"><?php esc_html_e( 'bloqueada', 'convoca-courses' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
