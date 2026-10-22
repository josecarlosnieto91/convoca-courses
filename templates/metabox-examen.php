<?php
/**
 * Plantilla: caja de configuración del examen.
 *
 * Variables: ver \Convoca\Courses\Admin_Examen::render_config_box().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table convoca-courses-exam-config">
	<tbody>
		<tr>
			<th scope="row"><label for="convoca_curso_id"><?php esc_html_e( 'Curso', 'convoca-courses' ); ?></label></th>
			<td>
				<select name="convoca_curso_id" id="convoca_curso_id">
					<?php foreach ( $cursos as $convoca_id => $convoca_titulo ) : ?>
						<option value="<?php echo esc_attr( (string) $convoca_id ); ?>" <?php selected( (int) $curso_id, (int) $convoca_id ); ?>>
							<?php echo esc_html( (string) $convoca_titulo ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'El examen se abre desde el curso al que pertenece.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_modulo_id"><?php esc_html_e( 'Módulo', 'convoca-courses' ); ?></label></th>
			<td>
				<select name="convoca_modulo_id" id="convoca_modulo_id">
					<?php foreach ( $modulos as $convoca_id => $convoca_titulo ) : ?>
						<option value="<?php echo esc_attr( (string) $convoca_id ); ?>" <?php selected( (int) $modulo_id, (int) $convoca_id ); ?>>
							<?php echo esc_html( (string) $convoca_titulo ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Si el examen es del módulo, el acceso se mide sobre las lecciones de ese módulo.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_num_preguntas"><?php esc_html_e( 'Preguntas por intento', 'convoca-courses' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" id="convoca_num_preguntas" name="convoca_num_preguntas" value="<?php echo esc_attr( (string) $num_preguntas ); ?>" />
				<p class="description"><?php esc_html_e( '0 = todas las preguntas del examen y del banco del curso.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Aleatorio', 'convoca-courses' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="convoca_aleatorio" value="1" <?php checked( true, (bool) $aleatorio ); ?> />
					<?php esc_html_e( 'Barajar y elegir preguntas al azar en cada intento', 'convoca-courses' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_intentos"><?php esc_html_e( 'Intentos', 'convoca-courses' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" id="convoca_intentos" name="convoca_intentos" value="<?php echo esc_attr( (string) $intentos ); ?>" />
				<p class="description"><?php esc_html_e( '0 = intentos ilimitados.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_nota_minima"><?php esc_html_e( 'Nota mínima', 'convoca-courses' ); ?></label></th>
			<td>
				<input type="number" min="0" max="100" step="0.01" id="convoca_nota_minima" name="convoca_nota_minima" value="<?php echo esc_attr( (string) $nota_minima ); ?>" />
				<p class="description"><?php esc_html_e( '0 = la que tenga el curso.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_tiempo_maximo"><?php esc_html_e( 'Tiempo máximo (minutos)', 'convoca-courses' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" id="convoca_tiempo_maximo" name="convoca_tiempo_maximo" value="<?php echo esc_attr( (string) $tiempo ); ?>" />
				<p class="description"><?php esc_html_e( '0 = sin límite. El tiempo se comprueba en el servidor al enviar.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_retroalimentacion"><?php esc_html_e( 'Corrección visible', 'convoca-courses' ); ?></label></th>
			<td>
				<select name="convoca_retroalimentacion" id="convoca_retroalimentacion">
					<?php foreach ( $retros as $convoca_modo ) : ?>
						<option value="<?php echo esc_attr( (string) $convoca_modo ); ?>" <?php selected( (string) $retro, (string) $convoca_modo ); ?>>
							<?php
							switch ( $convoca_modo ) {
								case 'tras_enviar':
									esc_html_e( 'Al enviar: nota y detalle', 'convoca-courses' );
									break;
								case 'al_cerrar':
									esc_html_e( 'Al cerrar el examen: solo la nota', 'convoca-courses' );
									break;
								default:
									esc_html_e( 'Nunca: solo la nota, sin detalle', 'convoca-courses' );
									break;
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="convoca_peso"><?php esc_html_e( 'Peso en la nota final', 'convoca-courses' ); ?></label></th>
			<td>
				<input type="number" min="0" max="100" step="0.01" id="convoca_peso" name="convoca_peso" value="<?php echo esc_attr( (string) $peso ); ?>" />
				<p class="description"><?php esc_html_e( 'Los pesos de los exámenes de un curso deben sumar 100.', 'convoca-courses' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
