<?php
/**
 * Plugin Name: Marshall WP-CLI
 * Description: Custom WP-CLI commands for Marshall University.
 * Author: Christopher McComas
 * Version: 1.0
 *
 * @package Marshall_WPCLI
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/mu-wpcli-commands/commands/class-get-urls-csv-command.php';
	require_once __DIR__ . '/mu-wpcli-commands/commands/class-keyword-scanner-command.php';
}
