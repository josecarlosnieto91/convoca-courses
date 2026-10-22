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
			<p class="description"><?php esc_html_e( 'Los cursos creados antes de la 0.3.0 que no tengan esta casilla marcada siguen abiertos: cambiarlo es decisión tuya.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Matrícula', 'convoca-courses' ); ?></th>
		<td>
			<label for="convoca_apertura_matricula"><?php esc_html_e( 'Abre', 'convoca-courses' ); ?></label>
			<input type="date" id="convoca_apertura_matricula" name="convoca_apertura_matricula"
				value="<?php echo esc_attr( (string) $apertura ); ?>" />
			<label for="convoca_cierre_matricula"><?php esc_html_e( 'Cierra', 'convoca-courses' ); ?></label>
			<input type="date" id="convoca_cierre_matricula" name="convoca_cierre_matricula"
				value="<?php echo esc_attr( (string) $cierre ); ?>" />
			<p class="description"><?php esc_html_e( 'En blanco, la matrícula está siempre abierta.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Fechas y caducidad', 'convoca-courses' ); ?></th>
		<td>
			<label for="convoca_fecha_fin"><?php esc_html_e( 'Fin del curso', 'convoca-courses' ); ?></label>
			<input type="date" id="convoca_fecha_fin" name="convoca_fecha_fin"
				value="<?php echo esc_attr( (string) $fecha_fin ); ?>" />
			<label for="convoca_caducidad_dias"><?php esc_html_e( 'Días de acceso', 'convoca-courses' ); ?></label>
			<input type="number" min="0" step="1" id="convoca_caducidad_dias" name="convoca_caducidad_dias"
				value="<?php echo esc_attr( (string) (int) $caducidad ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Días contados desde la matrícula; 0 = sin caducidad. Al vencer, el curso queda en solo lectura: se ven el progreso, las notas y el certificado, pero no se avanza.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_nota_minima"><?php esc_html_e( 'Nota mínima', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" min="0" max="100" step="0.01" id="convoca_nota_minima" name="convoca_nota_minima"
				value="<?php echo esc_attr( (string) (float) $nota_minima ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Sobre 100. Por debajo de esta nota no se aprueba el curso y no hay certificado.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_acceso_examen"><?php esc_html_e( 'Acceso al examen', 'convoca-courses' ); ?></label></th>
		<td>
			<select id="convoca_acceso_examen" name="convoca_acceso_examen">
				<option value="todas" <?php selected( 'todas', (string) $acceso ); ?>><?php esc_html_e( 'Al completar todas las lecciones', 'convoca-courses' ); ?></option>
				<option value="modulo" <?php selected( 'modulo', (string) $acceso ); ?>><?php esc_html_e( 'Al completar las lecciones de su módulo', 'convoca-courses' ); ?></option>
				<option value="porcentaje" <?php selected( 'porcentaje', (string) $acceso ); ?>><?php esc_html_e( 'Al alcanzar un porcentaje del curso', 'convoca-courses' ); ?></option>
			</select>
			<input type="number" min="0" max="100" step="1" id="convoca_acceso_examen_porcentaje"
				name="convoca_acceso_examen_porcentaje" value="<?php echo esc_attr( (string) (int) $acceso_pct ); ?>"
				class="small-text" />
			<p class="description"><?php esc_html_e( 'El porcentaje solo se usa en la tercera opción. La comprobación se hace en el servidor.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
</table>
