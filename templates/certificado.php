<?php
/**
 * Plantilla: certificado en PDF.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $code, $course, $person, $date, $center.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<style>
		body { font-family: DejaVu Sans, sans-serif; color: #1f2430; margin: 0; }
		.frame { border: 6px solid #7d0032; padding: 36px 44px; }
		.eyebrow { font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #7d0032; }
		h1 { font-size: 30px; margin: 6px 0 26px; }
		.person { font-size: 24px; font-weight: bold; margin: 18px 0 6px; }
		.course { font-size: 18px; margin: 0 0 26px; }
		.meta { font-size: 12px; color: #4a5160; }
		.code { margin-top: 30px; font-family: monospace; font-size: 13px; }
		.qr { margin-top: 18px; width: 110px; height: 110px; }
		.qr img { width: 100%; height: 100%; }
		.convoca-qr-fallback { font-size: 9px; word-break: break-all; }
	</style>
</head>
<body>
	<div class="frame">
		<p class="eyebrow"><?php echo esc_html( (string) $center ); ?></p>
		<h1><?php esc_html_e( 'Certificado de aprovechamiento', 'convoca-courses' ); ?></h1>

		<p class="meta"><?php esc_html_e( 'Se certifica que', 'convoca-courses' ); ?></p>
		<p class="person"><?php echo esc_html( (string) $person ); ?></p>
		<p class="meta"><?php esc_html_e( 'ha completado el curso', 'convoca-courses' ); ?></p>
		<p class="course"><?php echo esc_html( (string) $course ); ?></p>

		<p class="meta">
			<?php
			printf(
				/* translators: %s: issue date. */
				esc_html__( 'Fecha de emisión: %s', 'convoca-courses' ),
				esc_html( (string) $date )
			);
			?>
		</p>

		<?php if ( ! empty( $nota ) ) : ?>
			<p class="meta">
				<?php
				printf(
					/* translators: %s: score, 0-100. */
					esc_html__( 'Nota final: %s sobre 100', 'convoca-courses' ),
					esc_html( (string) $nota )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $qr ) ) : ?>
			<div class="qr">
			<?php
			echo wp_kses(
				(string) $qr,
				array(
					'img'  => array(
						'src' => true,
						'alt' => true,
					),
					'span' => array( 'class' => true ),
				)
			);
			?>
							</div>
		<?php endif; ?>

		<p class="code"><?php echo esc_html( (string) $code ); ?></p>
		<p class="meta">
			<?php
			printf(
				/* translators: %s: verification URL. */
				esc_html__( 'Verificable en %s', 'convoca-courses' ),
				esc_html( ! empty( $verify ) ? (string) $verify : home_url( '/verificar-curso/?codigo=' . (string) $code ) )
			);
			?>
		</p>
	</div>
</body>
</html>
