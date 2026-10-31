<?php
/**
 * Plantilla: certificado en PDF.
 *
 * Una sola página A4 horizontal. Las medidas van en mm porque Dompdf no estira `height:100%`, y el
 * centrado va explícito en cada bloque porque tampoco hereda `text-align` (courses#30, #34).
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $code, $course, $person, $date, $center, $nota, $verify, $qr, $logo, $firma, $fuentes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_firma   = isset( $firma ) && is_array( $firma ) ? $firma : array(
	'texto' => (string) $center,
	'cargo' => '',
);
$convoca_fuentes = isset( $fuentes ) ? (string) $fuentes : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<style>
		@font-face { font-family: 'Lato'; font-style: normal; font-weight: 400; src: url('<?php echo esc_url( $convoca_fuentes . 'Lato-Regular.ttf' ); ?>'); }
		@font-face { font-family: 'Lato'; font-style: normal; font-weight: 700; src: url('<?php echo esc_url( $convoca_fuentes . 'Lato-Bold.ttf' ); ?>'); }
		@font-face { font-family: 'Lato'; font-style: normal; font-weight: 900; src: url('<?php echo esc_url( $convoca_fuentes . 'Lato-Black.ttf' ); ?>'); }
		@font-face { font-family: 'Outfit'; font-style: normal; font-weight: 600; src: url('<?php echo esc_url( $convoca_fuentes . 'Outfit-SemiBold.ttf' ); ?>'); }
		@font-face { font-family: 'Outfit'; font-style: normal; font-weight: 700; src: url('<?php echo esc_url( $convoca_fuentes . 'Outfit-Bold.ttf' ); ?>'); }

		@page { margin: 0; size: A4 landscape; }
		html, body { height: 100%; margin: 0; padding: 0; }
		body { color: #16181b; font-family: 'Lato', sans-serif; }

		/* 195 mm y no 210: con la altura exacta de la página, un borde o un logo de más parte el
			documento en varias hojas (courses#34). */
		.sheet { background: #ffffff; border-collapse: collapse; height: 195mm; width: 291mm; }
		.sheet > tr > td { padding: 0; text-align: center; vertical-align: middle; }

		.frame { border: 6px solid #7d0032; margin: 0 6mm; padding: 7mm 12mm 6mm; text-align: center; }
		.frame h1, .frame p, .frame div { text-align: center; }

		.logo { height: 17mm; margin-bottom: 3mm; }
		.eyebrow { color: #7d0032; font-size: 9.5px; font-weight: 700; letter-spacing: 3.2px; margin: 0 0 2mm; text-transform: uppercase; }

		h1 { color: #2a0a1e; font-family: 'Outfit', sans-serif; font-size: 30px; font-weight: 700; margin: 0 0 5mm; }

		.lead { color: #59616a; font-size: 11px; letter-spacing: 0.6px; margin: 0; text-transform: uppercase; }
		.person { color: #16181b; font-family: 'Outfit', sans-serif; font-size: 33px; font-weight: 600; line-height: 1.15; margin: 2mm 0 1.5mm; }
		.course { color: #2a0a1e; font-size: 18px; font-weight: 700; margin: 1.5mm 0 0; }

		.rule { background: #ff8700; height: 2.5px; margin: 5mm auto; width: 46mm; }

		.meta { color: #59616a; font-size: 10.5px; margin: 1mm 0 0; }
		.meta strong { color: #16181b; font-weight: 700; }

		/* Pie en dos columnas: verificación a la izquierda, firma a la derecha. */
		.foot { border-collapse: collapse; margin-top: 6mm; width: 100%; }
		.foot td { padding: 0; text-align: center; vertical-align: bottom; width: 50%; }
		.foot .qr img { height: 26mm; width: 26mm; }
		.foot .convoca-qr-fallback__url { color: #59616a; display: block; font-size: 7px; line-height: 1.3; padding: 0 4mm; word-break: break-all; }
		.foot .label { color: #7d0032; display: block; font-size: 7.5px; font-weight: 700; letter-spacing: 1.4px; text-transform: uppercase; }

		.sign { border-top: 1px solid #d9d2c8; display: inline-block; padding-top: 2mm; width: 62mm; }
		.sign__name { color: #16181b; font-size: 11px; font-weight: 700; }
		.sign__role { color: #59616a; font-size: 9px; }
	</style>
</head>
<body>
	<table class="sheet">
		<tr>
			<td>
				<div class="frame">
					<?php if ( ! empty( $logo ) ) : ?>
						<img class="logo" src="<?php echo esc_attr( (string) $logo ); ?>" alt="" />
					<?php endif; ?>

					<p class="eyebrow"><?php echo esc_html( (string) $center ); ?></p>
					<h1><?php esc_html_e( 'Certificado de aprovechamiento', 'convoca-courses' ); ?></h1>

					<p class="lead"><?php esc_html_e( 'Se certifica que', 'convoca-courses' ); ?></p>
					<p class="person"><?php echo esc_html( (string) $person ); ?></p>
					<p class="lead"><?php esc_html_e( 'ha completado el curso', 'convoca-courses' ); ?></p>
					<p class="course"><?php echo esc_html( (string) $course ); ?></p>

					<div class="rule"></div>

					<p class="meta">
						<?php
						printf(
							/* translators: %s: issue date. */
							esc_html__( 'Emitido el %s', 'convoca-courses' ),
							'<strong>' . esc_html( (string) $date ) . '</strong>'
						);
						?>
						<?php if ( ! empty( $nota ) ) : ?>
							<?php
							printf(
								/* translators: %s: score, 0-100. */
								esc_html__( ' · Nota final %s sobre 100', 'convoca-courses' ),
								'<strong>' . esc_html( (string) $nota ) . '</strong>'
							);
							?>
						<?php endif; ?>
					</p>

					<table class="foot">
						<tr>
							<td>
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
										<span class="label"><?php esc_html_e( 'Verificación', 'convoca-courses' ); ?></span>
									</div>
								<?php endif; ?>
							</td>
							<td>
								<div class="sign">
									<div class="sign__name"><?php echo esc_html( (string) ( $convoca_firma['texto'] ?? '' ) ); ?></div>
									<?php if ( ! empty( $convoca_firma['cargo'] ) ) : ?>
										<div class="sign__role"><?php echo esc_html( (string) $convoca_firma['cargo'] ); ?></div>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					</table>

					<p class="meta"><?php echo esc_html( (string) $code ); ?></p>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>
