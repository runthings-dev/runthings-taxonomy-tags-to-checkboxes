( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Table sorting functionality
		$( '.sort-column' ).on( 'click', function ( e ) {
			e.preventDefault();

			const $this = $( this );
			const column = $this.data( 'column' );
			const $table = $( '#taxonomy-table' );
			const $rows = $table.find( 'tbody tr' ).toArray();
			const ascending = $this.closest( 'th' ).hasClass( 'desc' );

			// Update sortable classes
			$table
				.find( 'thead th, tfoot th' )
				.removeClass( 'sorted asc desc' );

			// Update the current column's sorting state
			const $currentHeader = $this.closest( 'th' );
			$currentHeader.addClass( 'sorted' );

			if ( ascending ) {
				$currentHeader.removeClass( 'desc' ).addClass( 'asc' );
			} else {
				$currentHeader.removeClass( 'asc' ).addClass( 'desc' );
			}

			// Sort the rows
			$rows.sort( function ( a, b ) {
				let valA, valB;

				if ( column === 'name' ) {
					valA = $( a ).data( 'name' );
					valB = $( b ).data( 'name' );
				} else if ( column === 'post_types' ) {
					valA = $( a ).data( 'post-types' );
					valB = $( b ).data( 'post-types' );
				} else if ( column === 'type' ) {
					valA = $( a ).data( 'type' );
					valB = $( b ).data( 'type' );
				}

				if ( valA < valB ) {
					return ascending ? -1 : 1;
				}
				if ( valA > valB ) {
					return ascending ? 1 : -1;
				}
				return 0;
			} );

			// Append sorted rows to table
			$.each( $rows, function ( index, row ) {
				$table.find( 'tbody' ).append( row );
			} );

			return false;
		} );

		// System taxonomy toggle
		$( '#show-system-taxonomies' ).on( 'change', function () {
			if ( $( this ).is( ':checked' ) ) {
				$( '.system-taxonomy' ).show();
			} else {
				$( '.system-taxonomy' ).hide();
			}

			updateNoItemsVisibility();
		} );

		// Height type selection change handler
		$( '.height-type-select' ).on( 'change', function () {
			const $this = $( this );
			const $row = $this.closest( 'tr' );
			const $customField = $row.find( '.custom-height-input' );

			if ( $this.val() === 'custom' ) {
				$customField.show();
			} else {
				$customField.hide();
			}
		} );

		// Search mode selection change handler
		$( '.search-mode-select' ).on( 'change', function () {
			const $this = $( this );
			const $row = $this.closest( 'tr' );
			const $minTermsField = $row.find( '.min-terms-input' );

			if ( $this.val() === 'min_terms' ) {
				$minTermsField.show();
			} else {
				$minTermsField.hide();
			}
		} );

		// Taxonomy checkbox change handler
		$( "input[name='runthings_ttc_selected_taxonomies[]']" ).on(
			'change',
			function () {
				const $this = $( this );
				const $row = $this.closest( 'tr' );
				const $heightSelect = $row.find( '.height-type-select' );
				const $customHeight = $row.find( '.custom-height-input input' );
				const $showLinkCheckbox = $row.find(
					"input[name='runthings_ttc_show_links[]']"
				);
				const $allowCreateCheckbox = $row.find(
					"input[name='runthings_ttc_allow_term_create[]']"
				);
				const $searchModeSelect = $row.find( '.search-mode-select' );
				const $searchThreshold = $row.find( '.min-terms-input input' );

				if ( $this.is( ':checked' ) && ! $this.is( ':disabled' ) ) {
					// Enable height controls and show link checkbox
					$heightSelect.prop( 'disabled', false );
					$customHeight.prop( 'disabled', false );
					$showLinkCheckbox.prop( 'disabled', false );
					$allowCreateCheckbox.prop( 'disabled', false );
					$searchModeSelect.prop( 'disabled', false );
					$searchThreshold.prop( 'disabled', false );
				} else {
					// Disable height controls and show link checkbox
					$heightSelect.prop( 'disabled', true );
					$customHeight.prop( 'disabled', true );
					$showLinkCheckbox.prop( 'disabled', true );
					$allowCreateCheckbox.prop( 'disabled', true );
					$searchModeSelect.prop( 'disabled', true );
					$searchThreshold.prop( 'disabled', true );
				}
			}
		);

		// Function to update the no-items message visibility
		function updateNoItemsVisibility() {
			const systemVisible = $( '#show-system-taxonomies' ).is(
				':checked'
			);

			const visibleRows = $( '#taxonomy-table tbody tr:visible' ).not(
				'.no-items'
			).length;

			if ( visibleRows === 0 ) {
				// Show the no items message
				$( '.no-items' ).show();

				// If there are system taxonomies but they're hidden, show the hint
				if ( ! systemVisible && taxonomyStats.systemCount > 0 ) {
					$( '.hidden-system-message' ).show();
				} else {
					$( '.hidden-system-message' ).hide();
				}
			} else {
				// Hide the no items message when there are visible rows
				$( '.no-items' ).hide();
			}
		}

		// Initialize height fields on load
		$( '.height-type-select' ).each( function () {
			$( this ).trigger( 'change' );
		} );
		$( '.search-mode-select' ).each( function () {
			$( this ).trigger( 'change' );
		} );

		// Initialize enabled/disabled state on load
		$( "input[name='runthings_ttc_selected_taxonomies[]']" ).each(
			function () {
				$( this ).trigger( 'change' );
			}
		);

		// Initial setup - hide system taxonomies
		$( '#show-system-taxonomies' )
			.prop( 'checked', false )
			.trigger( 'change' );

		// Initial update of no-items visibility
		updateNoItemsVisibility();
	} );
} )( jQuery );
