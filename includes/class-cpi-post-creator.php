<?php
/**
 * Post Creator
 *
 * Handles creating and updating WordPress posts from CSV row data.
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
 * Class CPI_Post_Creator
 *
 * Responsible for wp_insert_post() / wp_update_post() only.
 * Does NOT handle images or categories — those are delegated to their own classes.
 *
 * @since 1.0.0
 */
class CPI_Post_Creator {

	/**
	 * Create or update a post based on import mode.
	 *
	 * Entry point called by CPI_Admin::run_import_loop() for each CSV row.
	 *
	 * @since  1.0.0
	 * @param  array  $row_data   Associative array of mapped CSV data.
	 *                            Keys: post_title, post_content, post_status,
	 *                                  featured_image, category, parent_sub_category,
	 *                                  sub_category, [any extra mapped fields].
	 * @param  array  $mapping    Import config from parse_mapping_config():
	 *                            {
	 *                              import_mode:  'create'|'update',
	 *                              unique_key:   'post_title'|'post_id'|'post_slug'|'custom_meta',
	 *                              meta_key:     string (when unique_key = custom_meta),
	 *                            }
	 * @param  string $import_id  Current import run ID (for hooks / reference).
	 * @return array {
	 *   @type int|null $post_id  Inserted/updated post ID, or null on failure.
	 *   @type string   $status   'success' | 'updated' | 'skipped' | 'error'
	 *   @type string   $message  Human-readable result message.
	 * }
	 */
	public function create_or_update( array $row_data, array $mapping, string $import_id ): array {

		$import_mode = isset( $mapping['import_mode'] ) ? $mapping['import_mode'] : 'create';
		$unique_key  = isset( $mapping['unique_key'] )  ? $mapping['unique_key']  : 'post_title';
		$meta_key    = isset( $mapping['meta_key'] )    ? sanitize_key( $mapping['meta_key'] ) : '';

		// ------------------------------------------------------------------
		// Apply cpi_row_data filter — allow external modification of row data
		// ------------------------------------------------------------------

		/**
		 * Filters the row data before any insert or update operation.
		 *
		 * @since 1.0.0
		 * @param array  $row_data  Mapped row data.
		 * @param array  $mapping   Import configuration.
		 * @param string $import_id Current import run ID.
		 */
		$row_data = apply_filters( 'cpi_row_data', $row_data, $mapping, $import_id );

		// ------------------------------------------------------------------
		// Determine the lookup value for update mode
		// ------------------------------------------------------------------

		if ( 'update' === $import_mode ) {

			$lookup_value = $this->resolve_lookup_value( $row_data, $unique_key, $meta_key );

			if ( null === $lookup_value || '' === $lookup_value ) {
				return array(
					'post_id' => null,
					'status'  => 'error',
					'message' => sprintf(
						/* translators: %s: unique key name */
						__( 'Update mode: ค่า unique key "%s" ว่างเปล่า ข้ามแถวนี้', 'csv-post-importer' ),
						$unique_key
					),
				);
			}

			$existing_post_id = $this->find_existing_post( $lookup_value, $unique_key, $meta_key );

			if ( $existing_post_id ) {
				// ---- Update path ----
				try {
					$post_id = $this->update_post( $existing_post_id, $row_data );
					return array(
						'post_id' => $post_id,
						'status'  => 'updated',
						'message' => sprintf(
							/* translators: %d: post ID */
							__( 'อัปเดต post ID %d สำเร็จ', 'csv-post-importer' ),
							$post_id
						),
					);
				} catch ( Exception $e ) {
					return array(
						'post_id' => null,
						'status'  => 'error',
						'message' => $e->getMessage(),
					);
				}
			} else {
				// Unique key not found → skip (do not create in update mode)
				return array(
					'post_id' => null,
					'status'  => 'skipped',
					'message' => sprintf(
						/* translators: 1: unique key name, 2: lookup value */
						__( 'ไม่พบ post ที่ตรงกับ %1$s = "%2$s" — ข้ามแถวนี้', 'csv-post-importer' ),
						$unique_key,
						$lookup_value
					),
				);
			}
		}

		// ------------------------------------------------------------------
		// Create path
		// ------------------------------------------------------------------

		try {
			$post_id = $this->create_post( $row_data );
			return array(
				'post_id' => $post_id,
				'status'  => 'success',
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'สร้าง post ID %d สำเร็จ', 'csv-post-importer' ),
					$post_id
				),
			);
		} catch ( Exception $e ) {
			return array(
				'post_id' => null,
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	}

	// ======================================================================
	// find_existing_post
	// ======================================================================

	/**
	 * Find an existing post by unique key.
	 *
	 * @since  1.0.0
	 * @param  mixed  $value     The value to search for.
	 * @param  string $unique_key 'post_title' | 'post_id' | 'post_slug' | 'custom_meta'
	 * @param  string $meta_key  Required when $unique_key = 'custom_meta'.
	 * @return int|null          Post ID if found, null otherwise.
	 */
	public function find_existing_post( $value, string $unique_key, string $meta_key = '' ): ?int {

		switch ( $unique_key ) {

			// ---- post_title ----
			case 'post_title':
				$sanitized = sanitize_text_field( $value );
				$posts = get_posts(
					array(
						'post_type'      => 'any',
						'post_status'    => 'any',
						'title'          => $sanitized,
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				);
				return ! empty( $posts ) ? (int) $posts[0] : null;

			// ---- post_id ----
			case 'post_id':
				$post_id = absint( $value );
				if ( $post_id > 0 && get_post( $post_id ) ) {
					return $post_id;
				}
				return null;

			// ---- post_slug ----
			case 'post_slug':
				$slug  = sanitize_title( $value );
				$posts = get_posts(
					array(
						'post_type'      => 'any',
						'post_status'    => 'any',
						'name'           => $slug,
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				);
				return ! empty( $posts ) ? (int) $posts[0] : null;

			// ---- custom_meta ----
			case 'custom_meta':
				if ( '' === $meta_key ) {
					return null;
				}
				$posts = get_posts(
					array(
						'post_type'      => 'any',
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
							array(
								'key'   => $meta_key,
								'value' => sanitize_text_field( $value ),
							),
						),
					)
				);
				return ! empty( $posts ) ? (int) $posts[0] : null;

			default:
				return null;
		}
	}

	// ======================================================================
	// create_post
	// ======================================================================

	/**
	 * Insert a new post.
	 *
	 * Fires cpi_before_insert_post (action) and cpi_after_insert_post (action).
	 *
	 * @since  1.0.0
	 * @param  array $row_data  Mapped, pre-filtered row data.
	 * @return int              The new post ID.
	 * @throws Exception        When wp_insert_post() returns WP_Error or 0.
	 */
	public function create_post( array $row_data ): int {

		$post_arr = $this->build_post_array( $row_data );

		/**
		 * Fires immediately before a new post is inserted.
		 *
		 * @since 1.0.0
		 * @param array $post_arr  The post array about to be passed to wp_insert_post().
		 * @param array $row_data  The original (filtered) row data.
		 */
		do_action( 'cpi_before_insert_post', $post_arr, $row_data );

		$post_id = wp_insert_post( $post_arr, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: error message */
					__( 'wp_insert_post() ล้มเหลว: %s', 'csv-post-importer' ),
					$post_id->get_error_message()
				)
			);
		}

		if ( 0 === $post_id ) {
			throw new Exception( __( 'wp_insert_post() คืนค่า 0 โดยไม่มีข้อผิดพลาด', 'csv-post-importer' ) );
		}

		// Store custom meta fields (everything that isn't a core post field).
		$this->save_extra_meta( $post_id, $row_data );

		/**
		 * Fires immediately after a new post has been inserted successfully.
		 *
		 * @since 1.0.0
		 * @param int   $post_id  The inserted post ID.
		 * @param array $row_data The original (filtered) row data.
		 */
		do_action( 'cpi_after_insert_post', $post_id, $row_data );

		return $post_id;
	}

	// ======================================================================
	// update_post
	// ======================================================================

	/**
	 * Update an existing post.
	 *
	 * Fires cpi_before_update_post (action) and cpi_after_update_post (action).
	 *
	 * @since  1.0.0
	 * @param  int   $post_id   The existing post ID to update.
	 * @param  array $row_data  Mapped, pre-filtered row data.
	 * @return int              The updated post ID.
	 * @throws Exception        When wp_update_post() returns WP_Error or 0.
	 */
	public function update_post( int $post_id, array $row_data ): int {

		$post_arr         = $this->build_post_array( $row_data );
		$post_arr['ID']   = $post_id;

		/**
		 * Fires immediately before an existing post is updated.
		 *
		 * @since 1.0.0
		 * @param int   $post_id  The post ID about to be updated.
		 * @param array $post_arr The post array about to be passed to wp_update_post().
		 * @param array $row_data The original (filtered) row data.
		 */
		do_action( 'cpi_before_update_post', $post_id, $post_arr, $row_data );

		$result = wp_update_post( $post_arr, true );

		if ( is_wp_error( $result ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: error message */
					__( 'wp_update_post() ล้มเหลว: %s', 'csv-post-importer' ),
					$result->get_error_message()
				)
			);
		}

		if ( 0 === $result ) {
			throw new Exception( __( 'wp_update_post() คืนค่า 0 โดยไม่มีข้อผิดพลาด', 'csv-post-importer' ) );
		}

		// Update custom meta fields.
		$this->save_extra_meta( $post_id, $row_data );

		/**
		 * Fires immediately after a post has been updated successfully.
		 *
		 * @since 1.0.0
		 * @param int   $post_id  The updated post ID.
		 * @param array $row_data The original (filtered) row data.
		 */
		do_action( 'cpi_after_update_post', $post_id, $row_data );

		return $post_id;
	}

	// ======================================================================
	// Private helpers
	// ======================================================================

	/**
	 * Build the $postarr array for wp_insert_post() / wp_update_post().
	 *
	 * Only maps known core post fields; sanitizes every value.
	 *
	 * @since  1.0.0
	 * @param  array $row_data  Mapped row data.
	 * @return array            WordPress post array.
	 */
	private function build_post_array( array $row_data ): array {

		$post_arr = array();

		// post_title — required, sanitize as text.
		if ( ! empty( $row_data['post_title'] ) ) {
			$post_arr['post_title'] = sanitize_text_field( $row_data['post_title'] );
		}

		// post_content — allow HTML (kses applied by WP internally on save).
		if ( isset( $row_data['post_content'] ) && '' !== $row_data['post_content'] ) {
			$post_arr['post_content'] = wp_kses_post( $row_data['post_content'] );
		}

		// post_status — whitelist.
		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		if ( ! empty( $row_data['post_status'] ) && in_array( $row_data['post_status'], $allowed_statuses, true ) ) {
			$post_arr['post_status'] = $row_data['post_status'];
		} else {
			$post_arr['post_status'] = 'publish';
		}

		// post_type — whitelist against registered types.
		if ( ! empty( $row_data['post_type'] ) ) {
			$type = sanitize_key( $row_data['post_type'] );
			if ( post_type_exists( $type ) ) {
				$post_arr['post_type'] = $type;
			}
		}

		// post_date — optional, validate format.
		if ( ! empty( $row_data['post_date'] ) ) {
			$date = sanitize_text_field( $row_data['post_date'] );
			if ( false !== strtotime( $date ) ) {
				$post_arr['post_date'] = $date;
			}
		}

		// post_excerpt.
		if ( ! empty( $row_data['post_excerpt'] ) ) {
			$post_arr['post_excerpt'] = sanitize_textarea_field( $row_data['post_excerpt'] );
		}

		// post_name (slug).
		if ( ! empty( $row_data['post_name'] ) ) {
			$post_arr['post_name'] = sanitize_title( $row_data['post_name'] );
		}

		return $post_arr;
	}

	/**
	 * Save any extra (non-core) fields in $row_data as post meta.
	 *
	 * Keys that start with 'meta_' are saved with the prefix stripped.
	 * Example: 'meta_product_sku' → meta key '_product_sku' (with leading underscore
	 * kept if the original starts with underscore after stripping prefix).
	 *
	 * @since  1.0.0
	 * @param  int   $post_id   Target post ID.
	 * @param  array $row_data  Mapped row data.
	 * @return void
	 */
	private function save_extra_meta( int $post_id, array $row_data ): void {

		$core_keys = array(
			'post_title', 'post_content', 'post_status', 'post_type',
			'post_date', 'post_excerpt', 'post_name',
			// These are handled by their respective handler classes:
			'featured_image', 'category', 'parent_sub_category', 'sub_category',
		);

		foreach ( $row_data as $key => $value ) {
			if ( in_array( $key, $core_keys, true ) ) {
				continue;
			}
			// Save raw extra fields as post meta.
			$meta_key   = sanitize_key( $key );
			$meta_value = sanitize_text_field( $value );
			if ( '' !== $meta_key && '' !== $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Resolve the lookup value from row data for a given unique key type.
	 *
	 * @since  1.0.0
	 * @param  array  $row_data   Mapped row data.
	 * @param  string $unique_key Key type.
	 * @param  string $meta_key   Meta key (for custom_meta type).
	 * @return mixed              The lookup value, or null if not resolvable.
	 */
	private function resolve_lookup_value( array $row_data, string $unique_key, string $meta_key ) {

		switch ( $unique_key ) {
			case 'post_title':
				return isset( $row_data['post_title'] ) ? $row_data['post_title'] : null;

			case 'post_id':
				return isset( $row_data['post_id'] ) ? $row_data['post_id'] : null;

			case 'post_slug':
				return isset( $row_data['post_name'] ) ? $row_data['post_name'] : null;

			case 'custom_meta':
				// The CSV column for the custom meta value is stored under the meta_key name.
				return ( '' !== $meta_key && isset( $row_data[ $meta_key ] ) ) ? $row_data[ $meta_key ] : null;

			default:
				return null;
		}
	}
}
