<?php
/**
 * Plantilla: metabox del curso.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $post_id, $precio, $moneda, $plazas, $certificado, $secuencial, $prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table convoca-courses-metabox">
	<tr>
		<th scope="row"><label for="convoca_precio"><?php esc_html_e( 'Precio', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" min="0" step="0.01" id="convoca_precio" name="convoca_precio"
				value="<?php echo esc_attr( (string) (float) $precio ); ?>" class="small-text" />
			<input type="text" id="convoca_moneda" name="convoca_moneda" maxlength="3"
				value="<?php echo esc_attr( (string) $moneda ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Deja 0 para un curso gratuito. Moneda en tres letras (EUR, USD).', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_plazas"><?php esc_html_e( 'Plazas', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" min="0" step="1" id="convoca_plazas" name="convoca_plazas"
				value="<?php echo esc_attr( (string) (int) $plazas ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( '0 significa sin límite. Con plazas limitadas, dos personas no pueden coger la última a la vez.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Certificado', 'convoca-courses' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="convoca_certificado" value="1" <?php checked( (bool) $certificado ); ?> />
				<?php esc_html_e( 'Emitir certificado al completar el 100 % del curso', 'convoca-courses' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Desbloqueo', 'convoca-courses' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="convoca_secuencial" value="1" <?php checked( (bool) $secuencial ); ?> />
				<?php esc_html_e( 'Desbloqueo secuencial: cada lección se abre al completar la anterior', 'convoca-courses' ); ?>
			</label>
		</td>
	</tr>
</table>
