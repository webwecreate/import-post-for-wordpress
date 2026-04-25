<?php
/**
 * Admin View: Step 2 — Map Columns
 *
 * Variables available from CPI_Admin::render_import_page():
 *   $preview     = array( 'headers' => [], 'rows' => [] )
 *   $session_id  = string
 *
 * @package    CSV_Post_Importer
 * @subpackage Admin/Views
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $preview ) || empty( $preview['headers'] ) ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>';
	esc_html_e( 'Session expired or invalid. Please re-upload your CSV.', 'csv-post-importer' );
	echo '</p></div></div>';
	return;
}

$headers     = $preview['headers'];
$rows        = $preview['rows'];
$session_id  = isset( $session_id ) ? $session_id : '';

/**
 * Build the <option> list for a column-mapping dropdown.
 *
 * @param array  $headers  CSV headers.
 * @param string $selected Currently selected value.
 * @param bool   $required If true, no "not mapped" option is added.
 */
function cpi_mapping_options( $headers, $selected = '', $required = false ) {
	if ( ! $required ) {
		printf( '<option value="">%s</option>', esc_html__( '— Not mapped —', 'csv-post-importer' ) );
	} else {
		printf( '<option value="">%s</option>', esc_html__( '— Select column —', 'csv-post-importer' ) );
	}
	foreach ( $headers as $header ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $header ),
			selected( $selected, $header, false ),
			esc_html( $header )
		);
	}
}
?>
<div class="wrap cpi-wrap">

	<div class="cpi-header">
		<h1 class="cpi-title">
			<span class="cpi-title-icon dashicons dashicons-editor-table"></span>
			<?php esc_html_e( 'CSV Post Importer', 'csv-post-importer' ); ?>
		</h1>

		<div class="cpi-steps">
			<div class="cpi-step cpi-step--done">
				<span class="cpi-step__num dashicons dashicons-yes"></span>
				<span class="cpi-step__label"><?php esc_html_e( 'Upload CSV', 'csv-post-importer' ); ?></span>
			</div>
			<div class="cpi-step-connector cpi-step-connector--done"></div>
			<div class="cpi-step cpi-step--active">
				<span class="cpi-step__num">2</span>
				<span class="cpi-step__label"><?php esc_html_e( 'Map Columns', 'csv-post-importer' ); ?></span>
			</div>
			<div class="cpi-step-connector"></div>
			<div class="cpi-step">
				<span class="cpi-step__num">3</span>
				<span class="cpi-step__label"><?php esc_html_e( 'Import Result', 'csv-post-importer' ); ?></span>
			</div>
		</div>
	</div>

	<form id="cpi-mapping-form" method="post">
		<?php wp_nonce_field( 'cpi_run_import', 'cpi_mapping_nonce' ); ?>
		<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
		<input type="hidden" name="action" value="cpi_run_import">

		<!-- ═══════════════════════════════════════════════════════════
		     SECTION 1: Post Fields
		     ═══════════════════════════════════════════════════════════ -->
		<div class="cpi-card">
			<h2 class="cpi-card__title cpi-section-title">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Post Fields', 'csv-post-importer' ); ?>
			</h2>

			<table class="cpi-mapping-table">
				<thead>
					<tr>
						<th class="cpi-col-field"><?php esc_html_e( 'WordPress Field', 'csv-post-importer' ); ?></th>
						<th class="cpi-col-arrow"></th>
						<th class="cpi-col-csv"><?php esc_html_e( 'CSV Column', 'csv-post-importer' ); ?></th>
						<th class="cpi-col-note"><?php esc_html_e( 'Notes', 'csv-post-importer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Post Title (required) -->
					<tr class="cpi-mapping-row cpi-mapping-row--required">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Post Title', 'csv-post-importer' ); ?></span>
							<span class="cpi-required-badge"><?php esc_html_e( 'Required', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_post_title" id="map_post_title" class="cpi-select" required>
								<?php cpi_mapping_options( $headers, '', true ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Used as post title and for duplicate detection.', 'csv-post-importer' ); ?></td>
					</tr>

					<!-- Post Content -->
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Post Content', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_post_content" id="map_post_content" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Main post body (HTML allowed).', 'csv-post-importer' ); ?></td>
					</tr>

					<!-- Post Status -->
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Post Status', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_post_status" id="map_post_status" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note">
							<?php
							/* translators: default post status */
							esc_html_e( 'publish, draft, private, etc. Default: publish.', 'csv-post-importer' );
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div><!-- .cpi-card -->

		<!-- ═══════════════════════════════════════════════════════════
		     SECTION 2: Featured Image
		     ═══════════════════════════════════════════════════════════ -->
		<div class="cpi-card">
			<h2 class="cpi-card__title cpi-section-title">
				<span class="dashicons dashicons-format-image"></span>
				<?php esc_html_e( 'Featured Image', 'csv-post-importer' ); ?>
			</h2>

			<div class="cpi-image-mode-wrap">
				<div class="cpi-radio-group">
					<label class="cpi-radio-label cpi-radio-label--active" data-radio="image-mode-filename">
						<input type="radio" name="image_mode" value="filename" checked>
						<span class="cpi-radio-custom"></span>
						<span class="cpi-radio-text">
							<strong><?php esc_html_e( 'Filename', 'csv-post-importer' ); ?></strong>
							<small><?php esc_html_e( 'Search existing Media Library', 'csv-post-importer' ); ?></small>
						</span>
					</label>
					<label class="cpi-radio-label" data-radio="image-mode-url">
						<input type="radio" name="image_mode" value="url">
						<span class="cpi-radio-custom"></span>
						<span class="cpi-radio-text">
							<strong><?php esc_html_e( 'URL', 'csv-post-importer' ); ?></strong>
							<small><?php esc_html_e( 'Download from external URL', 'csv-post-importer' ); ?></small>
						</span>
					</label>
				</div>

				<div class="cpi-image-mode-detail" id="image-mode-filename-detail">
					<p class="cpi-hint">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'CSV column should contain the filename (e.g. photo.webp). The file must already exist in your Media Library.', 'csv-post-importer' ); ?>
					</p>
				</div>
				<div class="cpi-image-mode-detail" id="image-mode-url-detail" style="display:none;">
					<p class="cpi-hint">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'CSV column should contain a full image URL. The file will be downloaded and added to your Media Library.', 'csv-post-importer' ); ?>
					</p>
				</div>
			</div>

			<table class="cpi-mapping-table cpi-mapping-table--compact">
				<tbody>
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Image Column', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_featured_image" id="map_featured_image" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Leave unmapped to skip featured image.', 'csv-post-importer' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div><!-- .cpi-card -->

		<!-- ═══════════════════════════════════════════════════════════
		     SECTION 3: Categories
		     ═══════════════════════════════════════════════════════════ -->
		<div class="cpi-card">
			<h2 class="cpi-card__title cpi-section-title">
				<span class="dashicons dashicons-category"></span>
				<?php esc_html_e( 'Categories', 'csv-post-importer' ); ?>
			</h2>

			<table class="cpi-mapping-table">
				<thead>
					<tr>
						<th class="cpi-col-field"><?php esc_html_e( 'Category Level', 'csv-post-importer' ); ?></th>
						<th class="cpi-col-arrow"></th>
						<th class="cpi-col-csv"><?php esc_html_e( 'CSV Column', 'csv-post-importer' ); ?></th>
						<th class="cpi-col-note"><?php esc_html_e( 'Notes', 'csv-post-importer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Main Category', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_category" id="map_category" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Top-level category name or slug.', 'csv-post-importer' ); ?></td>
					</tr>
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Parent Sub-category', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_parent_sub_category" id="map_parent_sub_category" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Child of Main Category.', 'csv-post-importer' ); ?></td>
					</tr>
					<tr class="cpi-mapping-row">
						<td class="cpi-col-field">
							<span class="cpi-field-name"><?php esc_html_e( 'Sub-category', 'csv-post-importer' ); ?></span>
						</td>
						<td class="cpi-col-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<td class="cpi-col-csv">
							<select name="map_sub_category" id="map_sub_category" class="cpi-select">
								<?php cpi_mapping_options( $headers ); ?>
							</select>
						</td>
						<td class="cpi-col-note"><?php esc_html_e( 'Child of Parent Sub-category.', 'csv-post-importer' ); ?></td>
					</tr>
				</tbody>
			</table>

			<!-- Category Assign Mode -->
			<div class="cpi-subsection">
				<h3 class="cpi-subsection__title"><?php esc_html_e( 'Category Assign Mode', 'csv-post-importer' ); ?></h3>

				<div class="cpi-radio-group cpi-radio-group--vertical">
					<label class="cpi-radio-label">
						<input type="radio" name="assign_mode" value="all" checked>
						<span class="cpi-radio-custom"></span>
						<span class="cpi-radio-text">
							<strong><?php esc_html_e( 'All levels', 'csv-post-importer' ); ?></strong>
							<small><?php esc_html_e( 'Assign every level that has data (main + parent sub + sub).', 'csv-post-importer' ); ?></small>
						</span>
					</label>
					<label class="cpi-radio-label">
						<input type="radio" name="assign_mode" value="deepest">
						<span class="cpi-radio-custom"></span>
						<span class="cpi-radio-text">
							<strong><?php esc_html_e( 'Deepest only', 'csv-post-importer' ); ?></strong>
							<small><?php esc_html_e( 'Assign only the deepest level that has data.', 'csv-post-importer' ); ?></small>
						</span>
					</label>
					<label class="cpi-radio-label" id="assign-mode-custom-label">
						<input type="radio" name="assign_mode" value="custom" id="assign-mode-custom">
						<span class="cpi-radio-custom"></span>
						<span class="cpi-radio-text">
							<strong><?php esc_html_e( 'Custom', 'csv-post-importer' ); ?></strong>
							<small><?php esc_html_e( 'Choose which levels to assign.', 'csv-post-importer' ); ?></small>
						</span>
					</label>
				</div>

				<div id="cpi-assign-levels-custom" class="cpi-assign-levels" style="display:none;">
					<label class="cpi-check-label">
						<input type="checkbox" name="assign_levels[]" value="category">
						<?php esc_html_e( 'Main Category', 'csv-post-importer' ); ?>
					</label>
					<label class="cpi-check-label">
						<input type="checkbox" name="assign_levels[]" value="parent_sub_category">
						<?php esc_html_e( 'Parent Sub-category', 'csv-post-importer' ); ?>
					</label>
					<label class="cpi-check-label">
						<input type="checkbox" name="assign_levels[]" value="sub_category">
						<?php esc_html_e( 'Sub-category', 'csv-post-importer' ); ?>
					</label>
				</div>
			</div>

		</div><!-- .cpi-card -->

		<!-- ═══════════════════════════════════════════════════════════
		     SECTION 4: Import Mode
		     ═══════════════════════════════════════════════════════════ -->
		<div class="cpi-card">
			<h2 class="cpi-card__title cpi-section-title">
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'Import Mode', 'csv-post-importer' ); ?>
			</h2>

			<div class="cpi-radio-group">
				<label class="cpi-radio-label cpi-radio-label--active" data-radio="import-mode-create">
					<input type="radio" name="import_mode" value="create" checked>
					<span class="cpi-radio-custom"></span>
					<span class="cpi-radio-text">
						<strong><?php esc_html_e( 'Create new', 'csv-post-importer' ); ?></strong>
						<small><?php esc_html_e( 'Always create a new post for every row.', 'csv-post-importer' ); ?></small>
					</span>
				</label>
				<label class="cpi-radio-label" data-radio="import-mode-update">
					<input type="radio" name="import_mode" value="update">
					<span class="cpi-radio-custom"></span>
					<span class="cpi-radio-text">
						<strong><?php esc_html_e( 'Update existing', 'csv-post-importer' ); ?></strong>
						<small><?php esc_html_e( 'Find an existing post and update it.', 'csv-post-importer' ); ?></small>
					</span>
				</label>
			</div>

			<!-- Update mode options (shown only when Update is selected) -->
			<div id="cpi-update-mode-options" style="display:none;" class="cpi-update-options">
				<h3 class="cpi-subsection__title"><?php esc_html_e( 'Unique Key for Matching', 'csv-post-importer' ); ?></h3>

				<table class="cpi-mapping-table cpi-mapping-table--compact">
					<tbody>
						<tr>
							<td class="cpi-col-field">
								<span class="cpi-field-name"><?php esc_html_e( 'Match posts by', 'csv-post-importer' ); ?></span>
							</td>
							<td class="cpi-col-csv" colspan="2">
								<select name="unique_key" id="unique-key-type" class="cpi-select">
									<option value="post_title"><?php esc_html_e( 'Post Title', 'csv-post-importer' ); ?></option>
									<option value="post_id"><?php esc_html_e( 'Post ID', 'csv-post-importer' ); ?></option>
									<option value="post_slug"><?php esc_html_e( 'Post Slug', 'csv-post-importer' ); ?></option>
									<option value="custom_meta"><?php esc_html_e( 'Custom Meta Field', 'csv-post-importer' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="cpi-col-field">
								<span class="cpi-field-name"><?php esc_html_e( 'CSV Column with Key', 'csv-post-importer' ); ?></span>
							</td>
							<td class="cpi-col-csv" colspan="2">
								<select name="unique_key_column" id="unique-key-column" class="cpi-select">
									<?php cpi_mapping_options( $headers, '', true ); ?>
								</select>
							</td>
						</tr>
						<!-- Custom meta key input (shown only when custom_meta is selected) -->
						<tr id="cpi-custom-meta-row" style="display:none;">
							<td class="cpi-col-field">
								<span class="cpi-field-name"><?php esc_html_e( 'Meta Key', 'csv-post-importer' ); ?></span>
							</td>
							<td class="cpi-col-csv" colspan="2">
								<input type="text" name="custom_meta_key" id="custom-meta-key" class="regular-text"
									placeholder="<?php esc_attr_e( 'e.g. _product_sku', 'csv-post-importer' ); ?>">
							</td>
						</tr>
					</tbody>
				</table>
			</div>

		</div><!-- .cpi-card -->

		<!-- ═══════════════════════════════════════════════════════════
		     CSV Preview (collapsed)
		     ═══════════════════════════════════════════════════════════ -->
		<div class="cpi-card cpi-card--preview">
			<button type="button" class="cpi-toggle-preview" id="cpi-toggle-preview" aria-expanded="false">
				<span class="dashicons dashicons-table-row-after"></span>
				<?php esc_html_e( 'Show CSV Preview', 'csv-post-importer' ); ?>
				<span class="dashicons dashicons-arrow-down-alt2 cpi-toggle-icon"></span>
			</button>

			<div id="cpi-preview-collapsed" style="display:none;">
				<p class="cpi-preview-count">
					<?php
					printf(
						/* translators: %d: total row count */
						esc_html__( 'Total: %d rows', 'csv-post-importer' ),
						count( $rows )
					);
					?>
				</p>
				<div class="cpi-table-wrap">
					<table class="widefat cpi-preview-table">
						<thead>
							<tr>
								<th class="cpi-col-rownum">#</th>
								<?php foreach ( $headers as $header ) : ?>
									<th><?php echo esc_html( $header ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $i => $row ) : ?>
								<tr>
									<td class="cpi-col-rownum"><?php echo esc_html( $i + 1 ); ?></td>
									<?php foreach ( $headers as $header ) : ?>
										<td><?php echo esc_html( isset( $row[ $header ] ) ? $row[ $header ] : '' ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div><!-- .cpi-card--preview -->

		<!-- Run Import Button -->
		<div class="cpi-card cpi-card--actions">
			<div class="cpi-import-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=csv-post-importer' ) ); ?>" class="button">
					<?php esc_html_e( '← Back to Upload', 'csv-post-importer' ); ?>
				</a>
				<button type="button" id="cpi-btn-run-import" class="button button-primary button-large">
					<span class="dashicons dashicons-database-import"></span>
					<?php esc_html_e( 'Run Import', 'csv-post-importer' ); ?>
				</button>
			</div>

			<!-- Import progress (shown during AJAX) -->
			<div id="cpi-import-progress" class="cpi-progress" style="display:none;">
				<div class="cpi-progress__bar">
					<div class="cpi-progress__fill cpi-progress__fill--animated"></div>
				</div>
				<span class="cpi-progress__text"><?php esc_html_e( 'Running import…', 'csv-post-importer' ); ?></span>
			</div>

			<!-- Import error -->
			<div id="cpi-import-error" class="cpi-notice cpi-notice--error" style="display:none;"></div>
		</div><!-- .cpi-card--actions -->

	</form>

</div><!-- .cpi-wrap -->
