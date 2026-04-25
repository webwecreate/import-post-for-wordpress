/**
 * CSV Post Importer — Admin JavaScript
 *
 * Handles:
 *  - CSV drag-and-drop / file-input upload (AJAX)
 *  - Preview table rendering
 *  - Column mapping UI interactions
 *  - Import mode toggle (update / create)
 *  - Category assign mode toggle
 *  - Image mode toggle
 *  - Run Import AJAX call
 *
 * Depends on: jQuery (enqueued by WP), cpiAdmin localised object
 *
 * @package CSV_Post_Importer
 * @since   1.0.0
 */

/* global cpiAdmin, jQuery */
( function( $ ) {
	'use strict';

	/* ------------------------------------------------------------------
	   Cached selectors (populated on DOM ready — only exist on relevant pages)
	   ------------------------------------------------------------------ */
	var $uploadZone, $fileInput, $uploadProgress, $uploadError,
		$previewSection, $previewFilename, $previewMeta, $previewThead, $previewTbody,
		$btnNext, $btnReupload,
		$mappingForm, $btnRunImport, $importProgress, $importError,
		$importModeRadios, $updateModeOptions,
		$uniqueKeyType, $customMetaRow,
		$assignModeRadios, $assignLevelsCustom,
		$imageModeRadios,
		$togglePreview, $previewCollapsed;

	var currentSessionId = '';

	/* ==================================================================
	   DOM READY
	   ================================================================== */
	$( function() {
		initUploadPage();
		initMappingPage();
	} );

	/* ==================================================================
	   UPLOAD PAGE (Step 1)
	   ================================================================== */
	function initUploadPage() {
		$uploadZone     = $( '#cpi-upload-zone' );
		$fileInput      = $( '#cpi-csv-file' );
		$uploadProgress = $( '#cpi-upload-progress' );
		$uploadError    = $( '#cpi-upload-error' );
		$previewSection = $( '#cpi-preview-section' );
		$previewFilename= $( '#cpi-preview-filename' );
		$previewMeta    = $( '#cpi-preview-meta' );
		$previewThead   = $( '#cpi-preview-thead' );
		$previewTbody   = $( '#cpi-preview-tbody' );
		$btnNext        = $( '#cpi-btn-next' );
		$btnReupload    = $( '#cpi-btn-reupload' );

		if ( ! $uploadZone.length ) return; // Not on Step 1.

		// File input change.
		$fileInput.on( 'change', function() {
			var file = this.files && this.files[0];
			if ( file ) uploadFile( file );
		} );

		// Drag and drop.
		$uploadZone
			.on( 'dragover dragenter', function( e ) {
				e.preventDefault();
				$uploadZone.addClass( 'cpi-upload-zone--dragover' );
			} )
			.on( 'dragleave drop', function( e ) {
				e.preventDefault();
				$uploadZone.removeClass( 'cpi-upload-zone--dragover' );
				if ( e.type === 'drop' ) {
					var file = e.originalEvent.dataTransfer &&
					           e.originalEvent.dataTransfer.files[0];
					if ( file ) uploadFile( file );
				}
			} );

		// Next button → navigate to Step 2.
		$btnNext.on( 'click', function() {
			if ( ! currentSessionId ) return;
			window.location.href = addQueryArgs( { session_id: currentSessionId } );
		} );

		// Re-upload → reload Step 1.
		$btnReupload.on( 'click', function() {
			$previewSection.hide();
			$uploadZone.show();
			$uploadError.hide().text( '' );
			$fileInput.val( '' );
			currentSessionId = '';
		} );
	}

	/**
	 * Upload the chosen file via AJAX.
	 *
	 * @param {File} file
	 */
	function uploadFile( file ) {
		// Validate client-side.
		if ( ! file.name.match( /\.csv$/i ) ) {
			showUploadError( cpiAdmin.strings.errorUpload + ' (Only .csv files accepted.)' );
			return;
		}
		if ( file.size > 10 * 1024 * 1024 ) {
			showUploadError( cpiAdmin.strings.errorUpload + ' (File exceeds 10 MB limit.)' );
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'cpi_upload_csv' );
		formData.append( 'nonce', cpiAdmin.uploadNonce );
		formData.append( 'csv_file', file );

		$uploadError.hide().text( '' );
		$uploadZone.hide();
		showProgress( $uploadProgress, cpiAdmin.strings.uploading );

		$.ajax( {
			url: cpiAdmin.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			xhr: function() {
				var xhr = new window.XMLHttpRequest();
				xhr.upload.addEventListener( 'progress', function( e ) {
					if ( e.lengthComputable ) {
						var pct = Math.round( ( e.loaded / e.total ) * 100 );
						$uploadProgress.find( '.cpi-progress__fill' ).css( 'width', pct + '%' );
					}
				} );
				return xhr;
			}
		} )
		.done( function( response ) {
			$uploadProgress.hide();
			if ( response.success ) {
				currentSessionId = response.data.session_id;
				renderPreview( response.data );
				$previewSection.show();
			} else {
				$uploadZone.show();
				showUploadError( response.data && response.data.message
					? response.data.message
					: cpiAdmin.strings.errorUpload );
			}
		} )
		.fail( function() {
			$uploadProgress.hide();
			$uploadZone.show();
			showUploadError( cpiAdmin.strings.errorUpload );
		} );
	}

	/**
	 * Render the CSV preview table after upload.
	 *
	 * @param {Object} data  AJAX success data payload.
	 */
	function renderPreview( data ) {
		$previewFilename.text( data.filename );
		$previewMeta.text(
			data.row_count + ' rows · ' + data.headers.length + ' columns'
		);

		// Build header row.
		var $tr = $( '<tr>' );
		$.each( data.headers, function( i, h ) {
			$tr.append( $( '<th>' ).text( h ) );
		} );
		$previewThead.html( '' ).append( $tr );

		// Build data rows.
		var $tbody = $previewTbody.html( '' );
		$.each( data.rows, function( i, row ) {
			var $row = $( '<tr>' );
			$.each( data.headers, function( j, h ) {
				$row.append( $( '<td>' ).text( row[ h ] || '' ) );
			} );
			$tbody.append( $row );
		} );
	}

	/* ==================================================================
	   MAPPING PAGE (Step 2)
	   ================================================================== */
	function initMappingPage() {
		$mappingForm      = $( '#cpi-mapping-form' );
		$btnRunImport     = $( '#cpi-btn-run-import' );
		$importProgress   = $( '#cpi-import-progress' );
		$importError      = $( '#cpi-import-error' );
		$importModeRadios = $( 'input[name="import_mode"]' );
		$updateModeOptions= $( '#cpi-update-mode-options' );
		$uniqueKeyType    = $( '#unique-key-type' );
		$customMetaRow    = $( '#cpi-custom-meta-row' );
		$assignModeRadios = $( 'input[name="assign_mode"]' );
		$assignLevelsCustom = $( '#cpi-assign-levels-custom' );
		$imageModeRadios  = $( 'input[name="image_mode"]' );
		$togglePreview    = $( '#cpi-toggle-preview' );
		$previewCollapsed = $( '#cpi-preview-collapsed' );

		if ( ! $mappingForm.length ) return; // Not on Step 2.

		initImportModeToggle();
		initUniqueKeyToggle();
		initAssignModeToggle();
		initImageModeToggle();
		initRadioLabelHighlight();
		initPreviewToggle();

		$btnRunImport.on( 'click', runImport );
	}

	/* ------------------------------------------------------------------
	   Import mode toggle
	   ------------------------------------------------------------------ */
	function initImportModeToggle() {
		$importModeRadios.on( 'change', function() {
			if ( $( this ).val() === 'update' ) {
				$updateModeOptions.slideDown( 200 );
			} else {
				$updateModeOptions.slideUp( 200 );
			}
		} );

		// Init state.
		if ( $( 'input[name="import_mode"]:checked' ).val() === 'update' ) {
			$updateModeOptions.show();
		}
	}

	/* ------------------------------------------------------------------
	   Unique key sub-options
	   ------------------------------------------------------------------ */
	function initUniqueKeyToggle() {
		$uniqueKeyType.on( 'change', function() {
			if ( $( this ).val() === 'custom_meta' ) {
				$customMetaRow.show();
			} else {
				$customMetaRow.hide();
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Category assign mode toggle
	   ------------------------------------------------------------------ */
	function initAssignModeToggle() {
		$assignModeRadios.on( 'change', function() {
			if ( $( this ).val() === 'custom' ) {
				$assignLevelsCustom.slideDown( 200 );
			} else {
				$assignLevelsCustom.slideUp( 200 );
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Image mode toggle
	   ------------------------------------------------------------------ */
	function initImageModeToggle() {
		$imageModeRadios.on( 'change', function() {
			var val = $( this ).val();
			$( '#image-mode-filename-detail' ).toggle( val === 'filename' );
			$( '#image-mode-url-detail' ).toggle( val === 'url' );
		} );
	}

	/* ------------------------------------------------------------------
	   Radio label visual highlight (selected state via JS for older browsers)
	   ------------------------------------------------------------------ */
	function initRadioLabelHighlight() {
		$( '.cpi-radio-label' ).each( function() {
			var $label = $( this );
			var $radio = $label.find( 'input[type="radio"]' );

			function update() {
				$label.toggleClass( 'cpi-radio-label--selected', $radio.prop( 'checked' ) );
			}

			$radio.on( 'change', function() {
				// De-select siblings in the same radio group.
				var name = $( this ).attr( 'name' );
				$( 'input[name="' + name + '"]' ).each( function() {
					$( this ).closest( '.cpi-radio-label' ).removeClass( 'cpi-radio-label--selected' );
				} );
				update();
			} );

			update();
		} );
	}

	/* ------------------------------------------------------------------
	   Preview toggle
	   ------------------------------------------------------------------ */
	function initPreviewToggle() {
		if ( ! $togglePreview.length ) return;

		$togglePreview.on( 'click', function() {
			var expanded = $togglePreview.attr( 'aria-expanded' ) === 'true';
			$togglePreview.attr( 'aria-expanded', ! expanded );
			$togglePreview.find( '.cpi-step__label' ).text(
				! expanded
					? cpiAdminI18n( 'hidePreview', 'Hide CSV Preview' )
					: cpiAdminI18n( 'showPreview', 'Show CSV Preview' )
			);
			$previewCollapsed.slideToggle( 200 );
		} );
	}

	/* ------------------------------------------------------------------
	   Run Import
	   ------------------------------------------------------------------ */
	function runImport() {
		// Validate required field.
		var titleCol = $( '#map_post_title' ).val();
		if ( ! titleCol ) {
			showImportError( 'Please map the Post Title column before running the import.' );
			$( '#map_post_title' ).focus();
			return;
		}

		// Validate update mode has a key column.
		if ( $( 'input[name="import_mode"]:checked' ).val() === 'update' ) {
			if ( ! $( '#unique-key-column' ).val() ) {
				showImportError( 'Please select a CSV column for the unique key.' );
				$( '#unique-key-column' ).focus();
				return;
			}
		}

		$importError.hide().text( '' );
		$btnRunImport.prop( 'disabled', true );
		$importProgress.show();

		var formData = $mappingForm.serializeArray();
		formData.push( { name: 'action',   value: 'cpi_run_import' } );
		formData.push( { name: 'nonce',    value: cpiAdmin.importNonce } );

		$.ajax( {
			url: cpiAdmin.ajaxUrl,
			type: 'POST',
			data: $.param( formData )
		} )
		.done( function( response ) {
			$importProgress.hide();
			$btnRunImport.prop( 'disabled', false );

			if ( response.success ) {
				window.location.href = response.data.redirect;
			} else {
				showImportError( response.data && response.data.message
					? response.data.message
					: cpiAdmin.strings.errorImport );
			}
		} )
		.fail( function() {
			$importProgress.hide();
			$btnRunImport.prop( 'disabled', false );
			showImportError( cpiAdmin.strings.errorImport );
		} );
	}

	/* ==================================================================
	   LOGS PAGE
	   ================================================================== */
	$( function() {
		$( '.cpi-btn-clear-logs' ).on( 'click', function() {
			var importId = $( this ).data( 'import-id' ) || '';
			if ( ! window.confirm( cpiAdmin.strings.confirmClear ) ) return;

			var $btn = $( this );
			$btn.prop( 'disabled', true ).text( '…' );

			$.post( cpiAdmin.ajaxUrl, {
				action:    'cpi_clear_logs',
				nonce:     cpiAdmin.clearLogsNonce,
				import_id: importId
			} )
			.done( function( response ) {
				if ( response.success ) {
					if ( importId === 'all' ) {
						$( '.cpi-log-run' ).fadeOut( 300 );
					} else {
						$( '.cpi-log-run[data-import-id="' + importId + '"]' ).fadeOut( 300 );
					}
				}
			} )
			.always( function() {
				$btn.prop( 'disabled', false );
			} );
		} );
	} );

	/* ==================================================================
	   UTILITIES
	   ================================================================== */

	/**
	 * Show an upload error message.
	 *
	 * @param {string} msg
	 */
	function showUploadError( msg ) {
		$uploadError.text( msg ).show();
	}

	/**
	 * Show an import error message.
	 *
	 * @param {string} msg
	 */
	function showImportError( msg ) {
		$importError.text( msg ).show();
	}

	/**
	 * Show and configure a progress element.
	 *
	 * @param {jQuery} $el   Progress wrapper element.
	 * @param {string} label Text label.
	 */
	function showProgress( $el, label ) {
		$el.find( '.cpi-progress__text' ).text( label );
		$el.find( '.cpi-progress__fill' ).css( 'width', '0%' );
		$el.show();
	}

	/**
	 * Add query args to the current URL.
	 *
	 * @param  {Object} args Key-value pairs to merge.
	 * @return {string}      New URL string.
	 */
	function addQueryArgs( args ) {
		var url    = window.location.href.split( '?' )[0];
		var params = new URLSearchParams( window.location.search );
		$.each( args, function( key, val ) {
			params.set( key, val );
		} );
		return url + '?' + params.toString();
	}

	/**
	 * Lightweight i18n helper — returns string from cpiAdmin.strings if available.
	 *
	 * @param  {string} key      Key name.
	 * @param  {string} fallback Fallback string.
	 * @return {string}
	 */
	function cpiAdminI18n( key, fallback ) {
		return ( cpiAdmin.strings && cpiAdmin.strings[ key ] ) ? cpiAdmin.strings[ key ] : fallback;
	}

}( jQuery ) );
