<?php
/**
 * Plantilla: certificado en PDF — diseño B3c (banda uva).
 *
 * Una sola página A4 horizontal. Las medidas van en mm porque Dompdf no estira `height:100%`, y el
 * centrado va explícito en cada bloque porque tampoco hereda `text-align` (courses#30, #34).
 *
 * Dompdf **no soporta gradientes CSS**: la banda va en sólido `#320028` con filete `#ff8700`, como
 * pidió JC (courses#35). El rótulo vertical se escribe letra a letra porque `transform: rotate()`
 * no es fiable en Dompdf.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $code, $course, $person, $date, $center, $nota, $verify, $qr, $logo, $firma, $fuentes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_firma = isset( $firma ) && is_array( $firma ) ? $firma : array(
	'texto' => (string) $center,
	'cargo' => '',
);

$convoca_rotulo = 'CERTIFICADO';
$convoca_nota   = isset( $nota ) ? (string) $nota : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<style>
		/* Las TTF se registran en Dompdf desde el generador: con la fuente enlazada por CSS volvía a
			Helvetica. */

		@page { margin: 0; size: A4 landscape; }
		html, body { margin: 0; padding: 0; }
		body { color: #1e1e1e; font-family: 'Lato', sans-serif; }

		.sheet { background: #fff7ec; border-collapse: collapse; height: 207mm; width: 297mm; }
		.sheet > tr > td { padding: 0; vertical-align: top; }

		/* ── Banda uva ─────────────────────────────────────────────── */
		/* El alto va en el td y sin padding vertical: Dompdf no estira el fondo del td con la fila,
			así que la banda se quedaba a media altura. Con alto propio más padding sumaba 3 páginas. */
		.banda { background: #320028; height: 207mm; text-align: center; vertical-align: top; width: 42mm; }
		.banda__rotulo {
			color: #ffffff;
			font-family: 'Outfit', sans-serif;
			font-size: 9mm;
			font-weight: 700;
			letter-spacing: 0.06em;
			line-height: 1.02;
			padding-top: 12mm;
			text-align: center;
		}
		.banda__emblema { margin-top: 96mm; }
		.banda__emblema img { height: 26mm; width: 26mm; }

		/* Filete naranja entre la banda y el cuerpo. */
		.filete { background: #ff8700; height: 207mm; width: 3mm; }

		/* ── Cuerpo ────────────────────────────────────────────────── */
		/* Sin `width`: con 252 mm más el padding horizontal sumaba 283 mm y, con la banda y el filete,
			la tabla se salía de los 297 de la hoja (la fecha acababa fuera del papel). */
		.cuerpo { padding: 17mm 16mm 12mm 15mm; vertical-align: top; }

		/* `table-layout: fixed`: sin anchos, el rótulo del centro se comía la fecha y solo salía el «3». */
		.cabecera { border-bottom: 0.3mm solid #e6dccd; padding-bottom: 3mm; table-layout: fixed; width: 100%; }
		.cabecera td { padding: 0; vertical-align: bottom; }
		.cabecera td:first-child { width: 68%; }
		.cabecera td:last-child { width: 32%; }
		.cabecera__centro { color: #a65d24; font-size: 8.5px; font-weight: 700; letter-spacing: 2.4px; text-align: left; text-transform: uppercase; }
		.cabecera__fecha { color: #6d7773; font-size: 9px; letter-spacing: 0.8px; text-align: right; white-space: nowrap; }

		.lead { color: #6d7773; font-size: 10.5px; letter-spacing: 1.2px; margin: 12mm 0 0; text-align: left; text-transform: uppercase; }

		.person { color: #1e1e1e; font-family: 'Outfit', sans-serif; font-size: 34px; font-weight: 700; line-height: 1.05; margin: 3mm 0 0; text-align: left; }

		.rule { background: #ffab00; height: 1.5mm; margin: 4mm 0 0; width: 34mm; }

		.curso { color: #b05500; font-family: 'Outfit', sans-serif; font-size: 17px; font-weight: 600; line-height: 1.25; margin: 7mm 0 0; text-align: left; }

		/* Métricas con barra verde a la izquierda. */
		.metricas { border-collapse: collapse; margin-top: 12mm; width: 100%; }
		.metricas td { padding: 0; text-align: left; vertical-align: top; width: 33%; }
		.metrica__barra { background: #2e6b3a; display: inline-block; height: 6.5mm; margin-right: 2.5mm; vertical-align: middle; width: 1mm; }
		.metrica__valor { color: #1e1e1e; font-family: 'Outfit', sans-serif; font-size: 12px; font-weight: 700; vertical-align: middle; }
		.metrica__etiqueta { color: #6d7773; display: block; font-size: 7px; letter-spacing: 1.4px; margin-left: 3.5mm; margin-top: 1mm; text-transform: uppercase; }

		/* Pie: firma a la izquierda, verificación a la derecha. */
		.pie { border-collapse: collapse; margin-top: 14mm; width: 100%; }
		.pie td { padding: 0; text-align: left; vertical-align: bottom; }
		.pie__firma { width: 60%; }
		.pie__qr { text-align: right; width: 40%; }

		.firma__linea { border-top: 0.3mm solid #b9ada0; padding-top: 2mm; width: 56mm; }
		.firma__nombre { color: #1e1e1e; font-size: 10.5px; font-weight: 700; }
		.firma__cargo { color: #6d7773; font-size: 8.5px; }

		.qr img { height: 23mm; width: 23mm; }
		.qr__label { color: #6d7773; display: block; font-size: 6.5px; letter-spacing: 1.2px; margin-top: 1mm; text-transform: uppercase; }
		.qr__code { color: #1e1e1e; display: block; font-size: 7.5px; letter-spacing: 0.6px; }
		.qr__url { color: #6d7773; display: block; font-size: 6px; line-height: 1.3; word-break: break-all; }
	</style>
</head>
<body>
	<table class="sheet">
		<tr>
			<td class="banda">
				<div class="banda__rotulo">
					<?php
					// Letra a letra: `transform: rotate()` no es fiable en Dompdf.
					foreach ( str_split( (string) $convoca_rotulo ) as $convoca_letra ) {
						echo esc_html( $convoca_letra ) . '<br />';
					}
					?>
				</div>
				<?php if ( ! empty( $logo ) ) : ?>
					<div class="banda__emblema">
						<img src="<?php echo esc_attr( (string) $logo ); ?>" alt="" />
					</div>
				<?php endif; ?>
			</td>
			<td class="filete"></td>
			<td class="cuerpo">
				<table class="cabecera">
					<tr>
						<td class="cabecera__centro"><?php echo esc_html( (string) $center ); ?></td>
						<td class="cabecera__fecha"><?php echo esc_html( (string) $date ); ?></td>
					</tr>
				</table>

				<p class="lead"><?php esc_html_e( 'Se certifica que', 'convoca-courses' ); ?></p>
				<p class="person"><?php echo esc_html( (string) $person ); ?></p>
				<div class="rule"></div>
				<p class="curso"><?php echo esc_html( (string) $course ); ?></p>

				<table class="metricas">
					<tr>
						<?php if ( '' !== $convoca_nota ) : ?>
							<td>
								<span class="metrica__barra"></span><span class="metrica__valor"><?php echo esc_html( $convoca_nota ); ?></span>
								<span class="metrica__etiqueta"><?php esc_html_e( 'Nota final', 'convoca-courses' ); ?></span>
							</td>
						<?php endif; ?>
						<td>
							<span class="metrica__barra"></span><span class="metrica__valor"><?php echo esc_html( (string) $code ); ?></span>
							<span class="metrica__etiqueta"><?php esc_html_e( 'Código de verificación', 'convoca-courses' ); ?></span>
						</td>
					</tr>
				</table>

				<table class="pie">
					<tr>
						<td class="pie__firma">
							<div class="firma__linea">
								<span class="firma__nombre"><?php echo esc_html( (string) ( $convoca_firma['texto'] ?? '' ) ); ?></span>
								<?php if ( ! empty( $convoca_firma['cargo'] ) ) : ?>
									<br /><span class="firma__cargo"><?php echo esc_html( (string) $convoca_firma['cargo'] ); ?></span>
								<?php endif; ?>
							</div>
						</td>
						<td class="pie__qr">
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
									<span class="qr__label"><?php esc_html_e( 'Verificación', 'convoca-courses' ); ?></span>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
