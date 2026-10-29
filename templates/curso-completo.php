<?php
/**
 * Plantilla propia de la ficha del curso.
 *
 * El cuerpo lo pinta el shortcode `[convoca_curso]`: así la matrícula, el programa y el botón de empezar se
 * comportan igual que en cualquier otra página y no hay dos versiones de la misma lógica.
 *
 * Un tema puede sustituirla creando `convoca-curso.php` o `single-convoca_curso.php`.
 *
 * @package Convoca\Courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="convoca-curso" class="convoca-curso convoca-curso--propia">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article class="convoca-curso__articulo">
			<header class="convoca-curso__cabecera">
				<h1 class="convoca-curso__titulo"><?php the_title(); ?></h1>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="convoca-curso__portada">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>
			</header>

			<?php echo do_shortcode( '[convoca_curso id="' . (int) get_the_ID() . '"]' ); ?>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
