<?php
/**
 * Plantilla: examen del curso.
 *
 * Variables: ver \Convoca\Courses\Exam_View::data().
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $exam_id, $course_id, $title, $intro, $enrolled, $manage, $logged_in, $can_take, $expired, $min_score, $time_limit, $feedback, $attempts_used, $attempts_left, $attempts_max, $open, $questions, $attempts, $best, $passed, $open_url, $open_nonce, $course_url, $notice, $result.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_estado = 'abierto';

if ( ! $logged_in ) {
	$convoca_estado = 'sin_sesion';
} elseif ( ! $enrolled && ! $manage ) {
	$convoca_estado = 'sin_matricula';
} elseif ( $expired ) {
	$convoca_estado = 'caducado';
} elseif ( null !== $open ) {
	$convoca_estado = 'en_curso';
} elseif ( ! $can_take ) {
	$convoca_estado = 'bloqueado';
} elseif ( 0 === $attempts_left ) {
	$convoca_estado = 'agotado';
}
?>
<div class="convoca-courses convoca-courses-exam" id="convoca-examen-<?php echo esc_attr( (string) $exam_id ); ?>">
	<h3 class="convoca-courses-exam__title"><?php echo esc_html( (string) $title ); ?></h3>

	<?php if ( '' !== $notice ) : ?>
		<div class="convoca-courses-notice"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>

	<?php if ( '' !== (string) $intro ) : ?>
		<div class="convoca-courses-exam__intro"><?php echo wp_kses_post( (string) $intro ); ?></div>
	<?php endif; ?>

	<p class="convoca-courses-exam__rules">
		<?php
		printf(
			/* translators: %s: minimum score, 0-100. */
			esc_html__( 'Nota mínima para aprobar: %s sobre 100.', 'convoca-courses' ),
			esc_html( rtrim( rtrim( number_format_i18n( (float) $min_score, 1 ), '0' ), ',' ) )
		);
		?>
		<?php if ( $time_limit > 0 ) : ?>
			<?php
			printf(
				/* translators: %d: minutes. */
				esc_html__( ' Tiempo máximo: %d minutos; al pasarse, el intento se cierra sin corregir.', 'convoca-courses' ),
				(int) $time_limit
			);
			?>
		<?php endif; ?>
		<?php if ( $attempts_max > 0 ) : ?>
			<?php
			printf(
				/* translators: 1: attempts used, 2: attempts allowed. */
				esc_html__( ' Intentos: %1$d de %2$d usados.', 'convoca-courses' ),
				(int) $attempts_used,
				(int) $attempts_max
			);
			?>
		<?php endif; ?>
	</p>

	<?php if ( 'sin_sesion' === $convoca_estado ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Inicia sesión para hacer el examen.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( 'sin_matricula' === $convoca_estado ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Necesitas estar matriculado en el curso para hacer el examen.', 'convoca-courses' ); ?>
			<?php if ( '' !== (string) $course_url ) : ?>
				<a href="<?php echo esc_url( (string) $course_url ); ?>"><?php esc_html_e( 'Ver el curso', 'convoca-courses' ); ?></a>
			<?php endif; ?>
		</p>
	<?php elseif ( 'caducado' === $convoca_estado ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Tu acceso a este curso ha caducado: puedes consultar tus notas, pero ya no abrir exámenes.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( 'bloqueado' === $convoca_estado ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'El examen se abre cuando completes las lecciones que pide el curso.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( 'agotado' === $convoca_estado ) : ?>
		<p class="convoca-courses-notice convoca-courses-notice--warning">
			<?php esc_html_e( 'Has usado todos los intentos de este examen.', 'convoca-courses' ); ?>
		</p>
	<?php elseif ( 'en_curso' === $convoca_estado ) : ?>
		<form method="post" action="<?php echo esc_url( (string) $open_url ); ?>" class="convoca-courses-exam__form">
			<input type="hidden" name="action" value="<?php echo esc_attr( \Convoca\Courses\Handlers::ACTION_EXAM_SUBMIT ); ?>" />
			<input type="hidden" name="attempt_id" value="<?php echo esc_attr( (string) $open['id'] ); ?>" />
			<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( \Convoca\Courses\Handlers::exam_submit_nonce( (int) $open['id'] ) ); ?>" />

			<?php $convoca_numero = 0; ?>
			<?php foreach ( $questions as $convoca_pregunta ) : ?>
				<?php
				++$convoca_numero;
				$convoca_multiple = 'multiple' === (string) $convoca_pregunta['tipo'];
				$convoca_campo    = 'convoca_respuesta[' . (int) $convoca_pregunta['id'] . ']' . ( $convoca_multiple ? '[]' : '' );
				?>
				<fieldset class="convoca-courses-exam__question">
					<legend>
						<?php echo esc_html( sprintf( '%d. %s', $convoca_numero, (string) $convoca_pregunta['enunciado'] ) ); ?>
						<?php if ( $convoca_pregunta['puntos'] > 1 ) : ?>
							<small>
								<?php
								printf(
									/* translators: %s: points. */
									esc_html__( '(%s puntos)', 'convoca-courses' ),
									esc_html( number_format_i18n( (float) $convoca_pregunta['puntos'], 2 ) )
								);
								?>
							</small>
						<?php endif; ?>
					</legend>

					<?php foreach ( $convoca_pregunta['opciones'] as $convoca_indice => $convoca_opcion ) : ?>
						<label class="convoca-courses-exam__option">
							<input
								type="<?php echo esc_attr( $convoca_multiple ? 'checkbox' : 'radio' ); ?>"
								name="<?php echo esc_attr( $convoca_campo ); ?>"
								value="<?php echo esc_attr( (string) $convoca_indice ); ?>" />
							<?php echo esc_html( (string) $convoca_opcion ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<p class="convoca-courses-exam__actions">
				<button type="submit" class="convoca-button">
					<?php esc_html_e( 'Enviar examen', 'convoca-courses' ); ?>
				</button>
			</p>
		</form>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( (string) $open_url ); ?>" class="convoca-courses-exam__form">
			<input type="hidden" name="action" value="<?php echo esc_attr( \Convoca\Courses\Handlers::ACTION_EXAM_OPEN ); ?>" />
			<input type="hidden" name="exam_id" value="<?php echo esc_attr( (string) $exam_id ); ?>" />
			<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $open_nonce ); ?>" />
			<p class="convoca-courses-exam__actions">
				<button type="submit" class="convoca-button">
					<?php esc_html_e( 'Abrir examen', 'convoca-courses' ); ?>
				</button>
			</p>
		</form>
	<?php endif; ?>

	<?php if ( null !== $result ) : ?>
		<?php
		$convoca_mostrar_detalle = 'tras_enviar' === $feedback;
		$convoca_resultado       = (string) $result['estado'];
		?>
		<div class="convoca-courses-exam__result convoca-courses-exam__result--<?php echo esc_attr( $convoca_resultado ); ?>">
			<h4><?php esc_html_e( 'Resultado del intento', 'convoca-courses' ); ?></h4>
			<?php if ( 'expirado' === $convoca_resultado ) : ?>
				<p><?php esc_html_e( 'El intento se ha cerrado por tiempo: no se ha corregido.', 'convoca-courses' ); ?></p>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %s: score, 0-100. */
						esc_html__( 'Nota: %s', 'convoca-courses' ),
						esc_html( number_format_i18n( (float) $result['puntuacion'], 2 ) )
					);
					?>
					— <?php echo $result['aprobado'] ? esc_html__( 'aprobado', 'convoca-courses' ) : esc_html__( 'no aprobado', 'convoca-courses' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $convoca_mostrar_detalle && ! empty( $result['detalle'] ) ) : ?>
				<ol class="convoca-courses-exam__detail">
					<?php foreach ( $result['detalle'] as $convoca_linea ) : ?>
						<li class="<?php echo $convoca_linea['correcta'] ? 'is-correct' : 'is-wrong'; ?>">
							<?php echo esc_html( (string) ( $convoca_linea['enunciado'] ?? '' ) ); ?>
							— <?php echo $convoca_linea['correcta'] ? esc_html__( 'correcta', 'convoca-courses' ) : esc_html__( 'incorrecta', 'convoca-courses' ); ?>
							<?php if ( '' !== (string) ( $convoca_linea['explicacion'] ?? '' ) ) : ?>
								<p class="convoca-courses-exam__explanation"><?php echo esc_html( (string) $convoca_linea['explicacion'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $attempts ) ) : ?>
		<table class="convoca-courses-table convoca-courses-exam__attempts">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Intento', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Fecha', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Nota', 'convoca-courses' ); ?></th>
					<th><?php esc_html_e( 'Estado', 'convoca-courses' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $attempts as $convoca_intento ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $convoca_intento['intento'] ); ?></td>
						<td><?php echo esc_html( (string) $convoca_intento['terminado_en'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $convoca_intento['puntuacion'], 2 ) ); ?></td>
						<td>
							<?php
							if ( 'expirado' === $convoca_intento['estado'] ) {
								esc_html_e( 'cerrado por tiempo', 'convoca-courses' );
							} else {
								echo $convoca_intento['aprobado'] ? esc_html__( 'aprobado', 'convoca-courses' ) : esc_html__( 'no aprobado', 'convoca-courses' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
