<?php
/**
 * Plantilla: pantalla de ajustes.
 *
 * @package Convoca\Courses
 *
 * @var array<string, mixed> $group, $page, $config, $pro, $keep_data, $video_option, $keep_option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap convoca-courses-admin">
	<h1><?php esc_html_e( 'Ajustes de Convoca Courses', 'convoca-courses' ); ?></h1>

	<?php if ( ! $pro ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Las lecciones de vídeo son una función PRO. Sin licencia, los cursos funcionan con texto, adjuntos y PDF.', 'convoca-courses' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( (string) $group ); ?>

		<h2><?php esc_html_e( 'Vídeo (PRO)', 'convoca-courses' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'El vídeo no se aloja en este plugin: se firma una URL de corta duración contra un almacenamiento compatible con S3 (R2, B2, Wasabi, MinIO).', 'convoca-courses' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="convoca-endpoint"><?php esc_html_e( 'Endpoint', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="convoca-endpoint"
						name="<?php echo esc_attr( (string) $video_option ); ?>[endpoint]"
						value="<?php echo esc_attr( (string) $config['endpoint'] ); ?>"
						placeholder="https://s3.example.org" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-bucket"><?php esc_html_e( 'Bucket', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="convoca-bucket"
						name="<?php echo esc_attr( (string) $video_option ); ?>[bucket]"
						value="<?php echo esc_attr( (string) $config['bucket'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-region"><?php esc_html_e( 'Región', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="convoca-region"
						name="<?php echo esc_attr( (string) $video_option ); ?>[region]"
						value="<?php echo esc_attr( (string) $config['region'] ); ?>" placeholder="auto" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-key"><?php esc_html_e( 'Clave de acceso', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="convoca-key" autocomplete="off"
						name="<?php echo esc_attr( (string) $video_option ); ?>[key]"
						value="<?php echo esc_attr( (string) $config['key'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-secret"><?php esc_html_e( 'Secreto', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="convoca-secret" autocomplete="new-password"
						name="<?php echo esc_attr( (string) $video_option ); ?>[secret]" value="" />
					<p class="description"><?php esc_html_e( 'Se guarda cifrado. Déjalo vacío para conservar el actual.', 'convoca-courses' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-prefix"><?php esc_html_e( 'Prefijo de objetos', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="convoca-prefix"
						name="<?php echo esc_attr( (string) $video_option ); ?>[path_prefix]"
						value="<?php echo esc_attr( (string) $config['path_prefix'] ); ?>" placeholder="cursos/2026" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Estilo de ruta', 'convoca-courses' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( (string) $video_option ); ?>[path_style]" value="1" <?php checked( (bool) $config['path_style'] ); ?> />
						<?php esc_html_e( 'Usar estilo de ruta (bucket en la ruta). Desmarca para estilo virtual-host.', 'convoca-courses' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Datos de la entidad', 'convoca-courses' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Los usan las condiciones de venta y el recibo. Cada organización rellena los suyos; no vienen escritos en el código.', 'convoca-courses' ); ?>
		</p>
		<?php if ( ! empty( $entidad_faltan ) ) : ?>
			<div class="notice notice-warning inline" style="margin:8px 0 16px;">
				<p>
					<?php
					printf(
						/* translators: %s: lista de campos que faltan. */
						esc_html__( 'Faltan datos para publicar las condiciones de venta: %s.', 'convoca-courses' ),
						esc_html( implode( ', ', $entidad_faltan ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<?php foreach ( $entidad_campos as $convoca_campo => $convoca_etiqueta ) : ?>
				<tr>
					<th scope="row">
						<label for="convoca-entidad-<?php echo esc_attr( $convoca_campo ); ?>"><?php echo esc_html( $convoca_etiqueta ); ?></label>
					</th>
					<td>
						<input
							type="<?php echo 'email_desistimiento' === $convoca_campo ? 'email' : 'text'; ?>"
							class="regular-text"
							id="convoca-entidad-<?php echo esc_attr( $convoca_campo ); ?>"
							name="<?php echo esc_attr( (string) $entidad_option ); ?>[<?php echo esc_attr( $convoca_campo ); ?>]"
							value="<?php echo esc_attr( (string) ( $entidad[ $convoca_campo ] ?? '' ) ); ?>"
						/>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h3><?php esc_html_e( 'Texto de las condiciones', 'convoca-courses' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Déjalo vacío para usar el texto de fábrica. Si escribes el tuyo, sustituye al de fábrica: cada instalación pone el que le corresponda. Puedes usar los marcadores {{nombre}}, {{nif}}, {{email_desistimiento}}, {{direccion}} y {{telefono}}.', 'convoca-courses' ); ?>
		</p>
		<textarea
			name="<?php echo esc_attr( (string) $condiciones_option ); ?>"
			id="convoca-condiciones-texto"
			rows="14"
			class="large-text code"
			placeholder="<?php echo esc_attr( (string) $condiciones_plantilla ); ?>"
		><?php echo esc_textarea( (string) $condiciones_texto ); ?></textarea>

		<h3><?php esc_html_e( 'Casilla de consentimiento', 'convoca-courses' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Es la casilla que hay que marcar para matricularse. Sin ella no se puede invocar la excepción del artículo 103.m: los 14 días de desistimiento se respetan completos.', 'convoca-courses' ); ?>
		</p>
		<textarea
			name="<?php echo esc_attr( (string) $consentimiento_option ); ?>"
			id="convoca-consentimiento-texto"
			rows="3"
			class="large-text"
			placeholder="<?php echo esc_attr( (string) $consentimiento_defecto ); ?>"
		><?php echo esc_textarea( (string) $consentimiento_texto ); ?></textarea>

		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( (string) $renuncia_option ); ?>" value="1" <?php checked( (bool) $renuncia_activa ); ?> />
				<?php esc_html_e( 'Ofrecer también la renuncia expresa al desistimiento (solo si la entidad lo tiene autorizado).', 'convoca-courses' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'La casilla es opcional y solo alcanza al contenido digital: quien no la marque conserva los 14 días completos. Al guardar la matrícula se archiva el texto exacto que se mostró, con la fecha, la IP y el navegador, para poder demostrar el consentimiento.', 'convoca-courses' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Solo se ofrece en los cursos marcados como «100 % digital»: si el curso tiene sesiones en directo, tutorías o material físico, esa parte queda fuera de la excepción y la casilla no aparece.', 'convoca-courses' ); ?>
		</p>
		<textarea
			name="<?php echo esc_attr( (string) $renuncia_texto_option ); ?>"
			id="convoca-renuncia-texto"
			rows="4"
			class="large-text"
			placeholder="<?php echo esc_attr( (string) $renuncia_defecto ); ?>"
		><?php echo esc_textarea( (string) $renuncia_texto ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Texto de la casilla de renuncia. Debe decir con claridad que se pierde el derecho al empezar a consumir el contenido, que la casilla es opcional y que el alcance es solo el contenido digital. Si se deja vacío, se usa la redacción de fábrica.', 'convoca-courses' ); ?>
		</p>
		<?php if ( '' !== (string) $renuncia_texto ) : ?>
			<div class="notice notice-warning inline" style="margin:8px 0;">
				<p>
					<?php
					printf(
						/* translators: 1: versión de fábrica, 2: fecha del cambio. */
						esc_html__( 'Estás usando un texto propio, no el de fábrica (versión %1$s). Desde el cambio, la responsabilidad del redactado es de la entidad. Fecha del último cambio: %2$s.', 'convoca-courses' ),
						esc_html( (string) $renuncia_version ),
						esc_html( '' !== (string) $renuncia_fecha_cambio ? (string) $renuncia_fecha_cambio : __( 'sin registrar', 'convoca-courses' ) )
					);
					?>
				</p>
			</div>
		<?php else : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: versión del texto de fábrica. */
					esc_html__( 'El texto de fábrica está revisado contra el TRLGDCU (versión %s). Si lo cambias, la revisión deja de aplicar y el redactado pasa a ser responsabilidad de la entidad.', 'convoca-courses' ),
					esc_html( (string) $renuncia_version )
				);
				?>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Certificado', 'convoca-courses' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Cada organización firma con su propia identidad. Sin logo configurado se usa el del tema; sin firma, el nombre del sitio.', 'convoca-courses' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="convoca-cert-logo"><?php esc_html_e( 'Logo', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="convoca-cert-logo" name="<?php echo esc_attr( (string) $cert_logo_option ); ?>" value="<?php echo esc_attr( (string) $cert_logo ); ?>" class="small-text" />
					<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Ver biblioteca de medios', 'convoca-courses' ); ?></a>
					<p class="description">
						<?php esc_html_e( 'Identificador del adjunto (PNG o JPEG). Déjalo en 0 para usar el logo del tema. Se incrusta en el PDF: tiene que ser un fichero de este sitio.', 'convoca-courses' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-cert-firma"><?php esc_html_e( 'Firma', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" id="convoca-cert-firma" name="<?php echo esc_attr( (string) $cert_firma_option ); ?>" value="<?php echo esc_attr( (string) $cert_firma ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Nombre de quien expide el certificado. Vacío: el nombre del sitio.', 'convoca-courses' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="convoca-cert-cargo"><?php esc_html_e( 'Cargo', 'convoca-courses' ); ?></label></th>
				<td>
					<input type="text" id="convoca-cert-cargo" name="<?php echo esc_attr( (string) $cert_firma_cargo_option ); ?>" value="<?php echo esc_attr( (string) $cert_firma_cargo ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Aparece bajo la firma. Opcional.', 'convoca-courses' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Datos', 'convoca-courses' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ficha del curso', 'convoca-courses' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( (string) $plantilla_option ); ?>" value="1" <?php checked( (bool) $plantilla_propia ); ?> />
						<?php esc_html_e( 'Usar la plantilla del plugin para la ficha del curso.', 'convoca-courses' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Desmarcado (por defecto), la ficha la pinta el tema. Márcalo si el tema la muestra como una entrada cualquiera, sin matrícula ni programa. Un tema puede sustituirla con convoca-curso.php.', 'convoca-courses' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Al desinstalar', 'convoca-courses' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( (string) $keep_option ); ?>" value="1" <?php checked( (bool) $keep_data ); ?> />
						<?php esc_html_e( 'Conservar cursos, matrículas y progreso al desinstalar el plugin.', 'convoca-courses' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
