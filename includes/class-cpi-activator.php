<?php
/**
 * Fired during plugin activation.
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
 * Class CPI_Activator
 *
 * Handles all tasks that must run once when the plugin is activated:
 *  - Creates the temporary CSV upload directory.
 *  - Creates the `wp_cpi_logs` custom DB table.
 *
 * @since 1.0.0
 */
class CPI_Activator {

	/**
	 * Run all activation routines.
	 *
	 * Called via register_activation_hook() in the main plugin file.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function activate() {
		self::create_upload_dir();
		self::create_log_table();
	}

	/**
	 * Ensure the plugin environment is set up correctly on every load.
	 *
	 * Runs create_upload_dir() and create_log_table() only when the stored
	 * DB version doesn't match the current plugin version — covers cases
	 * where activation hook didn't fire (manual FTP upload, staging copy, etc.)
	 *
	 * @since  1.5.7
	 * @return void
	 */
	public static function maybe_setup() {
		if ( get_option( 'cpi_db_version' ) !== CPI_VERSION ) {
			self::create_upload_dir();
			self::create_log_table();
		}
	}

	// ─── Private Helpers ─────────────────────────────────────────────────────

	/**
	 * Create the temporary upload directory and protect it with .htaccess.
	 *
	 * Uses the CPI_UPLOAD_DIR constant defined in the main plugin file.
	 * An .htaccess file is written to deny direct HTTP access to uploaded CSVs.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function create_upload_dir() {
		if ( ! file_exists( CPI_UPLOAD_DIR ) ) {
			wp_mkdir_p( CPI_UPLOAD_DIR );
		}

		// Write an index.php to prevent directory listing.
		$index = CPI_UPLOAD_DIR . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, '<?php // Silence is golden.' );
		}

		// Deny direct access to all files in this directory.
		$htaccess = CPI_UPLOAD_DIR . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n<FilesMatch \".*\">\n\tOrder Allow,Deny\n\tDeny from all\n</FilesMatch>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, $rules );
		}
	}

	/**
	 * Create the `wp_cpi_logs` custom database table.
	 *
	 * Uses dbDelta() so the schema is applied safely on first activation
	 * and skipped on subsequent runs if the table already exists.
	 *
	 * Schema:
	 *  id          — Auto-increment primary key.
	 *  import_id   — UUID / timestamp string grouping one import run.
	 *  row_number  — Original row index in the CSV (1-based, header excluded).
	 *  filename    — Original value of the featured_image column (may be empty).
	 *  status      — One of: success | updated | skipped | error | image_error | category_error.
	 *  message     — Human-readable description of the outcome or error.
	 *  created_at  — UTC timestamp of when this log entry was written.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function create_log_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . CPI_LOG_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			`id`          BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
			`import_id`   VARCHAR(64)  NOT NULL DEFAULT '',
			`row_number`  BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
			`filename`    VARCHAR(255) NOT NULL DEFAULT '',
			`status`      VARCHAR(32)  NOT NULL DEFAULT '',
			`message`     TEXT         NOT NULL,
			`created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (`id`),
			KEY `import_id`  (`import_id`),
			KEY `status`     (`status`),
			KEY `created_at` (`created_at`)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store the DB version so future upgrades can run migrations.
		update_option( 'cpi_db_version', CPI_VERSION );
	}
}
