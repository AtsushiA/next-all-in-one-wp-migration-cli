<?php
/**
 * WP-CLI backup management commands for All-in-One WP Migration.
 *
 * Registers: wp ai1wm-cli backup           — create a backup
 *            wp ai1wm-cli backup list      — list backup files
 *            wp ai1wm-cli backup delete    — delete a backup file
 *
 * Note: WP-CLI does not support both __invoke and named subcommands on the
 * same class, so routing is handled manually inside __invoke.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

class Ai1wm_CLI_Backup_Command extends WP_CLI_Command {

	/**
	 * Creates a backup, or manages existing backups (list / delete).
	 *
	 * ## USAGE
	 *
	 *   wp ai1wm-cli backup [list|delete] [<args>...] [--options]
	 *
	 * ## SUBCOMMANDS
	 *
	 *   list    List all backup files in the backups directory.
	 *   delete  Delete a backup file from the backups directory.
	 *
	 * ## OPTIONS (backup creation)
	 *
	 * [<subcommand>]
	 * : Optional subcommand: list or delete.
	 *
	 * [--output=<path>]
	 * : Destination path for the .wpress file. Defaults to the plugin's backups directory.
	 *
	 * [--exclude-media]
	 * : Exclude media files (uploads) from the backup.
	 *
	 * [--exclude-themes]
	 * : Exclude theme files from the backup.
	 *
	 * [--exclude-plugins]
	 * : Exclude plugin files from the backup.
	 *
	 * [--exclude-db]
	 * : Exclude the database from the backup.
	 *
	 * ## EXAMPLES
	 *
	 *   # Create a full backup
	 *   $ wp ai1wm-cli backup
	 *
	 *   # Create a backup, save to a custom path
	 *   $ wp ai1wm-cli backup --output=/var/backups/site.wpress
	 *
	 *   # Create a backup excluding media
	 *   $ wp ai1wm-cli backup --exclude-media --exclude-db
	 *
	 *   # List backup files
	 *   $ wp ai1wm-cli backup list
	 *   $ wp ai1wm-cli backup list --format=json
	 *
	 *   # Delete a backup file
	 *   $ wp ai1wm-cli backup delete mysite-20260301-120000-abc123.wpress --yes
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : null;

		if ( 'list' === $subcommand ) {
			$this->cmd_list( array_slice( $args, 1 ), $assoc_args );
			return;
		}

		if ( 'delete' === $subcommand ) {
			$this->cmd_delete( array_slice( $args, 1 ), $assoc_args );
			return;
		}

		if ( null !== $subcommand ) {
			WP_CLI::error( sprintf(
				"'%s' is not a subcommand of 'ai1wm-cli backup'. Available subcommands: list, delete.",
				$subcommand
			) );
		}

		$this->create_backup( $assoc_args );
	}

	// -------------------------------------------------------------------------
	// Private handlers
	// -------------------------------------------------------------------------

	/**
	 * Create a backup archive.
	 */
	private function create_backup( $assoc_args ) {
		$output = \WP_CLI\Utils\get_flag_value( $assoc_args, 'output', null );

		$options = array(
			'no-media'    => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-media', false ),
			'no-themes'   => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-themes', false ),
			'no-plugins'  => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-plugins', false ),
			'no-database' => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-db', false ),
		);

		$params = array(
			'priority'   => 5,
			'secret_key' => get_option( 'ai1wm_secret_key' ),
			'options'    => $options,
		);

		WP_CLI::log( 'Starting backup...' );

		$result = Ai1wm_Export_Controller::export( $params );

		$backup_path = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $result['archive'];

		if ( ! file_exists( $backup_path ) ) {
			WP_CLI::error( 'Backup completed but file was not found at: ' . $backup_path );
		}

		if ( $output ) {
			$dest = path_is_absolute( $output ) ? $output : getcwd() . DIRECTORY_SEPARATOR . $output;
			if ( ! rename( $backup_path, $dest ) ) {
				WP_CLI::error( 'Backup succeeded but could not move the file to: ' . $dest );
			}
			$backup_path = $dest;
		}

		WP_CLI::success( 'Backup complete. File saved to: ' . $backup_path );
	}

	/**
	 * List all backup files.
	 */
	private function cmd_list( $args, $assoc_args ) {
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$backups = Ai1wm_Backups::get_files();

		if ( empty( $backups ) ) {
			WP_CLI::log( 'No backup files found in: ' . AI1WM_BACKUPS_PATH );
			return;
		}

		$rows = array();
		foreach ( $backups as $backup ) {
			$size = $backup['size'] !== null
				? ai1wm_size_format( $backup['size'], 1 )
				: 'N/A';

			$rows[] = array(
				'filename' => $backup['filename'],
				'created'  => $backup['mtime'] !== null
					? date( 'Y-m-d H:i:s', $backup['mtime'] )
					: 'N/A',
				'size'     => $size,
			);
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'filename', 'created', 'size' ) );
	}

	/**
	 * Delete a backup file.
	 */
	private function cmd_delete( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a filename. Usage: wp ai1wm-cli backup delete <filename>' );
		}

		$filename = basename( $args[0] );

		if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'wpress' ) {
			WP_CLI::error( 'Invalid file type. Only .wpress files can be deleted with this command.' );
		}

		$backup_path = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $filename;
		if ( ! file_exists( $backup_path ) ) {
			WP_CLI::error( 'Backup file not found: ' . $filename );
		}

		$skip_confirm = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		if ( ! $skip_confirm ) {
			WP_CLI::confirm( sprintf( 'Are you sure you want to delete "%s"?', $filename ) );
		}

		if ( Ai1wm_Backups::delete_file( $filename ) ) {
			WP_CLI::success( 'Deleted: ' . $filename );
		} else {
			WP_CLI::error( 'Could not delete: ' . $filename );
		}
	}
}
