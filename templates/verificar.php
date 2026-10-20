<?php
/**
 * Plantilla: verificación pública de un certificado.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $code, $data, $valid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$convoca_courses_blog = (string) get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,follow" />
	<title>
		<?php
		printf(
			/* translators: %s: site name. */
			esc_html__( 'Verificación de certificado — %s', 'convoca-courses' ),
			esc_html( $convoca_courses_blog )
		);
		?>
	</title>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- página autónoma sin tema ni wp_head: no hay cola de estilos donde encolarla. ?>
	<link rel="stylesheet" href="<?php echo esc_url( CONVOCA_COURSES_URL . 'assets/css/courses.css?ver=' . CONVOCA_COURSES_VERSION ); ?>" />
</head>
<body class="convoca-courses-verify">
	<main class="convoca-courses convoca-courses--verify">
		<h1 class="convoca-courses__title"><?php esc_html_e( 'Verificación de certificado', 'convoca-courses' ); ?></h1>

		<form class="convoca-courses__form" method="get" action="<?php echo esc_url( home_url( '/verificar-curso/' ) ); ?>">
			<label for="convoca-code"><?php esc_html_e( 'Código del certificado', 'convoca-courses' ); ?></label>
			<input type="text" id="convoca-code" name="codigo" value="<?php echo esc_attr( (string) $code ); ?>" required />
			<button type="submit" class="convoca-courses__button"><?php esc_html_e( 'Verificar', 'convoca-courses' ); ?></button>
		</form>

		<?php if ( '' === (string) $code ) : ?>
			<p><?php esc_html_e( 'Introduce el código que aparece en el certificado para comprobar que es auténtico.', 'convoca-courses' ); ?></p>
		<?php elseif ( $valid ) : ?>
			<div class="convoca-courses-notice convoca-courses-notice--ok" role="status">
				<p><strong><?php esc_html_e( 'Certificado válido.', 'convoca-courses' ); ?></strong></p>
				<ul class="convoca-courses__facts">
					<li><span class="convoca-courses__label"><?php esc_html_e( 'Código', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $data['code'] ); ?></li>
					<li><span class="convoca-courses__label"><?php esc_html_e( 'Curso', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $data['course'] ); ?></li>
					<li><span class="convoca-courses__label"><?php esc_html_e( 'Persona', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $data['person'] ); ?></li>
					<li><span class="convoca-courses__label"><?php esc_html_e( 'Fecha', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $data['date'] ); ?></li>
					<li><span class="convoca-courses__label"><?php esc_html_e( 'Entidad', 'convoca-courses' ); ?></span> <?php echo esc_html( (string) $data['center'] ); ?></li>
				</ul>
				<p>
					<a class="convoca-courses__button" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'codigo'  => (string) $data['code'],
								'formato' => 'pdf',
							),
							home_url( '/verificar-curso/' )
						)
					);
					?>
																" target="_blank" rel="noopener">
						<?php esc_html_e( 'Ver el certificado en PDF', 'convoca-courses' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
			<div class="convoca-courses-notice convoca-courses-notice--error" role="alert">
				<?php esc_html_e( 'No existe ningún certificado con ese código.', 'convoca-courses' ); ?>
			</div>
		<?php endif; ?>
	</main>
</body>
</html>
