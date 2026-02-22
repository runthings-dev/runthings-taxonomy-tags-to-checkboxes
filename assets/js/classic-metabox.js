( function ( $ ) {
	'use strict';

	function normalizeTermName( value ) {
		return ( value || '' ).trim().toLocaleLowerCase();
	}

	function getLabelText( $item ) {
		const text = $item.find( '> label, > .selectit' ).first().text();
		return ( text || '' ).trim().toLowerCase();
	}

	function getSearchConfig( checklist ) {
		const mode =
			checklist.getAttribute( 'data-runthings-ttc-search-mode' ) || 'off';
		const threshold = Number.parseInt(
			checklist.getAttribute( 'data-runthings-ttc-search-threshold' ) ||
				'20',
			10
		);

		return {
			mode,
			threshold:
				Number.isInteger( threshold ) && threshold > 0 ? threshold : 20,
		};
	}

	function shouldEnableSearch( checklist ) {
		const config = getSearchConfig( checklist );
		if ( config.mode === 'always' ) {
			return true;
		}

		if ( config.mode === 'min_terms' ) {
			return (
				checklist.querySelectorAll( ':scope > li' ).length >=
				config.threshold
			);
		}

		return false;
	}

	function isSearchInitialized( checklist ) {
		return (
			checklist.getAttribute(
				'data-runthings-ttc-search-initialized'
			) === '1'
		);
	}

	function markSearchInitialized( checklist ) {
		checklist.setAttribute( 'data-runthings-ttc-search-initialized', '1' );
	}

	function filterChecklist( checklist, query, emptyStateEl ) {
		const normalizedQuery = normalizeTermName( query );
		const items = checklist.querySelectorAll( ':scope > li' );
		let visibleCount = 0;

		items.forEach( ( item ) => {
			const itemText = normalizeTermName( item.textContent );
			const isVisible =
				! normalizedQuery || itemText.includes( normalizedQuery );
			item.style.display = isVisible ? '' : 'none';
			if ( isVisible ) {
				visibleCount += 1;
			}
		} );

		if ( emptyStateEl ) {
			emptyStateEl.style.display = visibleCount > 0 ? 'none' : '';
		}
	}

	function setupChecklistSearch( checklist ) {
		if (
			isSearchInitialized( checklist ) ||
			! shouldEnableSearch( checklist )
		) {
			return;
		}
		markSearchInitialized( checklist );
		const labels = window.runthingsTtcClassic || {};

		const taxonomyDiv = checklist.closest( '.categorydiv' );
		const listContainer = checklist.closest( '.taxonomies-container' );
		if ( ! taxonomyDiv || ! listContainer ) {
			return;
		}

		const searchWrap = document.createElement( 'div' );
		searchWrap.className = 'runthings-ttc-search-wrap';

		const searchLabel = document.createElement( 'label' );
		searchLabel.className = 'screen-reader-text';
		searchLabel.setAttribute( 'for', checklist.id + '-search' );
		searchLabel.textContent = labels.searchLabel || 'Search terms';

		const searchInput = document.createElement( 'input' );
		searchInput.type = 'search';
		searchInput.id = checklist.id + '-search';
		searchInput.className = 'runthings-ttc-search-input';
		searchInput.placeholder = labels.searchPlaceholder || 'Search terms...';

		searchWrap.appendChild( searchLabel );
		searchWrap.appendChild( searchInput );

		const noMatches = document.createElement( 'p' );
		noMatches.className = 'runthings-ttc-no-matching';
		noMatches.textContent = labels.noMatchingTerms || 'No matching terms.';
		noMatches.style.display = 'none';
		noMatches.setAttribute( 'aria-live', 'polite' );

		listContainer.parentNode.insertBefore( searchWrap, listContainer );
		listContainer.appendChild( noMatches );

		searchInput.addEventListener( 'input', () => {
			filterChecklist( checklist, searchInput.value, noMatches );
		} );

		const searchObserver = new MutationObserver( ( mutations ) => {
			if (
				mutations.some( ( mutation ) => mutation.addedNodes.length > 0 )
			) {
				filterChecklist( checklist, searchInput.value, noMatches );
			}
		} );
		searchObserver.observe( checklist, { childList: true } );
	}

	function observeMinTermsThreshold( checklist ) {
		const config = getSearchConfig( checklist );
		if ( config.mode !== 'min_terms' || isSearchInitialized( checklist ) ) {
			return;
		}

		const thresholdObserver = new MutationObserver( ( mutations ) => {
			if (
				mutations.some( ( mutation ) => mutation.addedNodes.length > 0 )
			) {
				if ( shouldEnableSearch( checklist ) ) {
					setupChecklistSearch( checklist );
					thresholdObserver.disconnect();
				}
			}
		} );

		thresholdObserver.observe( checklist, { childList: true } );
	}

	function getSearchChecklists() {
		return document.querySelectorAll(
			'ul[data-runthings-ttc-search-mode]'
		);
	}

	function initChecklistSearch( checklist ) {
		setupChecklistSearch( checklist );
		observeMinTermsThreshold( checklist );
	}

	function sortChecklist( checklist ) {
		const $checklist = $( checklist );
		if ( ! $checklist.length ) {
			return;
		}

		const $items = $checklist.children( 'li' );
		if ( $items.length < 2 ) {
			return;
		}

		$items.sort( function ( a, b ) {
			return getLabelText( $( a ) ).localeCompare(
				getLabelText( $( b ) )
			);
		} );

		$checklist.append( $items );
	}

	function observeChecklist( checklist ) {
		const observer = new MutationObserver( function ( mutations ) {
			if (
				mutations.some( ( mutation ) => mutation.addedNodes.length > 0 )
			) {
				observer.disconnect();
				sortChecklist( checklist );
				observer.observe( checklist, { childList: true } );
			}
		} );

		observer.observe( checklist, { childList: true } );
	}

	function getSortableChecklists() {
		return document.querySelectorAll(
			'ul[data-runthings-ttc-sortable="1"]'
		);
	}

	function getTaxonomyFromSubmitButton( button ) {
		if ( ! button || ! button.id ) {
			return null;
		}

		const match = button.id.match( /^(.*)-add-submit$/ );
		return match ? match[ 1 ] : null;
	}

	function getSortableChecklistByTaxonomy( taxonomy ) {
		if ( ! taxonomy ) {
			return null;
		}

		const checklist = document.getElementById( taxonomy + 'checklist' );
		if (
			! checklist ||
			checklist.getAttribute( 'data-runthings-ttc-sortable' ) !== '1'
		) {
			return null;
		}

		return checklist;
	}

	function findExistingTermCheckbox( checklist, termName ) {
		const normalizedTarget = normalizeTermName( termName );
		if ( ! normalizedTarget ) {
			return null;
		}

		const checkboxes = checklist.querySelectorAll(
			'input[type="checkbox"]'
		);
		for ( const checkbox of checkboxes ) {
			const label = checkbox.closest( 'label' );
			const labelText = normalizeTermName(
				label ? label.textContent : ''
			);
			if ( labelText === normalizedTarget ) {
				return checkbox;
			}
		}

		return null;
	}

	function handleDuplicateInlineAdd( submitButton ) {
		const taxonomy = getTaxonomyFromSubmitButton( submitButton );
		const checklist = getSortableChecklistByTaxonomy( taxonomy );
		if ( ! checklist ) {
			return false;
		}

		const newTermInput = document.getElementById( 'new' + taxonomy );
		if ( ! newTermInput ) {
			return false;
		}

		const existingCheckbox = findExistingTermCheckbox(
			checklist,
			newTermInput.value
		);
		if ( ! existingCheckbox ) {
			return false;
		}

		existingCheckbox.checked = true;
		existingCheckbox.dispatchEvent(
			new Event( 'change', { bubbles: true } )
		);
		newTermInput.value = '';
		return true;
	}

	function bindDuplicateSelectionShortCircuit() {
		document.addEventListener(
			'click',
			function ( event ) {
				const submitButton = event.target.closest(
					'input.category-add-submit'
				);
				if ( ! submitButton ) {
					return;
				}

				if ( handleDuplicateInlineAdd( submitButton ) ) {
					event.preventDefault();
					event.stopPropagation();
					event.stopImmediatePropagation();
				}
			},
			true
		);
	}

	$( function () {
		bindDuplicateSelectionShortCircuit();
		getSearchChecklists().forEach( initChecklistSearch );
		getSortableChecklists().forEach( observeChecklist );
	} );
} )( jQuery );
