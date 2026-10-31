<?php
/**
 * Plantilla: certificado en PDF.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $code, $course, $person, $date, $center, $nota, $verify, $qr, $logo, $firma.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_firma = isset( $firma ) && is_array( $firma ) ? $firma : array(
	'texto' => (string) $center,
	'cargo' => '',
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<style>
		@page { size: A4 landscape; margin: 0; }
		html, body { height: 100%; margin: 0; padding: 0; }
		body { font-family: DejaVu Sans, sans-serif; color: #1f2430; }

		/* Dompdf no estira un `height:100%`: la caja tiene que llevar la medida de la página en mm o
			el contenido se queda pegado arriba. A4 horizontal = 297 × 210 mm, sin márgenes de página
			(courses#30). */
		.sheet { background: #fdfaf7; border-collapse: collapse; height: 210mm; width: 297mm; }
		.sheet > tr > td { padding: 0; text-align: center; vertical-align: middle; }

		/* Dompdf no hereda `text-align` del td a los bloques hijos: va explícito en cada uno, o el
			texto se queda a la izquierda aunque la celda esté centrada (courses#30). */
		.frame { border: 7px solid #7d0032; margin: 12mm; padding: 8mm 14mm 13mm; text-align: center; }
		.frame h1, .frame p, .frame div { text-align: center; }
		.logo { height: 21mm; margin-bottom: 5mm; }
		.eyebrow { color: #7d0032; font-size: 10px; letter-spacing: 3px; margin: 0 0 3mm; text-transform: uppercase; }
		h1 { font-size: 31px; letter-spacing: 0.5px; margin: 0 0 8mm; }
		.lead { color: #4a5160; font-size: 11.5px; margin: 0; }
		.person { font-size: 32px; font-weight: bold; margin: 3mm 0 1mm; }
		.course { font-size: 19px; margin: 1mm 0 7mm; }
		.meta { color: #4a5160; font-size: 11px; margin: 1mm 0; }
		.bottom { margin-top: 6mm; }
		.verify { margin: 0 auto; width: 34mm; }
		.verify img { height: 30mm; width: 30mm; }
		.convoca-qr-fallback__label { color: #7d0032; display: block; font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; }
		.convoca-qr-fallback__url { display: block; font-size: 7.6px; line-height: 1.35; padding: 0 6mm; word-break: break-all; }
		.code { font-family: DejaVu Sans Mono, monospace; font-size: 11px; letter-spacing: 1px; margin: 3mm 0 0; }
		.sign { border-top: 1px solid #b9a0aa; display: inline-block; margin-top: 9mm; padding-top: 2mm; width: 80mm; }
		.sign__name { font-size: 11px; font-weight: bold; }
		.sign__role { color: #4a5160; font-size: 9.5px; }
	</style>
</head>
<body>
	<table class="sheet">
		<tr>
			<td>
				<div class="frame">
					<?php if ( ! empty( $logo ) ) : ?>
						<img class="logo" src="<?php echo esc_attr( (string) $logo ); ?>" alt="<?php echo esc_attr( (string) $center ); ?>" />
					<?php endif; ?>

					<p class="eyebrow"><?php echo esc_html( (string) $center ); ?></p>
					<h1><?php esc_html_e( 'Certificado de aprovechamiento', 'convoca-courses' ); ?></h1>

					<p class="lead"><?php esc_html_e( 'Se certifica que', 'convoca-courses' ); ?></p>
					<p class="person"><?php echo esc_html( (string) $person ); ?></p>
					<p class="lead"><?php esc_html_e( 'ha completado el curso', 'convoca-courses' ); ?></p>
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

					<div class="bottom">
						<?php if ( ! empty( $qr ) ) : ?>
							<div class="verify">
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
					</div>

					<div class="sign">
						<div class="sign__name"><?php echo esc_html( (string) ( $convoca_firma['texto'] ?? '' ) ); ?></div>
						<?php if ( ! empty( $convoca_firma['cargo'] ) ) : ?>
							<div class="sign__role"><?php echo esc_html( (string) $convoca_firma['cargo'] ); ?></div>
						<?php endif; ?>
					</div>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>
