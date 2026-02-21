( function ( $ ) {
	'use strict';

	function normalizeTermName( value ) {
		return ( value || '' ).trim().toLocaleLowerCase();
	}

	function getLabelText( $item ) {
		const text = $item.find( '> label, > .selectit' ).first().text();
		return ( text || '' ).trim().toLowerCase();
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
			return getLabelText( $( a ) ).localeCompare( getLabelText( $( b ) ) );
		} );

		$checklist.append( $items );
	}

	function observeChecklist( checklist ) {
		const observer = new MutationObserver( function ( mutations ) {
			if ( mutations.some( ( mutation ) => mutation.addedNodes.length > 0 ) ) {
				observer.disconnect();
				sortChecklist( checklist );
				observer.observe( checklist, { childList: true } );
			}
		} );

		observer.observe( checklist, { childList: true } );
	}

	function getSortableChecklists() {
		return document.querySelectorAll( 'ul[data-runthings-ttc-sortable="1"]' );
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

		const checkboxes = checklist.querySelectorAll( 'input[type="checkbox"]' );
		for ( const checkbox of checkboxes ) {
			const label = checkbox.closest( 'label' );
			const labelText = normalizeTermName( label ? label.textContent : '' );
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
		existingCheckbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
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
		getSortableChecklists().forEach( observeChecklist );
	} );
} )( jQuery );
