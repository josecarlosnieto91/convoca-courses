<?php
/**
 * Plantilla: lección bloqueada (sin matrícula o sin desbloquear).
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $course_id, $title, $enrolled, $course_url.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="convoca-courses convoca-courses--locked">
	<h2 class="convoca-courses__title"><?php echo esc_html( (string) $title ); ?></h2>
	<div class="convoca-courses-notice convoca-courses-notice--locked">
		<?php
		if ( $enrolled ) {
			esc_html_e( 'Esta lección se abre cuando completes las anteriores.', 'convoca-courses' );
		} else {
			esc_html_e( 'Necesitas matricularte en el curso para ver esta lección.', 'convoca-courses' );
		}
		?>
	</div>
	<?php if ( '' !== (string) $course_url ) : ?>
		<p><a class="convoca-courses__button" href="<?php echo esc_url( (string) $course_url ); ?>"><?php esc_html_e( 'Ir a la portada del curso', 'convoca-courses' ); ?></a></p>
	<?php endif; ?>
</div>
