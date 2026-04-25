<?php
/**
 * Admin Controller
 *
 * Registers the admin menu, enqueues assets, handles AJAX requests,
 * and delegates rendering to view files.
 *
 * @package    CSV_Post_Importer
 * @subpackage Admin
 * @since      1.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPI_Admin
 *
 * @since 1.0.0
 */
class CPI_Admin {

	/**
	 * Transient key prefix for CSV file path storage.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TRANSIENT_CSV_PATH   = 'cpi_csv_path_';

	/**
	 * Transient key prefix for CSV preview data storage.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TRANSIENT_CSV_PREVIEW = 'cpi_csv_preview_';

	/**
	 * Transient expiry in seconds (1 hour).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TRANSIENT_EXPIRY = 3600;

	/**
	 * Register all admin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers — logged-in users only.
		add_action( 'wp_ajax_cpi_upload_csv', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_cpi_run_import', array( $this, 'handle_import' ) );
		add_action( 'wp_ajax_cpi_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	// =========================================================================
	// Menu
	// =========================================================================

	/**
	 * Register standalone top-level admin menu.
	 *
	 * @since 1.5.1
	 * @return void
	 */
	public function register_menu() {
		// Top-level menu — visible in the main sidebar.
		add_menu_page(
			__( 'CSV Post Importer', 'csv-post-importer' ),
			__( 'CSV Importer', 'csv-post-importer' ),
			'import',
			'csv-post-importer',
			array( $this, 'render_import_page' ),
			'dashicons-media-spreadsheet',
			30
		);

		// Rename the auto-created first submenu item.
		add_submenu_page(
			'csv-post-importer',
			__( 'Import', 'csv-post-importer' ),
			__( 'Import', 'csv-post-importer' ),
			'import',
			'csv-post-importer',
			array( $this, 'render_import_page' )
		);

		// Import Logs submenu.
		add_submenu_page(
			'csv-post-importer',
			__( 'Import Logs', 'csv-post-importer' ),
			__( 'Import Logs', 'csv-post-importer' ),
			'import',
			'csv-post-importer-logs',
			array( $this, 'render_logs_page' )
		);
	}

	// =========================================================================
	// Assets
	// =========================================================================

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		$plugin_pages = array(
			'toplevel_page_csv-post-importer',
			'csv-importer_page_csv-post-importer-logs',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'cpi-admin',
			CPI_PLUGIN_URL . 'admin/css/cpi-admin.css',
			array(),
			CPI_VERSION
		);

		wp_enqueue_script(
			'cpi-admin',
			CPI_PLUGIN_URL . 'admin/js/cpi-admin.js',
			array( 'jquery' ),
			CPI_VERSION,
			true
		);

