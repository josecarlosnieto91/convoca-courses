<?php
/**
 * Andamiaje del documento para las vistas propias de Courses.
 *
 * En un tema FSE no existen `header.php` ni `footer.php`: llamar a `get_header()` no pinta nada y
 * además emite una deprecación. Las lecciones y la ficha del curso comparten este documento para no
 * tener dos versiones del mismo andamiaje (courses#49).
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documento completo para un tema de bloques.
 */
final class Documento_FSE {

	/**
	 * ¿El tema activo es FSE?
	 *
	 * @return bool
	 */
	public static function es_fse(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Pinta el documento completo con las partes de plantilla del tema.
	 *
	 * @param int      $post_id      Entrada cuyo título va al documento.
	 * @param callable $contenido    Callback que pinta el cuerpo.
	 * @param string[] $clases_extra Clases de body propias de la vista.
	 * @return void
	 */
	public static function render( int $post_id, callable $contenido, array $clases_extra = array() ): void {
		add_filter(
			'pre_get_document_title',
			static function () use ( $post_id ) {
				return (string) get_the_title( $post_id );
			}
		);

		add_filter(
			'body_class',
			static function ( $clases ) use ( $clases_extra ) {
				$clases = array_diff( (array) $clases, array( 'home', 'blog', 'archive', 'error404', 'search' ) );

				foreach ( $clases_extra as $clase ) {
					$clases[] = $clase;
				}

				return array_values( array_unique( $clases ) );
			}
		);

		// El tema no emite `<title>` en el head; se pinta aquí y se quita el de WordPress para no
		// duplicarlo. Es el mismo contrato que usa la lección desde courses#19.
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php
		echo '<title>' . esc_html( (string) get_the_title( $post_id ) ) . '</title>' . "\n";
		wp_head();
		?>
</head>
<body <?php body_class(); ?>>
		<?php
		wp_body_open();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lo genera WordPress.
		echo do_blocks( '<!-- wp:template-part {"slug":"header","theme":"' . esc_attr( (string) get_stylesheet() ) . '"} /-->' );

		$contenido();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lo genera WordPress.
		echo do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"' . esc_attr( (string) get_stylesheet() ) . '"} /-->' );
		wp_footer();
		?>
</body>
</html>
		<?php
	}
}
