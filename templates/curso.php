<?php
/**
 * Plantilla: portada del curso.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $course_id, $title, $content, $price, $seats, $enrolled, $progress, $certificate, $modules, $logged_in, $enroll_url, $enroll_nonce, $needs_payment, $enrollment_status, $payment_url, $receipt_url.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables inyectadas por \Convoca\Courses\Certificate::render_template().
$convoca_courses_message = '';
// Aviso de solo lectura: no cambia estado, así que no lleva nonce.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['convoca_msg'] ) ) {
	$convoca_courses_message = \Convoca\Courses\Handlers::message( sanitize_key( wp_unslash( (string) $_GET['convoca_msg'] ) ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="convoca-courses convoca-courses--course">

	<?php if ( '' !== $convoca_courses_message ) : ?>
		<div class="convoca-courses-notice"><?php echo esc_html( $convoca_courses_message ); ?></div>
	<?php endif; ?>

	<header class="convoca-courses__header">
		<h2 class="convoca-courses__title"><?php echo esc_html( (string) $title ); ?></h2>

		<ul class="convoca-courses__facts">
			<?php if ( '' !== (string) $price ) : ?>
				<li><span class="convoca-courses__label"><?php esc_html_e( 'Precio', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $price ); ?></li>
			<?php endif; ?>
			<li>
				<span class="convoca-courses__label"><?php esc_html_e( 'Plazas', 'convoca-courses' ); ?></span>
				<?php
				if ( (int) $seats < 0 ) {
					esc_html_e( 'sin límite', 'convoca-courses' );
				} else {
					echo esc_html( (string) (int) $seats );
				}
				?>
			</li>
			<?php if ( $certificate ) : ?>
				<li><span class="convoca-courses__label"><?php esc_html_e( 'Certificado', 'convoca-courses' ); ?></span> <?php esc_html_e( 'sí', 'convoca-courses' ); ?></li>
			<?php endif; ?>
		</ul>
	</header>

	<div class="convoca-courses__content">
		<?php echo wp_kses_post( (string) $content ); ?>
	</div>

	<?php if ( ! $enrolled && 'pendiente' === (string) $enrollment_status ) : ?>
		<div class="convoca-courses-notice convoca-courses-notice--locked">
			<p><?php esc_html_e( 'Tu matrícula está pendiente de pago.', 'convoca-courses' ); ?></p>
			<?php if ( '' !== (string) $payment_url ) : ?>
				<p>
					<a class="convoca-courses__button" href="<?php echo esc_url( (string) $payment_url ); ?>">
						<?php esc_html_e( 'Completar el pago', 'convoca-courses' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php elseif ( $enrolled ) : ?>
		<div class="convoca-courses__progress" role="status">
			<div class="convoca-courses__bar">
				<span style="width:<?php echo esc_attr( (string) (float) $progress['percent'] ); ?>%"></span>
			</div>
			<p>
				<?php
				printf(
					/* translators: 1: completed lessons, 2: total lessons, 3: percentage. */
					esc_html__( '%1$d de %2$d lecciones completadas (%3$s %%).', 'convoca-courses' ),
					(int) $progress['done'],
					(int) $progress['total'],
					esc_html( (string) $progress['percent'] )
				);
				?>
			</p>
			<?php if ( '' !== (string) $receipt_url ) : ?>
				<p>
					<a href="<?php echo esc_url( (string) $receipt_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Ver el recibo del pago', 'convoca-courses' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php elseif ( $logged_in ) : ?>
		<form class="convoca-courses__form" method="post" action="<?php echo esc_url( (string) $enroll_url ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( \Convoca\Courses\Handlers::ACTION_ENROLL ); ?>" />
			<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) (int) $course_id ); ?>" />
			<input type="hidden" name="_convoca_nonce" value="<?php echo esc_attr( (string) $enroll_nonce ); ?>" />
			<button type="submit" class="convoca-courses__button">
				<?php
				if ( $needs_payment && '' !== (string) $price ) {
					printf(
						/* translators: %s: course price. */
						esc_html__( 'Comprar plaza — %s', 'convoca-courses' ),
						esc_html( (string) $price )
					);
				} else {
					esc_html_e( 'Matricularme', 'convoca-courses' );
				}
				?>
			</button>
		</form>
	<?php else : ?>
		<?php
		// Sin cuenta también se puede uno matricular: el alumnado no tiene por qué tener usuario de
		// WordPress. El formulario pide correo y nombre y, si el curso es de pago, lleva al pago. El
		// enlace de siempre se queda como salida para quien ya tenga cuenta.
		$convoca_courses_matricula = \Convoca\Courses\Matricula_Identidad::render( array( 'course' => (int) $course_id ) );
		?>
		<?php if ( '' !== $convoca_courses_matricula ) : ?>
			<?php echo $convoca_courses_matricula; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML propio, ya escapado en su plantilla. ?>
			<p class="convoca-courses__login">
				<a href="<?php echo esc_url( wp_login_url( (string) get_permalink( (int) $course_id ) ) ); ?>">
					<?php esc_html_e( 'Si ya tienes cuenta en la web, entra por aquí.', 'convoca-courses' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="convoca-courses__login">
				<a class="convoca-courses__button" href="<?php echo esc_url( wp_login_url( (string) get_permalink( (int) $course_id ) ) ); ?>">
					<?php esc_html_e( 'Inicia sesión para matricularte', 'convoca-courses' ); ?>
				</a>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $modules ) ) : ?>
		<h3 class="convoca-courses__subtitle"><?php esc_html_e( 'Contenido del curso', 'convoca-courses' ); ?></h3>
		<ol class="convoca-courses__modules">
			<?php foreach ( $modules as $convoca_courses_module ) : ?>
				<li class="convoca-courses__module">
					<h4><?php echo esc_html( (string) $convoca_courses_module['title'] ); ?></h4>
					<?php if ( ! empty( $convoca_courses_module['lessons'] ) ) : ?>
						<ul class="convoca-courses__lessons">
							<?php foreach ( $convoca_courses_module['lessons'] as $convoca_courses_lesson ) : ?>
								<li class="convoca-courses__lesson<?php echo $convoca_courses_lesson['completed'] ? ' is-completed' : ''; ?><?php echo $convoca_courses_lesson['locked'] ? ' is-locked' : ''; ?>">
									<span class="convoca-courses__lesson-number"><?php echo esc_html( (string) $convoca_courses_lesson['number'] ); ?></span>
									<span class="convoca-courses__lesson-title"><?php echo esc_html( (string) $convoca_courses_lesson['title'] ); ?></span>
									<span class="convoca-courses__lesson-type"><?php echo esc_html( (string) $convoca_courses_lesson['type'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
