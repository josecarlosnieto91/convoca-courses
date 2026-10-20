<?php
/**
 * Bootstrap de PHPStan: constantes de runtime y del propio plugin.
 *
 * @package Convoca\Courses
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

define( 'CONVOCA_COURSES_VERSION', '0.1.0' );
define( 'CONVOCA_COURSES_DB_VERSION', '1.0.0' );
define( 'CONVOCA_COURSES_FILE', __DIR__ . '/convoca-courses.php' );
define( 'CONVOCA_COURSES_DIR', __DIR__ . '/' );
define( 'CONVOCA_COURSES_URL', 'https://example.org/wp-content/plugins/convoca-courses/' );
