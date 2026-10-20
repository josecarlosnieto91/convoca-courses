<?php
/**
 * Ajustes del módulo: vídeo (PRO) y conservación de datos.
 *
 * @package Convoca\Courses
 */

namespace Convoca\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla y registro de ajustes.
 */
class Settings {

	/**
	 * Grupo de ajustes.
	 */
	public const GROUP = 'convoca_courses_settings';

	/**
	 * Página de ajustes.
	 */
	public const PAGE = 'convoca-courses-settings';

	/**
	 * Registra la pantalla y los ajustes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Añade la pantalla de ajustes bajo el menú de cursos.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . CPT_Curso::TYPE,
			__( 'Ajustes de Convoca Courses', 'convoca-courses' ),
			__( 'Ajustes', 'convoca-courses' ),
			'convoca_manage_courses',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Registra las opciones con su saneado.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			self::GROUP,
			Video::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_video' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::GROUP,
			Installer::KEEP_DATA_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
				'default'           => false,
			)
		);
	}

	/**
	 * Saneado de la configuración de vídeo.
	 *
	 * @param mixed $input Valores enviados.
	 * @return array<string, string|bool>
	 */
	public static function sanitize_video( $input ): array {
		$current = Video::config();
		$input   = is_array( $input ) ? $input : array();

		$secret = isset( $input['secret'] ) ? trim( (string) $input['secret'] ) : '';
		if ( '' === $secret ) {
			// El campo se deja vacío para no reenviar la clave: se conserva la guardada.
			$secret = (string) $current['secret'];
		}

		return array(
			'endpoint'    => isset( $input['endpoint'] ) ? esc_url_raw( (string) $input['endpoint'] ) : '',
			'bucket'      => isset( $input['bucket'] ) ? sanitize_text_field( (string) $input['bucket'] ) : '',
			'region'      => isset( $input['region'] ) && '' !== (string) $input['region'] ? sanitize_text_field( (string) $input['region'] ) : 'auto',
			'key'         => isset( $input['key'] ) ? sanitize_text_field( (string) $input['key'] ) : '',
			'secret'      => Video::encrypt( $secret ),
			'path_prefix' => isset( $input['path_prefix'] ) ? sanitize_text_field( (string) $input['path_prefix'] ) : '',
			'path_style'  => ! empty( $input['path_style'] ),
		);
	}

	/**
	 * Pinta la pantalla de ajustes.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! Access::can_manage() ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'convoca-courses' ) );
		}

		$config = Video::config();
		$pro    = License::has_pro();

		Certificate::render_template(
			'ajustes.php',
			array(
				'group'      => self::GROUP,
				'page'       => self::PAGE,
				'config'     => $config,
				'pro'        => $pro,
				'keep_data'  => (bool) get_option( Installer::KEEP_DATA_OPTION, false ),
				'video_option' => Video::OPTION,
				'keep_option'  => Installer::KEEP_DATA_OPTION,
			)
		);
	}
}
