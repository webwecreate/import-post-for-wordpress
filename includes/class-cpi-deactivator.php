<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    CsvPostImporter
 * @subpackage CsvPostImporter/includes
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPI_Deactivator
 *
 * Handles cleanup tasks that must run when the plugin is deactivated:
 *  - Removes leftover CSV files from the temporary upload directory.
 *  - Clears any scheduled cron events registered by this plugin.
 *
 * NOTE: The `wp_cpi_logs` table and plugin options are intentionally
 * preserved on deactivation so that import history survives a
 * deactivate → reactivate cycle.  Use uninstall.php for full removal.
 *
 * @since 1.0.0
 */
class CPI_Deactivator {

	/**
	 * Run all deactivation routines.
	 *
	 * Called via register_deactivation_hook() in the main plugin file.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function deactivate() {
		self::clear_temp_uploads();
		self::clear_scheduled_events();
	}

	// ─── Private Helpers ─────────────────────────────────────────────────────

	/**
	 * Delete all .csv files from the temporary upload directory.
	 *
	 * Leaves the directory itself, .htaccess, and index.php intact so
	 * reactivation does not need to recreate them.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function clear_temp_uploads() {
		if ( ! defined( 'CPI_UPLOAD_DIR' ) || ! is_dir( CPI_UPLOAD_DIR ) ) {
			return;
		}

		$files = glob( CPI_UPLOAD_DIR . '*.csv' );

		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}
	}

	/**
	 * Clear any cron events scheduled by this plugin.
	 *
	 * Currently a placeholder — uncomment and extend if scheduled events
	 * are introduced in a later Chat.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function clear_scheduled_events() {
		// Example (uncomment when needed):
		// $timestamp = wp_next_scheduled( 'cpi_scheduled_import' );
		// if ( $timestamp ) {
		//     wp_unschedule_event( $timestamp, 'cpi_scheduled_import' );
		// }
	}
}
