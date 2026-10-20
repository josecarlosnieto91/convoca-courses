<?php
/**
 * Plantilla: metabox del módulo.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $post_id, $curso_id, $orden, $cursos, $prefix, $aviso.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table convoca-courses-metabox">
	<tr>
		<th scope="row"><label for="convoca_curso_id"><?php esc_html_e( 'Curso', 'convoca-courses' ); ?></label></th>
		<td>
			<select id="convoca_curso_id" name="convoca_curso_id">
				<?php foreach ( $cursos as $convoca_courses_id => $convoca_courses_title ) : ?>
					<option value="<?php echo esc_attr( (string) (int) $convoca_courses_id ); ?>" <?php selected( (int) $curso_id, (int) $convoca_courses_id ); ?>>
						<?php echo esc_html( (string) $convoca_courses_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $aviso ) ) : ?>
				<p class="description convoca-courses-admin__warning"><?php echo esc_html( implode( ' ', (array) $aviso ) ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="convoca_orden"><?php esc_html_e( 'Orden', 'convoca-courses' ); ?></label></th>
		<td>
			<input type="number" step="1" id="convoca_orden" name="convoca_orden"
				value="<?php echo esc_attr( (string) (int) $orden ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Número dentro del curso. Los módulos se muestran de menor a mayor.', 'convoca-courses' ); ?></p>
		</td>
	</tr>
</table>
