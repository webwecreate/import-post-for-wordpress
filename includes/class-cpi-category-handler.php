<?php
/**
 * Category Handler
 *
 * Handles category lookup, creation, and assignment for imported posts.
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
 * Class CPI_Category_Handler
 *
 * Responsible for:
 *  - Resolving or creating category terms (get_or_create_term)
 *  - Building the term ID hierarchy from flat CSV data (build_term_tree)
 *  - Resolving which IDs to actually assign based on assign mode (resolve_assign_ids)
 *  - Assigning terms to a post via wp_set_post_terms (assign)
 *
 * @since 1.0.0
 */
class CPI_Category_Handler {

	/**
	 * Assign categories to a post.
	 *
	 * Main entry point called by CPI_Admin::run_import_loop() after post creation/update.
	 *
	 * @since  1.0.0
	 * @param  int    $post_id       Target post ID.
	 * @param  array  $category_data {
	 *     CSV category values (raw, unsanitized).
	 *     @type string $main          Main category name or slug.
	 *     @type string $parent_sub    Parent sub-category name or slug.
	 *     @type string $sub           Sub-category name or slug.
	 * }
	 * @param  string $assign_mode   'all' | 'deepest' | 'custom'
	 * @param  array  $custom_levels {
	 *     Used only when $assign_mode = 'custom'.
	 *     @type bool $main        Assign main category?
	 *     @type bool $parent_sub  Assign parent sub-category?
	 *     @type bool $sub         Assign sub-category?
	 * }
	 * @return array {
	 *   @type bool   $success  Whether wp_set_post_terms() succeeded.
	 *   @type string $message  Human-readable result or error message.
	 * }
	 */
	public function assign( int $post_id, array $category_data, string $assign_mode = 'all', array $custom_levels = array() ): array {

		// Sanitize incoming category names.
		$category_data = $this->sanitize_category_data( $category_data );

		// All fields empty → nothing to do, not an error.
		if ( $this->is_empty_category_data( $category_data ) ) {
			return array(
				'success' => true,
				'message' => __( 'ไม่มีข้อมูล category — ข้ามการ assign', 'csv-post-importer' ),
			);
		}

		// Build hierarchy → array of term IDs keyed by level.
		$build_result = $this->build_term_tree( $category_data );

		if ( ! $build_result['success'] ) {
			return array(
				'success' => false,
				'message' => $build_result['message'],
			);
		}

		$term_tree = $build_result['term_tree'];

		// Resolve which IDs to assign.
		$assign_ids = $this->resolve_assign_ids( $term_tree, $assign_mode, $custom_levels );

		if ( empty( $assign_ids ) ) {
			return array(
				'success' => true,
				'message' => __( 'ไม่มี term ID ที่ต้อง assign หลังจาก resolve', 'csv-post-importer' ),
			);
		}

		/**
		 * Filters the term IDs that will be assigned to the post.
		 *
		 * @since 1.0.0
		 * @param int[]  $assign_ids    Term IDs resolved from assign mode.
		 * @param array  $term_tree     Full term tree (all levels).
		 * @param string $assign_mode   The assign mode used.
		 * @param array  $custom_levels Custom level flags (for 'custom' mode).
		 * @param int    $post_id       Target post ID.
		 */
		$assign_ids = apply_filters( 'cpi_category_assign_ids', $assign_ids, $term_tree, $assign_mode, $custom_levels, $post_id );

		$assign_ids = array_map( 'intval', $assign_ids );
		$assign_ids = array_filter( $assign_ids );
		$assign_ids = array_values( $assign_ids );

		$result = wp_set_post_terms( $post_id, $assign_ids, 'category', false );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: WP_Error message */
					__( 'wp_set_post_terms() ล้มเหลว: %s', 'csv-post-importer' ),
					$result->get_error_message()
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: comma-separated term IDs */
				__( 'Assign categories สำเร็จ (term IDs: %s)', 'csv-post-importer' ),
				implode( ', ', $assign_ids )
			),
		);
	}

	// ======================================================================
	// get_or_create_term
	// ======================================================================

	/**
	 * Get an existing term by name (case-insensitive) or create it.
	 *
	 * Searches by both slug and name to handle both CSV column styles.
	 *
	 * @since  1.0.0
	 * @param  string $name       Category name or slug from CSV.
	 * @param  int    $parent_id  Parent term ID (0 = top-level).
	 * @return int                Term ID.
	 * @throws Exception          When term creation fails.
	 */
	public function get_or_create_term( string $name, int $parent_id = 0 ): int {

		$name = trim( $name );

		if ( '' === $name ) {
			throw new Exception( __( 'ชื่อ category ว่างเปล่า — ไม่สามารถ get/create ได้', 'csv-post-importer' ) );
		}

		// ------------------------------------------------------------------
		// 1. Try to find by slug (sanitize_title converts name → slug)
		// ------------------------------------------------------------------
		$slug = sanitize_title( $name );

		$existing = get_term_by( 'slug', $slug, 'category' );

		if ( $existing instanceof WP_Term ) {
			// Verify parent matches (avoids returning a term at wrong hierarchy level).
			if ( (int) $existing->parent === $parent_id ) {
				return (int) $existing->term_id;
			}
		}

		// ------------------------------------------------------------------
		// 2. Try to find by name (case-insensitive via WP_Term_Query)
		// ------------------------------------------------------------------
		$args = array(
			'taxonomy'   => 'category',
			'name'       => $name,
			'parent'     => $parent_id,
			'number'     => 1,
			'hide_empty' => false,
			'fields'     => 'ids',
		);

		$terms = get_terms( $args );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return (int) $terms[0];
		}

		// ------------------------------------------------------------------
		// 3. Create new term
		// ------------------------------------------------------------------
		$insert_args = array(
			'slug'   => $slug,
			'parent' => $parent_id,
		);

		$result = wp_insert_term( $name, 'category', $insert_args );

		if ( is_wp_error( $result ) ) {
			// WordPress may return 'term_exists' error with the existing term ID.
			$existing_id = $result->get_error_data( 'term_exists' );
			if ( $existing_id ) {
				return (int) $existing_id;
			}

			throw new Exception(
				sprintf(
					/* translators: 1: category name, 2: error message */
					__( 'สร้าง category "%1$s" ล้มเหลว: %2$s', 'csv-post-importer' ),
					esc_html( $name ),
					$result->get_error_message()
				)
			);
		}

		return (int) $result['term_id'];
	}

	// ======================================================================
	// build_term_tree
	// ======================================================================

	/**
	 * Build a hierarchical tree of term IDs from flat category data.
	 *
	 * Creates terms that don't exist yet. Hierarchy:
	 *   main (top-level) → parent_sub (child of main) → sub (child of parent_sub)
	 *
	 * Missing levels are skipped gracefully; hierarchy is preserved for present levels.
	 *
	 * @since  1.0.0
	 * @param  array $category_data {
	 *     @type string $main        Main category name/slug (may be empty).
	 *     @type string $parent_sub  Parent sub-category name/slug (may be empty).
	 *     @type string $sub         Sub-category name/slug (may be empty).
	 * }
	 * @return array {
	 *   @type bool   $success   Whether the build succeeded.
	 *   @type string $message   Error message (only when success = false).
	 *   @type array  $term_tree {
	 *       Term IDs keyed by level (value is 0 when level was skipped/empty).
	 *       @type int $main        Term ID of main category (or 0).
	 *       @type int $parent_sub  Term ID of parent sub-category (or 0).
	 *       @type int $sub         Term ID of sub-category (or 0).
	 *   }
	 * }
	 */
	public function build_term_tree( array $category_data ): array {

		$tree = array(
			'main'       => 0,
			'parent_sub' => 0,
			'sub'        => 0,
		);

		$main_name       = $category_data['main']       ?? '';
		$parent_sub_name = $category_data['parent_sub'] ?? '';
		$sub_name        = $category_data['sub']        ?? '';

		// ---- Main category ----
		if ( '' !== $main_name ) {
			try {
				$tree['main'] = $this->get_or_create_term( $main_name, 0 );
			} catch ( Exception $e ) {
				return array(
					'success'   => false,
					'message'   => sprintf(
						/* translators: 1: category name, 2: error */
						__( 'ไม่สามารถสร้าง/หา main category "%1$s": %2$s', 'csv-post-importer' ),
						esc_html( $main_name ),
						$e->getMessage()
					),
					'term_tree' => $tree,
				);
			}
		}

		// ---- Parent sub-category ----
		if ( '' !== $parent_sub_name ) {
			// Parent of parent_sub = main (if exists), otherwise top-level.
			$parent_id = $tree['main'];
			try {
				$tree['parent_sub'] = $this->get_or_create_term( $parent_sub_name, $parent_id );
			} catch ( Exception $e ) {
				return array(
					'success'   => false,
					'message'   => sprintf(
						/* translators: 1: category name, 2: error */
						__( 'ไม่สามารถสร้าง/หา parent_sub category "%1$s": %2$s', 'csv-post-importer' ),
						esc_html( $parent_sub_name ),
						$e->getMessage()
					),
					'term_tree' => $tree,
				);
			}
		}

		// ---- Sub-category ----
		if ( '' !== $sub_name ) {
			// Parent of sub = parent_sub (if exists), else main, else top-level.
			if ( $tree['parent_sub'] > 0 ) {
				$parent_id = $tree['parent_sub'];
			} elseif ( $tree['main'] > 0 ) {
				$parent_id = $tree['main'];
			} else {
				$parent_id = 0;
			}

			try {
				$tree['sub'] = $this->get_or_create_term( $sub_name, $parent_id );
			} catch ( Exception $e ) {
				return array(
					'success'   => false,
					'message'   => sprintf(
						/* translators: 1: category name, 2: error */
						__( 'ไม่สามารถสร้าง/หา sub category "%1$s": %2$s', 'csv-post-importer' ),
						esc_html( $sub_name ),
						$e->getMessage()
					),
					'term_tree' => $tree,
				);
			}
		}

		return array(
			'success'   => true,
			'message'   => '',
			'term_tree' => $tree,
		);
	}

	// ======================================================================
	// resolve_assign_ids
	// ======================================================================

	/**
	 * Resolve which term IDs to assign based on the assign mode.
	 *
	 * @since  1.0.0
	 * @param  array  $term_tree    {
	 *     @type int $main        Term ID (0 = not present).
	 *     @type int $parent_sub  Term ID (0 = not present).
	 *     @type int $sub         Term ID (0 = not present).
	 * }
	 * @param  string $assign_mode 'all' | 'deepest' | 'custom'
	 * @param  array  $custom_levels {
	 *     @type bool $main        Include main?
	 *     @type bool $parent_sub  Include parent_sub?
	 *     @type bool $sub         Include sub?
	 * }
	 * @return int[]  Flat list of term IDs to assign.
	 */
	public function resolve_assign_ids( array $term_tree, string $assign_mode, array $custom_levels = array() ): array {

		$main_id       = (int) ( $term_tree['main']       ?? 0 );
		$parent_sub_id = (int) ( $term_tree['parent_sub'] ?? 0 );
		$sub_id        = (int) ( $term_tree['sub']        ?? 0 );

		switch ( $assign_mode ) {

			// ------ All levels ------
			case 'all':
				$ids = array();
				if ( $main_id > 0 )       { $ids[] = $main_id; }
				if ( $parent_sub_id > 0 ) { $ids[] = $parent_sub_id; }
				if ( $sub_id > 0 )        { $ids[] = $sub_id; }
				return $ids;

			// ------ Deepest level only ------
			case 'deepest':
				if ( $sub_id > 0 )        { return array( $sub_id ); }
				if ( $parent_sub_id > 0 ) { return array( $parent_sub_id ); }
				if ( $main_id > 0 )       { return array( $main_id ); }
				return array();

			// ------ Custom — user ticked which levels to include ------
			case 'custom':
				$ids = array();
				if ( ! empty( $custom_levels['main'] )       && $main_id > 0 )       { $ids[] = $main_id; }
				if ( ! empty( $custom_levels['parent_sub'] ) && $parent_sub_id > 0 ) { $ids[] = $parent_sub_id; }
				if ( ! empty( $custom_levels['sub'] )        && $sub_id > 0 )        { $ids[] = $sub_id; }
				return $ids;

			default:
				// Fallback: assign all.
				$ids = array();
				if ( $main_id > 0 )       { $ids[] = $main_id; }
				if ( $parent_sub_id > 0 ) { $ids[] = $parent_sub_id; }
				if ( $sub_id > 0 )        { $ids[] = $sub_id; }
				return $ids;
		}
	}

	// ======================================================================
	// Private helpers
	// ======================================================================

	/**
	 * Sanitize all values in $category_data.
	 *
	 * @since  1.0.0
	 * @param  array $category_data  Raw category data from CSV.
	 * @return array                 Sanitized category data.
	 */
	private function sanitize_category_data( array $category_data ): array {

		return array(
			'main'       => isset( $category_data['main'] )       ? sanitize_text_field( $category_data['main'] )       : '',
			'parent_sub' => isset( $category_data['parent_sub'] ) ? sanitize_text_field( $category_data['parent_sub'] ) : '',
			'sub'        => isset( $category_data['sub'] )        ? sanitize_text_field( $category_data['sub'] )        : '',
		);
	}

	/**
	 * Check if all category fields are empty.
	 *
	 * @since  1.0.0
	 * @param  array $category_data  Sanitized category data.
	 * @return bool                  True if all empty.
	 */
	private function is_empty_category_data( array $category_data ): bool {

		return '' === $category_data['main']
			&& '' === $category_data['parent_sub']
			&& '' === $category_data['sub'];
	}
}
