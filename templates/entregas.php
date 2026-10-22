<?php
/**
 * Plantilla: pantalla de entregas del panel.
 *
 * Variables: ver Admin_Entregas::render().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Entregas del alumnado', 'convoca-courses' ); ?></h1>

	<?php if ( '' !== (string) $notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo esc_html( (string) $notice ); ?></p></div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( CPT_Curso::TYPE ); ?>" />
		<input type="hidden" name="page" value="<?php echo esc_attr( (string) $page ); ?>" />
		<label for="convoca-course-filter"><?php esc_html_e( 'Curso', 'convoca-courses' ); ?></label>
		<select name="course_id" id="convoca-course-filter">
			<option value="0"><?php esc_html_e( '— Selecciona un curso —', 'convoca-courses' ); ?></option>
			<?php foreach ( $cursos as $convoca_curso ) : ?>
				<option value="<?php echo esc_attr( (string) $convoca_curso->ID ); ?>" <?php selected( (int) $course_id, (int) $convoca_curso->ID ); ?>>
					<?php echo esc_html( (string) $convoca_curso->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Ver entregas', 'convoca-courses' ), 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( $course_id > 0 && empty( $entregas ) ) : ?>
		<p><?php esc_html_e( 'Este curso no tiene entregas todavía.', 'convoca-courses' ); ?></p>
	<?php elseif ( $course_id > 0 ) : ?>
		<h2><?php echo esc_html( (string) $course_title ); ?></h2>

		<table class="widefat striped convoca-courses-entregas">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Alumno', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Tarea', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Entregada', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Estado', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Nota y comentario', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Corregir', 'convoca-courses' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entregas as $convoca_entrega ) : ?>
					<?php
					$convoca_usuario = get_userdata( (int) $convoca_entrega['user_id'] );
					$convoca_nombre  = $convoca_usuario ? (string) $convoca_usuario->display_name : '#' . (int) $convoca_entrega['user_id'];
					$convoca_adjunto = (int) $convoca_entrega['adjunto_id'] > 0 ? (string) wp_get_attachment_url( (int) $convoca_entrega['adjunto_id'] ) : '';
					?>
					<tr>
						<td><?php echo esc_html( $convoca_nombre ); ?></td>
						<td><?php echo esc_html( (string) get_the_title( (int) $convoca_entrega['task_id'] ) ); ?></td>
						<td><?php echo esc_html( (string) $convoca_entrega['entregada_en'] ); ?></td>
						<td>
							<?php echo esc_html( (string) $convoca_entrega['estado'] ); ?>
							<?php if ( '' !== $convoca_adjunto ) : ?>
								<br /><a href="<?php echo esc_url( $convoca_adjunto ); ?>"><?php esc_html_e( 'fichero', 'convoca-courses' ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== (string) $convoca_entrega['contenido'] ) : ?>
								<details>
									<summary><?php esc_html_e( 'ver texto', 'convoca-courses' ); ?></summary>
									<p><?php echo esc_html( (string) $convoca_entrega['contenido'] ); ?></p>
								</details>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( '' !== (string) $convoca_entrega['corregida_en'] && '0000-00-00 00:00:00' !== $convoca_entrega['corregida_en'] ) : ?>
								<?php echo esc_html( number_format_i18n( (float) $convoca_entrega['nota'], 2 ) ); ?>
								<?php if ( '' !== (string) $convoca_entrega['comentario'] ) : ?>
									<br /><small><?php echo esc_html( (string) $convoca_entrega['comentario'] ); ?></small>
								<?php endif; ?>
							<?php else : ?>
								<?php esc_html_e( 'sin corregir', 'convoca-courses' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( (string) $action_url ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( (string) $action ); ?>" />
								<input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $convoca_entrega['id'] ); ?>" />
								<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>" />
								<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( Handlers::task_grade_nonce( (int) $convoca_entrega['id'] ) ); ?>" />
								<p>
									<label for="convoca_nota_<?php echo esc_attr( (string) $convoca_entrega['id'] ); ?>"><?php esc_html_e( 'Nota', 'convoca-courses' ); ?></label>
									<input
										type="number"
										min="0"
										max="100"
										step="0.01"
										id="convoca_nota_<?php echo esc_attr( (string) $convoca_entrega['id'] ); ?>"
										name="convoca_nota"
										value="<?php echo esc_attr( (string) $convoca_entrega['nota'] ); ?>" />
								</p>
								<p>
									<textarea name="convoca_comentario" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Comentario para el alumno', 'convoca-courses' ); ?>"><?php echo esc_textarea( (string) $convoca_entrega['comentario'] ); ?></textarea>
								</p>
								<p>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar corrección', 'convoca-courses' ); ?></button>
								</p>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $group && $alumnos > 0 ) : ?>
			<h2><?php esc_html_e( 'Escribir a todo el alumnado', 'convoca-courses' ); ?></h2>
			<form method="post" action="<?php echo esc_url( (string) $action_url ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( (string) $group_action ); ?>" />
				<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>" />
				<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $group_nonce ); ?>" />
				<p>
					<label for="convoca_asunto"><?php esc_html_e( 'Asunto', 'convoca-courses' ); ?></label>
					<input type="text" id="convoca_asunto" name="convoca_asunto" class="regular-text" />
				</p>
				<p>
					<textarea name="convoca_mensaje" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Mensaje para el alumnado', 'convoca-courses' ); ?>"></textarea>
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of students. */
						esc_html__( 'Se manda a %d personas, una a una, por la cola de correo del ecosistema.', 'convoca-courses' ),
						(int) $alumnos
					);
					?>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enviar mensaje', 'convoca-courses' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<?php if ( $bulk ) : ?>
			<p class="description">
				<?php esc_html_e( 'Corrección en lote disponible con licencia PRO: cada entrega se guarda por separado, así que no se pisa la corrección de otro tutor.', 'convoca-courses' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
