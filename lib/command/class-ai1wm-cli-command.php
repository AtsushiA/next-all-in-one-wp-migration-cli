<?php
/**
 * WP-CLI commands for All-in-One WP Migration.
 *
 * Registers: wp ai1wm-cli export
 *            wp ai1wm-cli import <file>
 *            wp ai1wm-cli restore <filename>
 *            wp ai1wm-cli url-restore <url>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

class Ai1wm_CLI_Command extends WP_CLI_Command {

	/**
	 * Creates a backup (.wpress file) of the current site.
	 *
	 * ## OPTIONS
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
	 *   # Full backup saved to the default backups directory
	 *   $ wp ai1wm-cli export
	 *
	 *   # Save backup to a custom path
	 *   $ wp ai1wm-cli export --output=/var/backups/site.wpress
	 *
	 *   # Exclude media and database
	 *   $ wp ai1wm-cli export --exclude-media --exclude-db
	 *
	 * @subcommand export
	 */
	public function export( $args, $assoc_args ) {
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

		WP_CLI::log( 'Starting export...' );

		$result = Ai1wm_Export_Controller::export( $params );

		$backup_path = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $result['archive'];

		if ( ! file_exists( $backup_path ) ) {
			WP_CLI::error( 'Export completed but backup file was not found at: ' . $backup_path );
		}

		// Move to custom output path if specified
		if ( $output ) {
			$output = $this->resolve_path( $output );
			if ( ! rename( $backup_path, $output ) ) {
				WP_CLI::error( 'Export succeeded but could not move the file to: ' . $output );
			}
			$backup_path = $output;
		}

		WP_CLI::success( 'Export complete. File saved to: ' . $backup_path );
	}

	/**
	 * Restores a site from a .wpress file at an arbitrary path.
	 *
	 * Use `wp ai1wm-cli restore` for files already in the backups directory.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the .wpress backup file to import.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *   # Import with confirmation prompt
	 *   $ wp ai1wm-cli import /var/backups/site.wpress
	 *
	 *   # Import without confirmation (for scripts)
	 *   $ wp ai1wm-cli import /var/backups/site.wpress --yes
	 *
	 * @subcommand import
	 */
	public function import( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a path to a .wpress file. Usage: wp ai1wm-cli import <file>' );
		}

		$file = $this->resolve_path( $args[0] );

		// Validate file exists
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File not found: ' . $file );
		}

		// Validate extension
		if ( pathinfo( $file, PATHINFO_EXTENSION ) !== 'wpress' ) {
			WP_CLI::error( 'Invalid file type. Only .wpress files are supported.' );
		}

		// Confirm before overwriting
		$skip_confirm = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		if ( ! $skip_confirm ) {
			WP_CLI::confirm( 'This will overwrite the current site. Are you sure?' );
		}

		// Copy the .wpress file into the storage directory so the import pipeline can find it
		$storage  = uniqid();
		$archive  = basename( $file );
		$dest_dir = AI1WM_STORAGE_PATH . DIRECTORY_SEPARATOR . $storage;

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			WP_CLI::error( 'Could not create temporary storage directory: ' . $dest_dir );
		}

		$dest_path = $dest_dir . DIRECTORY_SEPARATOR . $archive;
		if ( ! copy( $file, $dest_path ) ) {
			WP_CLI::error( 'Could not copy the backup file to the storage directory.' );
		}

		WP_CLI::log( 'Starting import of: ' . $archive );

		// Start from priority 10 (skip Import_Upload which requires $_FILES).
		// cli_args is forwarded to Import_Confirm so --yes skips its WP_CLI::confirm() prompt.
		$params = array(
			'priority'   => 10,
			'secret_key' => get_option( 'ai1wm_secret_key' ),
			'archive'    => $archive,
			'storage'    => $storage,
			'cli_args'   => array( 'yes' => $skip_confirm ),
		);

		Ai1wm_Import_Controller::import( $params );

		WP_CLI::success( 'Import complete. Site restored successfully.' );
	}

	/**
	 * Restores a site from a backup file stored in the backups directory.
	 *
	 * Use `wp ai1wm-cli backup list` to see available filenames.
	 * Unlike `import`, only the filename is needed — no file copy is performed.
	 *
	 * ## OPTIONS
	 *
	 * <filename>
	 * : Filename of the backup (e.g. mysite-20260301-120000-abc123.wpress).
	 *   Only the filename is required — the backups directory is resolved automatically.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *   # List available backups, then restore one
	 *   $ wp ai1wm-cli backup list
	 *   $ wp ai1wm-cli restore mysite-20260301-120000-abc123.wpress
	 *
	 *   # Restore without confirmation (for scripts)
	 *   $ wp ai1wm-cli restore mysite-20260301-120000-abc123.wpress --yes
	 *
	 * @subcommand restore
	 */
	public function restore( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a backup filename. Usage: wp ai1wm-cli restore <filename>' );
		}

		$filename = basename( $args[0] );

		if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'wpress' ) {
			WP_CLI::error( 'Invalid file type. Only .wpress files are supported.' );
		}

		$backup_path = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $filename;
		if ( ! file_exists( $backup_path ) ) {
			WP_CLI::error( sprintf(
				'Backup file not found: %s' . PHP_EOL . 'Run `wp ai1wm-cli backup list` to see available backups.',
				$filename
			) );
		}

		$skip_confirm = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		if ( ! $skip_confirm ) {
			WP_CLI::confirm( sprintf( 'Restore site from "%s"? This will overwrite the current site.', $filename ) );
		}

		// Create a temporary storage folder for the extraction working directory.
		// With ai1wm_manual_restore set, ai1wm_archive_path() reads the file directly
		// from AI1WM_BACKUPS_PATH — no file copy is needed.
		$storage  = uniqid();
		$dest_dir = AI1WM_STORAGE_PATH . DIRECTORY_SEPARATOR . $storage;
		if ( ! wp_mkdir_p( $dest_dir ) ) {
			WP_CLI::error( 'Could not create temporary storage directory: ' . $dest_dir );
		}

		WP_CLI::log( 'Starting restore of: ' . $filename );

		// Start from priority 10 (skip Import_Upload which requires $_FILES).
		// ai1wm_manual_restore tells the pipeline to read the archive from
		// AI1WM_BACKUPS_PATH instead of the storage folder.
		// cli_args is forwarded to Import_Confirm so --yes skips its WP_CLI::confirm() prompt.
		$params = array(
			'priority'             => 10,
			'secret_key'          => get_option( 'ai1wm_secret_key' ),
			'archive'             => $filename,
			'storage'             => $storage,
			'ai1wm_manual_restore' => true,
			'cli_args'            => array( 'yes' => $skip_confirm ),
		);

		Ai1wm_Import_Controller::import( $params );

		WP_CLI::success( 'Restore complete. Site restored from: ' . $filename );
	}

	/**
	 * Downloads a .wpress file from a URL and restores the site from it.
	 *
	 * The file is streamed directly to disk — suitable for large archives.
	 * After downloading, the standard import pipeline is executed.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : HTTP/HTTPS URL of the .wpress backup file to download and restore.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--timeout=<seconds>]
	 * : HTTP download timeout in seconds. Default: 300.
	 *
	 * ## EXAMPLES
	 *
	 *   # Download and restore (with confirmation prompt)
	 *   $ wp ai1wm-cli url-restore https://example.com/backups/site.wpress
	 *
	 *   # Download and restore without confirmation
	 *   $ wp ai1wm-cli url-restore https://example.com/backups/site.wpress --yes
	 *
	 * @subcommand url-restore
	 */
	public function url_restore( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a URL. Usage: wp ai1wm-cli url-restore <url>' );
		}

		$url = $args[0];

		// Validate URL scheme
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			WP_CLI::error( 'Invalid URL. Only HTTP and HTTPS URLs are supported.' );
		}

		// Validate .wpress extension in URL path
		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		if ( pathinfo( $url_path, PATHINFO_EXTENSION ) !== 'wpress' ) {
			WP_CLI::error( 'Invalid file type. The URL must point to a .wpress file.' );
		}

		$archive     = basename( $url_path );
		$timeout     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'timeout', 300 );
		$skip_confirm = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		// Confirm before overwriting
		if ( ! $skip_confirm ) {
			WP_CLI::confirm( 'This will download and restore from a remote URL, overwriting the current site. Are you sure?' );
		}

		// Prepare storage directory
		$storage  = uniqid();
		$dest_dir = AI1WM_STORAGE_PATH . DIRECTORY_SEPARATOR . $storage;

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			WP_CLI::error( 'Could not create temporary storage directory: ' . $dest_dir );
		}

		$dest_path = $dest_dir . DIRECTORY_SEPARATOR . $archive;

		WP_CLI::log( 'Downloading: ' . $url );

		// Stream the remote file directly to disk (memory-efficient for large archives)
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'  => $timeout,
				'stream'   => true,
				'filename' => $dest_path,
			)
		);

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Download failed: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( $http_code !== 200 ) {
			WP_CLI::error( sprintf( 'Download failed: server returned HTTP %d.', $http_code ) );
		}

		if ( ! file_exists( $dest_path ) || filesize( $dest_path ) === 0 ) {
			WP_CLI::error( 'Download completed but the file is missing or empty.' );
		}

		WP_CLI::log( sprintf( 'Download complete (%s). Starting restore...', ai1wm_size_format( filesize( $dest_path ), 1 ) ) );

		// Run the import pipeline (priority 10 skips Import_Upload which requires $_FILES)
		$params = array(
			'priority'   => 10,
			'secret_key' => get_option( 'ai1wm_secret_key' ),
			'archive'    => $archive,
			'storage'    => $storage,
			'cli_args'   => array( 'yes' => $skip_confirm ),
		);

		Ai1wm_Import_Controller::import( $params );

		WP_CLI::success( 'Restore complete. Site restored from: ' . $url );
	}

	/**
	 * Resolve a file path to its absolute form.
	 *
	 * @param string $path
	 * @return string
	 */
	private function resolve_path( $path ) {
		if ( path_is_absolute( $path ) ) {
			return $path;
		}

		return getcwd() . DIRECTORY_SEPARATOR . $path;
	}
}
