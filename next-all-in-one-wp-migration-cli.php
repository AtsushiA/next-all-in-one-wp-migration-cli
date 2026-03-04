<?php
/**
 * Plugin Name: Next All-in-One WP Migration CLI
 * Description: WP-CLI commands for All-in-One WP Migration plugin. Provides export, import, and backup management without the Unlimited Extension.
 * Version: 1.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Author:            NExT-Season
 * Author URI:        https://next-season.net/
 * Requires Plugins: all-in-one-wp-migration
 * License: GPLv3
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'AI1WM_PLUGIN_NAME' ) ) {
			WP_CLI::error( 'All-in-One WP Migration plugin is not active. Please activate it before using this plugin.' );
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'lib/command/class-ai1wm-cli-command.php';
		require_once plugin_dir_path( __FILE__ ) . 'lib/command/class-ai1wm-cli-backup-command.php';

		WP_CLI::add_command(
			'ai1wm-cli',
			'Ai1wm_CLI_Command',
			array( 'shortdesc' => 'All-in-One WP Migration CLI — export and import without the Unlimited Extension.' )
		);

		WP_CLI::add_command(
			'ai1wm-cli backup',
			'Ai1wm_CLI_Backup_Command',
			array( 'shortdesc' => 'Manage All-in-One WP Migration backup files.' )
		);
	},
	20
);
