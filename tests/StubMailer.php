<?php
/**
 * Doble de `Convoca\Core\Mailer` para las pruebas de avisos del curso.
 *
 * `Notifications::send()` exige que exista el Mailer del núcleo y apunta como pendiente lo que no
 * sale. El doble deja decidir si el envío funciona y apunta lo enviado.
 *
 * @package Convoca\Courses\Tests
 */

namespace Convoca\Core;

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- Mailer y Logger son dos dobles del núcleo y van juntos: se cargan o no se cargan a la vez.
if ( ! class_exists( 'Convoca\\Core\\Mailer' ) ) {
	/**
	 * Envío de correo del núcleo.
	 *
	 * Doble que apunta lo enviado y deja decidir si el envío funciona.
	 */
	class Mailer {
		/**
		 * Correos enviados.
		 *
		 * @var array<int, array>
		 */
		public static $enviados = array();

		/**
		 * Envía un correo.
		 *
		 * @param string $para     Destinatario.
		 * @param string $asunto   Asunto.
		 * @param string $cuerpo   Cuerpo.
		 * @param array  $contexto Contexto.
		 * @return bool
		 */
		public static function send( $para, $asunto, $cuerpo, $contexto = array() ) {
			self::$enviados[] = array( $para, $asunto, $cuerpo, $contexto );
			return (bool) ( $GLOBALS['convoca_courses_mailer_ok'] ?? true );
		}
	}
}

if ( ! class_exists( 'Convoca\\Core\\Logger' ) ) {
	/**
	 * Registro de eventos del núcleo.
	 *
	 * Doble que apunta lo escrito, para poder comprobar los avisos.
	 */
	class Logger {
		/**
		 * Eventos escritos.
		 *
		 * @var array<int, array>
		 */
		public static $escritos = array();

		/**
		 * Registra un aviso.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @param int    $objeto   Objeto afectado.
		 * @return void
		 */
		public static function warning( $mensaje, $contexto = '', $objeto = 0 ) {
			self::$escritos[] = array( $mensaje, $contexto, (int) $objeto );
		}

		/**
		 * Registra un evento informativo.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @param int    $objeto   Objeto afectado.
		 * @return void
		 */
		public static function info( $mensaje, $contexto = '', $objeto = 0 ) {
			self::$escritos[] = array( $mensaje, $contexto, (int) $objeto );
		}

		/**
		 * Registra un error.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @param int    $objeto   Objeto afectado.
		 * @return void
		 */
		public static function error( $mensaje, $contexto = '', $objeto = 0 ) {
			self::$escritos[] = array( $mensaje, $contexto, (int) $objeto );
		}
	}
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile
