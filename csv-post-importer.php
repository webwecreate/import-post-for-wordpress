<?php
/**
 * Plugin Name:       CSV Post Importer
 * Plugin URI:        https://github.com/your-repo/csv-post-importer
 * Description:       Import posts from CSV with featured image (Media Library or URL) and category/sub-category mapping, import modes, and error logging.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Webwecreate.com
 * Author URI:        https://Webwecreate.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       csv-post-importer
 * Domain Path:       /languages
 *
 * @package CsvPostImporter
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────

/** Plugin version. */
define( 'CPI_VERSION', '1.0.0' );

/** Absolute path to plugin directory (with trailing slash). */
define( 'CPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** URL to plugin directory (with trailing slash). */
define( 'CPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Plugin basename for use with hooks. */
define( 'CPI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/** Temporary upload directory inside plugin folder. */
define( 'CPI_UPLOAD_DIR', CPI_PLUGIN_DIR . 'uploads/' );

/** DB table name for import logs (without $wpdb->prefix). */
define( 'CPI_LOG_TABLE', 'cpi_logs' );

// ─── Autoload Includes ───────────────────────────────────────────────────────

/**
 * Load all required plugin classes.
 *
 * Files are loaded in dependency order: utilities first, then handlers,
 * then admin layer last.
 *
 * @since 1.0.0
 */
function cpi_load_dependencies() {
	$includes = array(
		// Core utilities / lifecycle
		'includes/class-cpi-activator.php',
		'includes/class-cpi-deactivator.php',
		'includes/class-cpi-logger.php',

		// Business logic (loaded on demand — uncomment in later Chats)
		// 'includes/class-cpi-csv-parser.php',
		// 'includes/class-cpi-post-creator.php',
		// 'includes/class-cpi-image-handler.php',
		// 'includes/class-cpi-category-handler.php',

		// Admin layer
		// 'admin/class-cpi-admin.php',
	);

	foreach ( $includes as $file ) {
		$path = CPI_PLUGIN_DIR . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

cpi_load_dependencies();

// ─── Activation / Deactivation Hooks ─────────────────────────────────────────

/**
 * Plugin activation hook callback.
 *
 * @since 1.0.0
 */
function cpi_activate() {
	CPI_Activator::activate();
}
register_activation_hook( __FILE__, 'cpi_activate' );

/**
 * Plugin deactivation hook callback.
 *
 * @since 1.0.0
 */
function cpi_deactivate() {
	CPI_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'cpi_deactivate' );

// ─── Bootstrap Admin ─────────────────────────────────────────────────────────

/**
 * Initialise the admin layer after all plugins are loaded.
 *
 * Wrapped in class_exists() so later Chats can enable CPI_Admin
 * by simply uncommenting the require in cpi_load_dependencies().
 *
 * @since 1.0.0
 */
function cpi_init() {
	if ( is_admin() && class_exists( 'CPI_Admin' ) ) {
		$admin = new CPI_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'cpi_init' );
