<?php
/**
 * Import & error log handler.
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
 * Class CPI_Logger
 *
 * Writes and reads import/error log entries in the `wp_cpi_logs` table.
 *
 * Every meaningful event during an import run (success, skip, error, etc.)
 * MUST be recorded through this class — silent failures are not allowed.
 *
 * Supported status values:
 *  - success        → Post created successfully.
 *  - updated        → Existing post updated successfully.
 *  - skipped        → Row skipped (duplicate detected in Create mode).
 *  - error          → Fatal error prevented post insert/update.
 *  - image_error    → Post saved but featured image assignment failed.
 *  - category_error → Post saved but category assignment failed.
 *
 * @since 1.0.0
 */
class CPI_Logger {

	/**
	 * Allowed status values.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const STATUSES = array(
		'success',
		'updated',
		'skipped',
		'error',
		'image_error',
		'category_error',
	);

	// ─── Write ────────────────────────────────────────────────────────────────

	/**
	 * Write a single log entry.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $import_id  Unique identifier for the current import run
	 *                            (e.g. a UUID or timestamp string).
	 * @param  int    $row_number 1-based row index in the CSV (header excluded).
	 * @param  string $filename   Value of the featured_image column (may be '').
	 * @param  string $status     One of the CPI_Logger::STATUSES values.
	 * @param  string $message    Human-readable description of the result/error.
	 *
	 * @return int|false          Number of rows inserted, or false on failure.
	 */
	public static function log( $import_id, $row_number, $filename, $status, $message ) {
		global $wpdb;

		// Guard: reject unknown status values.
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status  = 'error';
			$message = sprintf(
				/* translators: %1$s invalid status, %2$s original message */
				'[CPI_Logger] Invalid status "%1$s" — original message: %2$s',
				esc_attr( $status ),
				$message
			);
		}

		$table = $wpdb->prefix . CPI_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->insert(
			$table,
			array(
				'import_id'  => sanitize_text_field( $import_id ),
				'row_number' => absint( $row_number ),
				'filename'   => sanitize_text_field( $filename ),
				'status'     => sanitize_key( $status ),
				'message'    => sanitize_textarea_field( $message ),
				'created_at' => current_time( 'mysql', true ), // UTC
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	// ─── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Retrieve log entries, optionally filtered by import run and/or status.
	 *
	 * @since  1.0.0
	 *
	 * @param  string|null $import_id Filter by a specific import run ID.
	 *                                Pass null to retrieve logs from all runs.
	 * @param  string|null $status    Filter by status value (must be one of
	 *                                CPI_Logger::STATUSES). Pass null for all.
	 * @param  int         $limit     Maximum number of rows to return. Default 200.
	 * @param  int         $offset    Number of rows to skip (for pagination). Default 0.
	 *
	 * @return array[]                Array of associative row arrays, newest first.
	 */
	public static function get_logs( $import_id = null, $status = null, $limit = 200, $offset = 0 ) {
		global $wpdb;

		$table  = $wpdb->prefix . CPI_LOG_TABLE;
		$where  = array( '1=1' );
		$params = array();

		if ( null !== $import_id ) {
			$where[]  = 'import_id = %s';
			$params[] = sanitize_text_field( $import_id );
		}

		if ( null !== $status && in_array( $status, self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $status );
		}

		$where_sql = implode( ' AND ', $where );
		$limit     = absint( $limit );
		$offset    = absint( $offset );
		$params[]  = $limit;
		$params[]  = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Return a summary count grouped by status for a given import run.
	 *
	 * Example return value:
	 *  [
	 *    'success'        => 42,
	 *    'updated'        => 0,
	 *    'skipped'        => 3,
	 *    'error'          => 1,
	 *    'image_error'    => 2,
	 *    'category_error' => 0,
	 *  ]
	 *
	 * @since  1.0.0
	 *
	 * @param  string $import_id The import run ID to summarise.
	 *
	 * @return int[]             Associative array of status => count.
	 */
	public static function get_summary( $import_id ) {
		global $wpdb;

		$table = $wpdb->prefix . CPI_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE import_id = %s GROUP BY status",
				sanitize_text_field( $import_id )
			),
			ARRAY_A
		);

		// Build a zero-filled summary so callers don't need to check isset().
		$summary = array_fill_keys( self::STATUSES, 0 );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( isset( $summary[ $row['status'] ] ) ) {
					$summary[ $row['status'] ] = (int) $row['cnt'];
				}
			}
		}

		return $summary;
	}

	/**
	 * Retrieve a list of distinct import run IDs, newest first.
	 *
	 * Used by the Logs admin page to populate the run selector.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $limit Maximum number of import runs to return. Default 50.
	 *
	 * @return string[]  Array of import_id strings.
	 */
	public static function get_import_ids( $limit = 50 ) {
		global $wpdb;

		$table  = $wpdb->prefix . CPI_LOG_TABLE;
		$limit  = absint( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT import_id FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	// ─── Delete ───────────────────────────────────────────────────────────────

	/**
	 * Delete log entries for a specific import run.
	 *
	 * Used by the "Clear logs" action on the Logs admin page.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $import_id The import run ID whose logs should be deleted.
	 *
	 * @return int|false         Number of rows deleted, or false on failure.
	 */
	public static function clear_logs( $import_id ) {
		global $wpdb;

		$table = $wpdb->prefix . CPI_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete(
			$table,
			array( 'import_id' => sanitize_text_field( $import_id ) ),
			array( '%s' )
		);
	}

	/**
	 * Delete ALL log entries (full table wipe).
	 *
	 * Intended for use by an admin "Clear all logs" button.
	 *
	 * @since  1.0.0
	 *
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public static function clear_all_logs() {
		global $wpdb;

		$table = $wpdb->prefix . CPI_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Generate a unique import run ID.
	 *
	 * Format: cpi_{YYYYmmdd_HHiiss}_{random6}
	 * Example: cpi_20260425_143022_a3f9b1
	 *
	 * @since  1.0.0
	 *
	 * @return string A unique import run identifier.
	 */
	public static function generate_import_id() {
		return 'cpi_' . gmdate( 'Ymd_His' ) . '_' . substr( bin2hex( random_bytes( 3 ) ), 0, 6 );
	}
}
