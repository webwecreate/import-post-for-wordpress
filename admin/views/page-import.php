<?php
/**
 * Admin View: Step 1 — Upload CSV
 *
 * @package    CSV_Post_Importer
 * @subpackage Admin/Views
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cpi-wrap">

	<div class="cpi-header">
		<h1 class="cpi-title">
			<span class="cpi-title-icon dashicons dashicons-upload"></span>
			<?php esc_html_e( 'CSV Post Importer', 'csv-post-importer' ); ?>
		</h1>

		<div class="cpi-steps">
			<div class="cpi-step cpi-step--active">
				<span class="cpi-step__num">1</span>
				<span class="cpi-step__label"><?php esc_html_e( 'Upload CSV', 'csv-post-importer' ); ?></span>
			</div>
			<div class="cpi-step-connector"></div>
			<div class="cpi-step">
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

	<div class="cpi-card">

		<h2 class="cpi-card__title">
			<?php esc_html_e( 'Step 1: Upload your CSV file', 'csv-post-importer' ); ?>
		</h2>

		<p class="cpi-card__desc">
			<?php esc_html_e( 'Upload a CSV file to begin. The first row must contain column headers. UTF-8 encoding is recommended.', 'csv-post-importer' ); ?>
		</p>

		<!-- Upload Zone -->
		<div id="cpi-upload-zone" class="cpi-upload-zone">
			<div class="cpi-upload-zone__icon dashicons dashicons-media-spreadsheet"></div>
			<p class="cpi-upload-zone__text">
				<?php esc_html_e( 'Drag & drop your CSV here, or', 'csv-post-importer' ); ?>
				<label for="cpi-csv-file" class="cpi-upload-zone__browse">
					<?php esc_html_e( 'browse to choose a file', 'csv-post-importer' ); ?>
				</label>
			</p>
			<p class="cpi-upload-zone__hint"><?php esc_html_e( 'Accepted: .csv — Max 10 MB', 'csv-post-importer' ); ?></p>
			<input type="file" id="cpi-csv-file" name="csv_file" accept=".csv,text/csv" class="cpi-upload-zone__input" />
		</div>

		<!-- Upload Progress -->
		<div id="cpi-upload-progress" class="cpi-progress" style="display:none;">
			<div class="cpi-progress__bar">
				<div class="cpi-progress__fill"></div>
			</div>
			<span class="cpi-progress__text"><?php esc_html_e( 'Uploading…', 'csv-post-importer' ); ?></span>
		</div>

		<!-- Upload Error -->
		<div id="cpi-upload-error" class="cpi-notice cpi-notice--error" style="display:none;"></div>

	</div><!-- .cpi-card -->

	<!-- Preview Section (shown after successful upload) -->
	<div id="cpi-preview-section" style="display:none;">

		<div class="cpi-card">
			<div class="cpi-preview-header">
				<div>
					<h2 class="cpi-card__title" id="cpi-preview-filename"></h2>
					<p class="cpi-preview-meta" id="cpi-preview-meta"></p>
				</div>
				<button type="button" id="cpi-btn-reupload" class="button">
					<?php esc_html_e( '← Re-upload', 'csv-post-importer' ); ?>
				</button>
			</div>

			<div class="cpi-table-wrap">
				<table id="cpi-preview-table" class="widefat cpi-preview-table">
					<thead id="cpi-preview-thead"></thead>
					<tbody id="cpi-preview-tbody"></tbody>
				</table>
			</div>

			<div class="cpi-card__footer">
				<button type="button" id="cpi-btn-next" class="button button-primary button-large cpi-btn-next">
					<?php esc_html_e( 'Next: Map Columns →', 'csv-post-importer' ); ?>
				</button>
			</div>
		</div>

	</div><!-- #cpi-preview-section -->

</div><!-- .cpi-wrap -->
