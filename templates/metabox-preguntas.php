<?php
/**
 * Plantilla: banco de preguntas del examen.
 *
 * Variables: ver Admin_Examen::render_questions_box().
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_editando  = null !== $editar;
$convoca_tipo      = $convoca_editando ? (string) $editar['tipo'] : 'unica';
$convoca_opciones  = $convoca_editando ? (array) $editar['opciones'] : array( '', '', '', '' );
$convoca_correctas = $convoca_editando ? (array) $editar['correctas'] : array();
?>
<?php if ( '' !== $aviso ) : ?>
	<div class="notice notice-info inline"><p><?php echo esc_html( $aviso ); ?></p></div>
<?php endif; ?>

<?php if ( empty( $preguntas ) ) : ?>
	<p><?php esc_html_e( 'Este examen todavía no tiene preguntas.', 'convoca-courses' ); ?></p>
<?php else : ?>
	<ol class="convoca-courses-questions">
		<?php foreach ( $preguntas as $convoca_pregunta ) : ?>
			<li>
				<strong><?php echo esc_html( (string) $convoca_pregunta['enunciado'] ); ?></strong>
				<em>(<?php echo esc_html( (string) $convoca_pregunta['tipo'] ); ?>, <?php echo esc_html( number_format_i18n( (float) $convoca_pregunta['puntos'], 2 ) ); ?> <?php esc_html_e( 'puntos', 'convoca-courses' ); ?>)</em>
				<ul>
					<?php foreach ( $convoca_pregunta['opciones'] as $convoca_indice => $convoca_opcion ) : ?>
						<li>
							<?php echo in_array( (string) $convoca_indice, (array) $convoca_pregunta['correctas'], true ) ? '✔' : '·'; ?>
							<?php echo esc_html( (string) $convoca_opcion ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( add_query_arg( 'convoca_pregunta', (int) $convoca_pregunta['id'], (string) get_edit_post_link( (int) $post_id, 'raw' ) ) ); ?>">
						<?php esc_html_e( 'Editar', 'convoca-courses' ); ?>
					</a>
					|
					<a
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'action'         => $action_delete,
									'question_id'    => (int) $convoca_pregunta['id'],
									'exam_id'        => (int) $post_id,
									'_convoca_nonce' => (string) $nonce_delete,
								),
								admin_url( 'admin-post.php' )
							)
						);
						?>
								"
						onclick="return confirm('<?php echo esc_js( __( '¿Borrar esta pregunta?', 'convoca-courses' ) ); ?>');">
						<?php esc_html_e( 'Borrar', 'convoca-courses' ); ?>
					</a>
				</p>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<hr />

<h4><?php echo $convoca_editando ? esc_html__( 'Editar pregunta', 'convoca-courses' ) : esc_html__( 'Añadir pregunta', 'convoca-courses' ); ?></h4>

<form method="post" action="<?php echo esc_url( (string) $action_url ); ?>" class="convoca-courses-question-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( (string) $action ); ?>" />
	<input type="hidden" name="exam_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
	<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $nonce ); ?>" />
	<input type="hidden" name="question_id" value="<?php echo esc_attr( (string) ( $convoca_editando ? $editar['id'] : 0 ) ); ?>" />

	<p>
		<label for="convoca_tipo"><strong><?php esc_html_e( 'Tipo', 'convoca-courses' ); ?></strong></label>
		<select name="convoca_tipo" id="convoca_tipo">
			<?php foreach ( $tipos as $convoca_tipo_opcion ) : ?>
				<option value="<?php echo esc_attr( (string) $convoca_tipo_opcion ); ?>" <?php selected( $convoca_tipo, (string) $convoca_tipo_opcion ); ?>>
					<?php echo esc_html( (string) $convoca_tipo_opcion ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p>
		<label for="convoca_enunciado"><strong><?php esc_html_e( 'Enunciado', 'convoca-courses' ); ?></strong></label><br />
		<textarea name="convoca_enunciado" id="convoca_enunciado" rows="3" class="large-text"><?php echo esc_textarea( $convoca_editando ? (string) $editar['enunciado'] : '' ); ?></textarea>
	</p>

	<p><strong><?php esc_html_e( 'Opciones', 'convoca-courses' ); ?></strong></p>
	<?php for ( $convoca_i = 0; $convoca_i < 4; $convoca_i++ ) : ?>
		<p>
			<input
				type="checkbox"
				name="convoca_correcta[]"
				value="<?php echo esc_attr( (string) $convoca_i ); ?>"
				<?php checked( true, in_array( (string) $convoca_i, array_map( 'strval', $convoca_correctas ), true ) ); ?> />
			<input
				type="text"
				name="convoca_opcion[]"
				value="<?php echo esc_attr( isset( $convoca_opciones[ $convoca_i ] ) ? (string) $convoca_opciones[ $convoca_i ] : '' ); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr( sprintf( /* translators: %d: option number. */ __( 'Opción %d', 'convoca-courses' ), $convoca_i + 1 ) ); ?>" />
		</p>
	<?php endfor; ?>
	<p class="description">
		<?php esc_html_e( 'Marca la casilla de la respuesta correcta. En las de respuesta múltiple, marca todas: una de más anula la pregunta. En verdadero/falso, las opciones son siempre Verdadero y Falso.', 'convoca-courses' ); ?>
	</p>

	<p>
		<label for="convoca_puntos"><strong><?php esc_html_e( 'Puntos', 'convoca-courses' ); ?></strong></label>
		<input type="number" min="0.01" step="0.01" id="convoca_puntos" name="convoca_puntos" value="<?php echo esc_attr( (string) ( $convoca_editando ? $editar['puntos'] : 1 ) ); ?>" />
		<label for="convoca_orden"><strong><?php esc_html_e( 'Orden', 'convoca-courses' ); ?></strong></label>
		<input type="number" min="0" step="1" id="convoca_orden" name="convoca_orden" value="<?php echo esc_attr( (string) ( $convoca_editando ? $editar['orden'] : 0 ) ); ?>" />
	</p>

	<p>
		<label for="convoca_explicacion"><strong><?php esc_html_e( 'Explicación (se enseña al corregir)', 'convoca-courses' ); ?></strong></label><br />
		<textarea name="convoca_explicacion" id="convoca_explicacion" rows="2" class="large-text"><?php echo esc_textarea( $convoca_editando ? (string) $editar['explicacion'] : '' ); ?></textarea>
	</p>

	<p>
		<button type="submit" class="button button-primary">
			<?php echo $convoca_editando ? esc_html__( 'Guardar pregunta', 'convoca-courses' ) : esc_html__( 'Añadir pregunta', 'convoca-courses' ); ?>
		</button>
	</p>
</form>
