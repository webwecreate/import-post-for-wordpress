<?php
/**
 * CSV Parser
 *
 * Handles reading and parsing CSV files for import.
 *
 * @package    CSV_Post_Importer
 * @subpackage Includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPI_CSV_Parser
 *
 * Parses CSV files into arrays of rows with UTF-8 BOM handling
 * and multi-line cell support.
 *
 * @since 1.0.0
 */
class CPI_CSV_Parser {

	/**
	 * Default CSV delimiter.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $delimiter = ',';

	/**
	 * Default CSV enclosure character.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $enclosure = '"';

	/**
	 * Default CSV escape character.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $escape = '\\';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $delimiter Optional. CSV delimiter. Default ','.
	 * @param string $enclosure Optional. CSV enclosure. Default '"'.
	 * @param string $escape    Optional. CSV escape char. Default '\\'.
	 */
	public function __construct( $delimiter = ',', $enclosure = '"', $escape = '\\' ) {
		$this->delimiter = $delimiter;
		$this->enclosure = $enclosure;
		$this->escape    = $escape;
	}

	/**
	 * Parse a CSV file into an array of associative rows.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the CSV file.
	 * @return array|WP_Error    Array of associative arrays (header => value) or WP_Error.
	 */
	public function parse( $file_path ) {
		$file_path = $this->validate_file( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		$handle = $this->open_file( $file_path );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		// Read and strip BOM from first line.
		$headers = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape );
		if ( false === $headers ) {
			fclose( $handle );
			return new WP_Error( 'cpi_empty_csv', __( 'CSV file is empty or has no headers.', 'csv-post-importer' ) );
		}
		$headers = $this->strip_bom( $headers );
		$headers = array_map( 'trim', $headers );

		$rows = array();
		while ( ( $data = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape ) ) !== false ) {
			// Skip completely empty rows.
			if ( $this->is_empty_row( $data ) ) {
				continue;
			}

			// Pad or trim to match header count.
			$data = array_pad( $data, count( $headers ), '' );
			$data = array_slice( $data, 0, count( $headers ) );

			$row = array();
			foreach ( $headers as $index => $header ) {
				$row[ $header ] = isset( $data[ $index ] ) ? $data[ $index ] : '';
			}
			$rows[] = $row;
		}

		fclose( $handle );

		return $rows;
	}

	/**
	 * Get only the header row from a CSV file.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the CSV file.
	 * @return array|WP_Error    Array of column header strings or WP_Error.
	 */
	public function get_headers( $file_path ) {
		$file_path = $this->validate_file( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		$handle = $this->open_file( $file_path );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		$headers = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape );
		fclose( $handle );

		if ( false === $headers ) {
			return new WP_Error( 'cpi_empty_csv', __( 'CSV file is empty or has no headers.', 'csv-post-importer' ) );
		}

		$headers = $this->strip_bom( $headers );
		$headers = array_map( 'trim', $headers );

		return $headers;
	}

	/**
	 * Get headers plus a limited preview of data rows.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the CSV file.
	 * @param  int    $limit     Optional. Number of preview rows. Default 5.
	 * @return array|WP_Error    Associative array with 'headers' and 'rows' keys, or WP_Error.
	 */
	public function get_preview( $file_path, $limit = 5 ) {
		$file_path = $this->validate_file( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		$handle = $this->open_file( $file_path );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		$headers = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape );
		if ( false === $headers ) {
			fclose( $handle );
			return new WP_Error( 'cpi_empty_csv', __( 'CSV file is empty or has no headers.', 'csv-post-importer' ) );
		}

		$headers = $this->strip_bom( $headers );
		$headers = array_map( 'trim', $headers );

		$rows  = array();
		$count = 0;

		while ( $count < absint( $limit ) && ( $data = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape ) ) !== false ) {
			if ( $this->is_empty_row( $data ) ) {
				continue;
			}

			$data = array_pad( $data, count( $headers ), '' );
			$data = array_slice( $data, 0, count( $headers ) );

			$row = array();
			foreach ( $headers as $index => $header ) {
				$row[ $header ] = isset( $data[ $index ] ) ? $data[ $index ] : '';
			}
			$rows[] = $row;
			$count++;
		}

		fclose( $handle );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Count total data rows in a CSV file (excluding header).
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the CSV file.
	 * @return int|WP_Error      Row count or WP_Error.
	 */
	public function count_rows( $file_path ) {
		$file_path = $this->validate_file( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		$handle = $this->open_file( $file_path );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		// Skip header.
		fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape );

		$count = 0;
		while ( ( $data = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure, $this->escape ) ) !== false ) {
			if ( ! $this->is_empty_row( $data ) ) {
				$count++;
			}
		}

		fclose( $handle );

		return $count;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Validate file path and readability.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Path to validate.
	 * @return string|WP_Error   Sanitized path or WP_Error.
	 */
	private function validate_file( $file_path ) {
		$file_path = sanitize_text_field( $file_path );

		if ( empty( $file_path ) ) {
			return new WP_Error( 'cpi_no_file', __( 'No file path provided.', 'csv-post-importer' ) );
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'cpi_file_not_found', __( 'CSV file not found.', 'csv-post-importer' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new WP_Error( 'cpi_file_not_readable', __( 'CSV file is not readable.', 'csv-post-importer' ) );
		}

		// Ensure file is within the allowed uploads directory.
		$upload_dir = wp_normalize_path( CPI_UPLOAD_DIR );
		$real_path  = wp_normalize_path( realpath( $file_path ) );

		if ( strpos( $real_path, $upload_dir ) !== 0 ) {
			return new WP_Error( 'cpi_file_outside_dir', __( 'CSV file is outside the allowed directory.', 'csv-post-importer' ) );
		}

		return $real_path;
	}

	/**
	 * Open a file handle in read mode with UTF-8 encoding.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Validated file path.
	 * @return resource|WP_Error File handle or WP_Error.
	 */
	private function open_file( $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'cpi_file_open_failed', __( 'Could not open CSV file.', 'csv-post-importer' ) );
		}

		return $handle;
	}

	/**
	 * Strip UTF-8 BOM from the first cell of a headers array.
	 *
	 * @since  1.0.0
	 * @param  array $headers Array of header strings.
	 * @return array          Headers with BOM removed from first element.
	 */
	private function strip_bom( array $headers ) {
		if ( ! empty( $headers[0] ) ) {
			$bom = "\xEF\xBB\xBF";
			if ( substr( $headers[0], 0, 3 ) === $bom ) {
				$headers[0] = substr( $headers[0], 3 );
			}
		}
		return $headers;
	}

	/**
	 * Check whether a parsed CSV row is entirely empty.
	 *
	 * @since  1.0.0
	 * @param  array $row Array of cell values.
	 * @return bool       True if all cells are empty strings or null.
	 */
	private function is_empty_row( array $row ) {
		foreach ( $row as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}
}