		wp_localize_script(
			'cpi-admin',
			'cpiAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'uploadNonce'    => wp_create_nonce( 'cpi_upload_csv' ),
				'importNonce'    => wp_create_nonce( 'cpi_run_import' ),
				'clearLogsNonce' => wp_create_nonce( 'cpi_clear_logs' ),
				'strings'        => array(
					'uploading'      => __( 'Uploading…', 'csv-post-importer' ),
					'importing'      => __( 'Running import…', 'csv-post-importer' ),
					'importDone'     => __( 'Import complete.', 'csv-post-importer' ),
					'errorUpload'    => __( 'Upload failed. Please try again.', 'csv-post-importer' ),
					'errorImport'    => __( 'Import failed. Please try again.', 'csv-post-importer' ),
					'confirmClear'   => __( 'Are you sure you want to clear these logs?', 'csv-post-importer' ),
					'selectColumn'   => __( '— Select column —', 'csv-post-importer' ),
					'notMapped'      => __( '— Not mapped —', 'csv-post-importer' ),
				),
			)
		);
	}

	// =========================================================================
	// Page Renderers
	// =========================================================================

	/**
	 * Render the main import page (Steps 1–3).
	 *
	 * Determines which step to show based on transient state.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'import' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'csv-post-importer' ) );
		}

		$session_id = $this->get_session_id();
		$step       = $this->get_current_step( $session_id );

		switch ( $step ) {
			case 2:
				$preview = get_transient( self::TRANSIENT_CSV_PREVIEW . $session_id );
				include CPI_PLUGIN_DIR . 'admin/views/page-mapping.php';
				break;

			case 3:
				$import_results = get_transient( 'cpi_import_results_' . $session_id );
				include CPI_PLUGIN_DIR . 'admin/views/page-result.php';
				break;

			default:
				include CPI_PLUGIN_DIR . 'admin/views/page-import.php';
				break;
		}
	}

	/**
	 * Render the import logs page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'import' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'csv-post-importer' ) );
		}

		include CPI_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	// =========================================================================
	// AJAX: Upload CSV
	// =========================================================================

	/**
	 * Handle AJAX CSV upload request.
	 *
	 * Saves the file to CPI_UPLOAD_DIR, parses headers and preview rows,
	 * stores them in transients, and returns JSON.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_upload() {
		check_ajax_referer( 'cpi_upload_csv', 'nonce' );

		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'csv-post-importer' ) ) );
		}

		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file received.', 'csv-post-importer' ) ) );
		}

		$file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Validate MIME type.
		$mime = isset( $file['type'] ) ? sanitize_text_field( $file['type'] ) : '';
		$allowed_mimes = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		if ( ! in_array( $mime, $allowed_mimes, true ) ) {
			// Also allow by extension as fallback.
			$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
			if ( 'csv' !== $ext ) {
				wp_send_json_error( array( 'message' => __( 'Only CSV files are allowed.', 'csv-post-importer' ) ) );
			}
		}

		// Validate upload error.
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'File upload error.', 'csv-post-importer' ) ) );
		}

		// Build destination path.
		$session_id  = $this->generate_session_id();
		$safe_name   = sanitize_file_name( $file['name'] );
		$dest_path   = trailingslashit( CPI_UPLOAD_DIR ) . $session_id . '_' . $safe_name;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save uploaded file.', 'csv-post-importer' ) ) );
		}

		// Parse preview.
		$parser  = new CPI_CSV_Parser();
		$preview = $parser->get_preview( $dest_path, 5 );

		if ( is_wp_error( $preview ) ) {
			@unlink( $dest_path ); // phpcs:ignore
			wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
		}

		$row_count = $parser->count_rows( $dest_path );

		// Store in transients.
		set_transient( self::TRANSIENT_CSV_PATH . $session_id, $dest_path, self::TRANSIENT_EXPIRY );
		set_transient( self::TRANSIENT_CSV_PREVIEW . $session_id, $preview, self::TRANSIENT_EXPIRY );

		wp_send_json_success(
			array(
				'session_id' => $session_id,
				'filename'   => esc_html( $safe_name ),
				'row_count'  => absint( $row_count ),
				'headers'    => array_map( 'esc_html', $preview['headers'] ),
				'rows'       => $this->sanitize_preview_rows( $preview['rows'] ),
			)
		);
	}

	// =========================================================================
	// AJAX: Run Import
	// =========================================================================

	/**
	 * Handle AJAX import trigger request.
	 *
	 * Reads the mapping config from POST, loops CSV rows, and returns results.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_import() {
		check_ajax_referer( 'cpi_run_import', 'nonce' );

		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'csv-post-importer' ) ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'csv-post-importer' ) ) );
		}

		$csv_path = get_transient( self::TRANSIENT_CSV_PATH . $session_id );
		if ( ! $csv_path || ! file_exists( $csv_path ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV session expired or file not found. Please re-upload.', 'csv-post-importer' ) ) );
		}

		// Parse and sanitize mapping config.
		$mapping = $this->parse_mapping_config( $_POST ); // phpcs:ignore

		// Parse all rows.
		$parser = new CPI_CSV_Parser();
		$rows   = $parser->parse( $csv_path );
		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ) );
		}

		// Generate import ID.
		$import_id = CPI_Logger::generate_import_id();

		// Run the import loop.
		$results = $this->run_import_loop( $rows, $mapping, $import_id );

		// Store results summary in transient for result page.
		set_transient( 'cpi_import_results_' . $session_id, array(
			'import_id' => $import_id,
			'summary'   => $results['summary'],
		), self::TRANSIENT_EXPIRY );

		// Clean up CSV file.
		@unlink( $csv_path ); // phpcs:ignore
		delete_transient( self::TRANSIENT_CSV_PATH . $session_id );

		wp_send_json_success(
			array(
				'import_id' => esc_html( $import_id ),
				'summary'   => $results['summary'],
				'redirect'  => add_query_arg(
					array(
						'page'       => 'csv-post-importer',
						'step'       => '3',
						'session_id' => rawurlencode( $session_id ),
					),
					admin_url( 'tools.php' )
				),
			)
		);
	}

	// =========================================================================
	// AJAX: Clear Logs
	// =========================================================================

	/**
	 * Handle AJAX clear logs request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_clear_logs() {
		check_ajax_referer( 'cpi_clear_logs', 'nonce' );

		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'csv-post-importer' ) ) );
		}

		$import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
		$logger    = new CPI_Logger();

		if ( 'all' === $import_id ) {
			$logger->clear_all_logs();
		} elseif ( ! empty( $import_id ) ) {
			$logger->clear_logs( $import_id );
		} else {
			wp_send_json_error( array( 'message' => __( 'No import ID specified.', 'csv-post-importer' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'csv-post-importer' ) ) );
	}

	// =========================================================================
	// Import Loop
	// =========================================================================

	/**
	 * Loop through parsed rows and delegate to Post Creator, Category Handler,
	 * and Image Handler.
	 *
	 * @since  1.0.0
	 * @param  array  $rows      Parsed CSV rows (associative).
	 * @param  array  $mapping   Sanitized mapping configuration.
	 * @param  string $import_id Unique import run ID.
	 * @return array             Array with 'summary' key.
	 */
	private function run_import_loop( array $rows, array $mapping, $import_id ) {
		$logger  = new CPI_Logger();
		$creator = new CPI_Post_Creator();
		$cat_handler = new CPI_Category_Handler();
		$img_handler = new CPI_Image_Handler();

		$summary = array(
			'total'          => count( $rows ),
			'success'        => 0,
			'updated'        => 0,
			'skipped'        => 0,
			'error'          => 0,
			'image_error'    => 0,
			'category_error' => 0,
		);

		foreach ( $rows as $row_number => $row ) {
			$row_data = $this->map_row( $row, $mapping );

			/**
			 * Filter row data before insert/update.
			 *
			 * @since 1.0.0
			 * @param array $row_data  Mapped row data.
			 * @param array $row       Original raw row.
			 * @param array $mapping   Mapping configuration.
			 */
			$row_data = apply_filters( 'cpi_row_data', $row_data, $row, $mapping );

			// Skip row if no title.
			if ( empty( $row_data['post_title'] ) ) {
				$logger->log( $import_id, $row_number + 1, '', CPI_Logger::STATUS_SKIPPED, __( 'Missing post title.', 'csv-post-importer' ) );
				$summary['skipped']++;
				continue;
			}

			// Create or update post.
			$result = $creator->create_or_update( $row_data, $mapping );

			if ( is_wp_error( $result ) ) {
				$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_ERROR, $result->get_error_message() );
				$summary['error']++;
				continue;
			}

			$post_id = $result['post_id'];
			$status  = $result['status']; // 'created' or 'updated' or 'skipped'

			if ( 'skipped' === $status ) {
				$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_SKIPPED, __( 'Post already exists (create mode).', 'csv-post-importer' ) );
				$summary['skipped']++;
				continue;
			}

			$log_status = ( 'updated' === $status ) ? CPI_Logger::STATUS_UPDATED : CPI_Logger::STATUS_SUCCESS;

			// Assign categories.
			if ( ! empty( $row_data['category'] ) || ! empty( $row_data['parent_sub_category'] ) || ! empty( $row_data['sub_category'] ) ) {
				$cat_result = $cat_handler->assign_categories( $post_id, $row_data, $mapping );
				if ( is_wp_error( $cat_result ) ) {
					$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_CATEGORY_ERROR, $cat_result->get_error_message() );
					$summary['category_error']++;
					$log_status = CPI_Logger::STATUS_CATEGORY_ERROR;
				}
			}

			// Set featured image.
			if ( ! empty( $row_data['featured_image'] ) ) {
				$img_result = $img_handler->set_featured_image( $post_id, $row_data['featured_image'], $mapping['image_mode'] );
				if ( is_wp_error( $img_result ) ) {
					$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_IMAGE_ERROR, $img_result->get_error_message() );
					$summary['image_error']++;
					if ( CPI_Logger::STATUS_SUCCESS === $log_status || CPI_Logger::STATUS_UPDATED === $log_status ) {
						$log_status = CPI_Logger::STATUS_IMAGE_ERROR;
					}
				}
			}

			// Final success log.
			if ( CPI_Logger::STATUS_SUCCESS === $log_status ) {
				$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_SUCCESS, '' );
				$summary['success']++;
			} elseif ( CPI_Logger::STATUS_UPDATED === $log_status ) {
				$logger->log( $import_id, $row_number + 1, $row_data['post_title'], CPI_Logger::STATUS_UPDATED, '' );
				$summary['updated']++;
			}
		}

		return array( 'summary' => $summary );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Map a CSV row to post field values using the mapping config.
	 *
	 * @since  1.0.0
	 * @param  array $row     Associative CSV row (column_name => value).
	 * @param  array $mapping Mapping configuration.
	 * @return array          Post data array.
	 */
	private function map_row( array $row, array $mapping ) {
		$data = array();

		$fields = array(
			'post_title', 'post_content', 'post_status',
			'featured_image', 'category', 'parent_sub_category', 'sub_category',
		);

		foreach ( $fields as $field ) {
			$col = isset( $mapping['fields'][ $field ] ) ? $mapping['fields'][ $field ] : '';
			if ( ! empty( $col ) && isset( $row[ $col ] ) ) {
				$data[ $field ] = $row[ $col ];
			} else {
				$data[ $field ] = '';
			}
		}

		// Unique key for update mode.
		if ( ! empty( $mapping['unique_key_column'] ) && isset( $row[ $mapping['unique_key_column'] ] ) ) {
			$data['unique_key_value'] = $row[ $mapping['unique_key_column'] ];
		}

		return $data;
	}

	/**
	 * Parse and sanitize the mapping configuration from POST data.
	 *
	 * @since  1.0.0
	 * @param  array $post Raw $_POST data.
	 * @return array       Sanitized mapping config.
	 */
	private function parse_mapping_config( array $post ) {
		$mapping = array(
			'fields'           => array(),
			'import_mode'      => 'create',
			'unique_key'       => 'post_title',
			'unique_key_column'=> '',
			'custom_meta_key'  => '',
			'image_mode'       => 'filename',
			'assign_mode'      => 'all',
			'assign_levels'    => array(),
		);

		// Field column mappings.
		$field_names = array(
			'post_title', 'post_content', 'post_status',
			'featured_image', 'category', 'parent_sub_category', 'sub_category',
		);
		foreach ( $field_names as $field ) {
			$key = 'map_' . $field;
			if ( ! empty( $post[ $key ] ) ) {
				$mapping['fields'][ $field ] = sanitize_text_field( wp_unslash( $post[ $key ] ) );
			}
		}

		// Import mode.
		if ( isset( $post['import_mode'] ) && in_array( $post['import_mode'], array( 'create', 'update' ), true ) ) {
			$mapping['import_mode'] = sanitize_text_field( $post['import_mode'] );
		}

		// Unique key type.
		$valid_unique_keys = array( 'post_title', 'post_id', 'post_slug', 'custom_meta' );
		if ( isset( $post['unique_key'] ) && in_array( $post['unique_key'], $valid_unique_keys, true ) ) {
			$mapping['unique_key'] = sanitize_text_field( $post['unique_key'] );
		}

		// Column that holds the unique key value.
		if ( ! empty( $post['unique_key_column'] ) ) {
			$mapping['unique_key_column'] = sanitize_text_field( wp_unslash( $post['unique_key_column'] ) );
		}

		// Custom meta key.
		if ( ! empty( $post['custom_meta_key'] ) ) {
			$mapping['custom_meta_key'] = sanitize_key( $post['custom_meta_key'] );
		}

		// Image mode.
		if ( isset( $post['image_mode'] ) && in_array( $post['image_mode'], array( 'filename', 'url' ), true ) ) {
			$mapping['image_mode'] = sanitize_text_field( $post['image_mode'] );
		}

		// Category assign mode.
		$valid_assign = array( 'all', 'deepest', 'custom' );
		if ( isset( $post['assign_mode'] ) && in_array( $post['assign_mode'], $valid_assign, true ) ) {
			$mapping['assign_mode'] = sanitize_text_field( $post['assign_mode'] );
		}

		// Custom assign levels (checkboxes).
		if ( ! empty( $post['assign_levels'] ) && is_array( $post['assign_levels'] ) ) {
			$valid_levels = array( 'category', 'parent_sub_category', 'sub_category' );
			foreach ( $post['assign_levels'] as $level ) {
				$level = sanitize_text_field( $level );
				if ( in_array( $level, $valid_levels, true ) ) {
					$mapping['assign_levels'][] = $level;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Sanitize preview row data for JSON output.
	 *
	 * @since  1.0.0
	 * @param  array $rows Preview rows.
	 * @return array       Sanitized rows.
	 */
	private function sanitize_preview_rows( array $rows ) {
		$clean = array();
		foreach ( $rows as $row ) {
			$clean_row = array();
			foreach ( $row as $key => $value ) {
				$clean_row[ esc_html( $key ) ] = esc_html( $value );
			}
			$clean[] = $clean_row;
		}
		return $clean;
	}

	/**
	 * Get or create a session ID for the current import session.
	 *
	 * Session ID is stored in a query arg when navigating between steps.
	 *
	 * @since  1.0.0
	 * @return string Session ID.
	 */
	private function get_session_id() {
		if ( isset( $_GET['session_id'] ) ) { // phpcs:ignore
			return sanitize_text_field( wp_unslash( $_GET['session_id'] ) ); // phpcs:ignore
		}
		return '';
	}

	/**
	 * Determine the current step based on transient state and query args.
	 *
	 * @since  1.0.0
	 * @param  string $session_id Current session ID.
	 * @return int                Step number (1, 2, or 3).
	 */
	private function get_current_step( $session_id ) {
		// Explicit step in URL (after import completes).
		if ( isset( $_GET['step'] ) ) { // phpcs:ignore
			$step = absint( $_GET['step'] ); // phpcs:ignore
			if ( in_array( $step, array( 1, 2, 3 ), true ) ) {
				return $step;
			}
		}

		if ( empty( $session_id ) ) {
			return 1;
		}

		// If import results transient exists → step 3.
		if ( get_transient( 'cpi_import_results_' . $session_id ) ) {
			return 3;
		}

		// If CSV path transient exists → step 2.
		if ( get_transient( self::TRANSIENT_CSV_PATH . $session_id ) ) {
			return 2;
		}

		return 1;
	}

	/**
	 * Generate a new unique session ID.
	 *
	 * @since  1.0.0
	 * @return string Unique session ID.
	 */
	private function generate_session_id() {
		return 'sess_' . wp_generate_password( 12, false );
	}
}
