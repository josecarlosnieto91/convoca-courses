<?php
/**
 * Plantilla: metabox de la lección.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $post_id, $modulo_id, $tipo, $orden, $duracion, $adjunto_id, $video_url, $video_key, $tipos, $modulos, $prefix, $pro, $aviso.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table convoca-courses-metabox">
	<tr>
		<th scope="row"><label for="convoca_modulo_id"><?php esc_html_e( 'Módulo', 'convoca-courses' ); ?></label></th>
		<td>
			<select id="convoca_modulo_id" name="convoca_modulo_id">
				<option value="0"><?php esc_html_e( '— Selecciona un módulo —', 'convoca-courses' ); ?></option>
				<?php foreach ( $modulos as $convoca_courses_group ) : ?>
					<optgroup label="<?php echo esc_attr( (string) $convoca_courses_group['curso'] ); ?>">
						<?php foreach ( $convoca_courses_group['modulos'] as $convoca_courses_mid => $convoca_courses_mtitle ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $convoca_courses_mid ); ?>" <?php selected( (int) $modulo_id, (int) $convoca_courses_mid ); ?>>
								<?php echo esc_html( (string) $convoca_courses_mtitle ); ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $aviso ) ) : ?>
				<p class="description convoca-courses-admin__warning"><?php echo esc_html( implode( ' ', (array) $aviso ) ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_tipo"><?php esc_html_e( 'Tipo de lección', 'convoca-courses' ); ?></label></th>
		<td>
			<select id="convoca_tipo" name="convoca_tipo">
				<?php foreach ( $tipos as $convoca_courses_type ) : ?>
					<option value="<?php echo esc_attr( (string) $convoca_courses_type ); ?>" <?php selected( (string) $tipo, (string) $convoca_courses_type ); ?>>
						<?php echo esc_html( (string) $convoca_courses_type ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! $pro ) : ?>
				<p class="description"><?php esc_html_e( 'El tipo «video» necesita licencia PRO: sin ella la lección se muestra sin vídeo.', 'convoca-courses' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_orden"><?php esc_html_e( 'Orden', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" step="1" id="convoca_orden" name="convoca_orden"
				value="<?php echo esc_attr( (string) (int) $orden ); ?>" class="small-text" />
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_duracion"><?php esc_html_e( 'Duración (min)', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" min="0" step="1" id="convoca_duracion" name="convoca_duracion"
				value="<?php echo esc_attr( (string) (int) $duracion ); ?>" class="small-text" />
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_adjunto_id"><?php esc_html_e( 'Adjunto o PDF', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" min="0" step="1" id="convoca_adjunto_id" name="convoca_adjunto_id"
				value="<?php echo esc_attr( (string) (int) $adjunto_id ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Identificador del fichero en la biblioteca de medios.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_video_url"><?php esc_html_e( 'URL externa de vídeo', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="url" id="convoca_video_url" name="convoca_video_url" class="regular-text"
				value="<?php echo esc_attr( (string) $video_url ); ?>" placeholder="https://video.example.org/leccion.mp4" />
			<p class="description"><?php esc_html_e( 'Alternativa al almacenamiento propio: URL directa de vídeo.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_video_key"><?php esc_html_e( 'Objeto en el almacenamiento', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="text" id="convoca_video_key" name="convoca_video_key" class="regular-text"
				value="<?php echo esc_attr( (string) $video_key ); ?>" placeholder="curso-1/leccion-1.mp4" />
			<p class="description"><?php esc_html_e( 'Ruta del objeto dentro del bucket. Se sirve con URL firmada de corta duración.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
</table>
