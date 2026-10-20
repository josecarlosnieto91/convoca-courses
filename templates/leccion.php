<?php
/**
 * Plantilla: una lección.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $lesson_id, $course_id, $title, $type, $content, $attachment_id, $attachment, $video, $video_allowed, $completed, $complete_url, $complete_nonce, $duration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="convoca-courses convoca-courses--lesson" data-lesson="<?php echo esc_attr( (string) (int) $lesson_id ); ?>">
	<header class="convoca-courses__header">
		<h2 class="convoca-courses__title"><?php echo esc_html( (string) $title ); ?></h2>
		<ul class="convoca-courses__facts">
			<li><span class="convoca-courses__label"><?php esc_html_e( 'Tipo', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $type ); ?></li>
			<?php if ( (int) $duration > 0 ) : ?>
				<li>
					<span class="convoca-courses__label"><?php esc_html_e( 'Duración', 'convoca-courses' ); ?></span>
					<?php
					printf(
						/* translators: %d: minutes. */
						esc_html__( '%d min', 'convoca-courses' ),
						(int) $duration
					);
					?>
				</li>
			<?php endif; ?>
		</ul>
	</header>

	<?php if ( 'video' === $type ) : ?>
		<?php if ( ! $video_allowed ) : ?>
			<div class="convoca-courses-notice convoca-courses-notice--locked">
				<?php esc_html_e( 'El vídeo de esta lección requiere una licencia PRO activa. El resto del curso sigue disponible.', 'convoca-courses' ); ?>
			</div>
		<?php elseif ( '' !== (string) $video['src'] ) : ?>
			<div class="convoca-courses__video">
				<video controls preload="metadata" playsinline data-lesson="<?php echo esc_attr( (string) (int) $lesson_id ); ?>">
					<source src="<?php echo esc_url( (string) $video['src'] ); ?>" />
					<?php esc_html_e( 'Tu navegador no puede reproducir este vídeo.', 'convoca-courses' ); ?>
					<a href="<?php echo esc_url( (string) $video['src'] ); ?>"><?php esc_html_e( 'Descargar el vídeo', 'convoca-courses' ); ?></a>
				</video>
			</div>
		<?php else : ?>
			<div class="convoca-courses-notice"><?php esc_html_e( 'El vídeo de esta lección todavía no está configurado.', 'convoca-courses' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="convoca-courses__content">
		<?php echo wp_kses_post( (string) $content ); ?>
	</div>

	<?php if ( 'adjunto' === $type || 'pdf' === $type ) : ?>
		<?php if ( '' !== (string) $attachment ) : ?>
			<p class="convoca-courses__attachment">
				<a class="convoca-courses__button" href="<?php echo esc_url( (string) $attachment ); ?>" target="_blank" rel="noopener">
					<?php
					echo 'pdf' === $type
						? esc_html__( 'Abrir el PDF de la lección', 'convoca-courses' )
						: esc_html__( 'Descargar el adjunto', 'convoca-courses' );
					?>
				</a>
			</p>
		<?php else : ?>
			<div class="convoca-courses-notice"><?php esc_html_e( 'Esta lección todavía no tiene el fichero adjunto.', 'convoca-courses' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>

	<footer class="convoca-courses__footer">
		<?php if ( $completed ) : ?>
			<p class="convoca-courses__badge"><?php esc_html_e( 'Lección completada', 'convoca-courses' ); ?></p>
		<?php else : ?>
			<form class="convoca-courses__form" method="post" action="<?php echo esc_url( (string) $complete_url ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( \Convoca\Courses\Handlers::ACTION_COMPLETE ); ?>" />
				<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson_id ); ?>" />
				<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $complete_nonce ); ?>" />
				<button type="submit" class="convoca-courses__button"><?php esc_html_e( 'Marcar como completada', 'convoca-courses' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( (int) $course_id > 0 ) : ?>
			<p><a href="<?php echo esc_url( (string) get_permalink( (int) $course_id ) ); ?>"><?php esc_html_e( 'Volver al curso', 'convoca-courses' ); ?></a></p>
		<?php endif; ?>
	</footer>
</article>
