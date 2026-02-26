<?php
/**
 * WPKit Test Bootstrap.
 *
 * Uses Brain\Monkey for mocking WordPress functions.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants used in source code.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * 24 * 3600 );
}
