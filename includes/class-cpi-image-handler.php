<?php
/**
 * Image Handler
 *
 * Handles featured image assignment via Media Library lookup (filename)
 * or remote URL download (sideload).
 *
 * @package    CSV_Post_Importer
 * @subpackage CSV_Post_Importer/includes
 * @since      1.0.0
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPI_Image_Handler
 *
 * @since 1.0.0
 */
class CPI_Image_Handler {

	/**
	 * Set featured image for a post.
	 *
	 * Delegates to find_by_filename() or download_from_url() based on $image_mode.
	 * Always returns an associative array — never throws.
	 *
	 * @since  1.0.0
	 * @param  int    $post_id     The target post ID.
	 * @param  string $value       Filename (e.g. "photo.webp") or full URL.
	 * @param  string $image_mode  'filename' | 'url'.
	 * @param  string $import_id   Current import run ID (for context — logging done by caller).
	 * @return array {
	 *     @type bool   $success
	 *     @type string $message
	 * }
	 */
	public function set_featured_image( $post_id, $value, $image_mode, $import_id ) {
		$post_id = absint( $post_id );
		$value   = sanitize_text_field( $value );

		if ( empty( $value ) ) {
			return array(
				'success' => false,
				'message' => __( 'Image value is empty.', 'csv-post-importer' ),
			);
		}

		if ( 'url' === $image_mode ) {
			return $this->_set_from_url( $value, $post_id );
		}

		// Default: filename mode.
		return $this->_set_from_filename( $value, $post_id );
	}

	// -------------------------------------------------------------------------
	// Private: filename mode
	// -------------------------------------------------------------------------

	/**
	 * Find attachment by filename and set as featured image.
	 *
	 * @since  1.0.0
	 * @param  string $filename  e.g. "photo.webp"
	 * @param  int    $post_id
	 * @return array {success, message}
	 */
	private function _set_from_filename( $filename, $post_id ) {
		$attach_id = $this->find_by_filename( $filename );

		if ( ! $attach_id ) {
			return array(
				'success' => false,
				/* translators: %s: filename */
				'message' => sprintf( __( 'Image not found in Media Library: "%s"', 'csv-post-importer' ), $filename ),
			);
		}

		$result = set_post_thumbnail( $post_id, $attach_id );

		if ( false === $result ) {
			return array(
				'success' => false,
				/* translators: 1: filename, 2: attachment ID */
				'message' => sprintf( __( 'set_post_thumbnail failed for "%1$s" (attachment ID: %2$d).', 'csv-post-importer' ), $filename, $attach_id ),
			);
		}

		return array(
			'success' => true,
			/* translators: 1: filename, 2: attachment ID */
			'message' => sprintf( __( 'Featured image set from Media Library: "%1$s" (ID: %2$d).', 'csv-post-importer' ), $filename, $attach_id ),
		);
	}

	// -------------------------------------------------------------------------
	// Private: URL mode
	// -------------------------------------------------------------------------

	/**
	 * Download image from URL, import into Media Library, set as featured image.
	 *
	 * @since  1.0.0
	 * @param  string $url      Full image URL.
	 * @param  int    $post_id
	 * @return array {success, message}
	 */
	private function _set_from_url( $url, $post_id ) {
		$result = $this->download_from_url( $url, $post_id );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				/* translators: 1: URL, 2: WP_Error message */
				'message' => sprintf( __( 'Failed to download image from "%1$s": %2$s', 'csv-post-importer' ), $url, $result->get_error_message() ),
			);
		}

		$attach_id = absint( $result );
		$thumb     = set_post_thumbnail( $post_id, $attach_id );

		if ( false === $thumb ) {
			return array(
				'success' => false,
				/* translators: 1: URL, 2: attachment ID */
				'message' => sprintf( __( 'Image downloaded but set_post_thumbnail failed for "%1$s" (attachment ID: %2$d).', 'csv-post-importer' ), $url, $attach_id ),
			);
		}

		return array(
			'success' => true,
			/* translators: 1: URL, 2: attachment ID */
			'message' => sprintf( __( 'Featured image downloaded and set from "%1$s" (ID: %2$d).', 'csv-post-importer' ), $url, $attach_id ),
		);
	}

	// -------------------------------------------------------------------------
	// Public: find_by_filename
	// -------------------------------------------------------------------------

	/**
	 * Search Media Library for an attachment by filename.
	 *
	 * Tries 3 strategies in order:
	 *   1. post_title of attachment (filename without extension)
	 *   2. guid LIKE '%filename%'
	 *   3. _wp_attached_file meta LIKE '%filename%'
	 *
	 * Applies filter `cpi_image_search_args` before each WP_Query.
	 *
	 * @since  1.0.0
	 * @param  string $filename  e.g. "photo.webp"
	 * @return int|null  Attachment ID on success, null if not found.
	 */
	public function find_by_filename( $filename ) {
		$filename = sanitize_file_name( $filename );

		if ( empty( $filename ) ) {
			return null;
		}

		// Strategy 1: post_title (filename without extension).
		$title_slug = pathinfo( $filename, PATHINFO_FILENAME );

		$args_1 = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'title'          => $title_slug,
			'no_found_rows'  => true,
		);

		/**
		 * Filters WP_Query args used to search for an image attachment.
		 *
		 * @since 1.0.0
		 * @param array  $args     WP_Query arguments.
		 * @param string $filename Original filename string.
		 * @param string $strategy Search strategy identifier: 'title' | 'guid' | 'meta'.
		 */
		$args_1   = apply_filters( 'cpi_image_search_args', $args_1, $filename, 'title' );
		$query_1  = new WP_Query( $args_1 );

		if ( $query_1->have_posts() ) {
			return (int) $query_1->posts[0];
		}

		// Strategy 2: guid LIKE '%filename%'.
		global $wpdb;

		$like      = '%' . $wpdb->esc_like( $filename ) . '%';
		$attach_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type   = 'attachment'
				   AND post_status = 'inherit'
				   AND guid LIKE %s
				 LIMIT 1",
				$like
			)
		);

		if ( $attach_id ) {
			return (int) $attach_id;
		}

		// Strategy 3: _wp_attached_file meta LIKE '%filename%'.
		$meta_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $filename,
					'compare' => 'LIKE',
				),
			),
		);

		$meta_args = apply_filters( 'cpi_image_search_args', $meta_args, $filename, 'meta' );
		$query_3   = new WP_Query( $meta_args );

		if ( $query_3->have_posts() ) {
			return (int) $query_3->posts[0];
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Public: download_from_url
	// -------------------------------------------------------------------------

	/**
	 * Download an image from a remote URL and import it into the Media Library.
	 *
	 * Requires WordPress media functions (loaded on demand).
	 *
	 * @since  1.0.0
	 * @param  string $url      Full image URL.
	 * @param  int    $post_id  Post ID to attach the image to.
	 * @return int|WP_Error  Attachment ID on success, WP_Error on failure.
	 */
	public function download_from_url( $url, $post_id ) {
		// Validate URL.
		$url = esc_url_raw( $url );

		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'cpi_invalid_url',
				/* translators: %s: URL */
				sprintf( __( 'Invalid image URL: "%s"', 'csv-post-importer' ), $url )
			);
		}

		// Load WordPress media sideload functions.
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attach_id = media_sideload_image( $url, $post_id, null, 'id' );

		return $attach_id; // int or WP_Error.
	}
}
