/**
 * Gutenberg editor integration for Taxonomy Tags to Checkboxes.
 *
 * Removes the default taxonomy panels and registers native
 * PluginDocumentSettingPanel replacements with checkbox UI.
 */
import { registerPlugin } from '@wordpress/plugins';
import domReady from '@wordpress/dom-ready';
import { dispatch, select, subscribe } from '@wordpress/data';
import TtcTaxonomyPanel from './components/TtcTaxonomyPanel';

const config = window.runthingsTtcEditor || {};
const taxonomies = config.taxonomies || [];
const toSafeId = ( slug ) => slug.replace( /_/g, '-' );

const removeDefaultTaxonomyPanels = () => {
	const currentPostType = select( 'core/editor' ).getCurrentPostType();
	if ( ! currentPostType ) {
		return false;
	}

	taxonomies.forEach( ( tax ) => {
		if ( ! tax.postTypes.includes( currentPostType ) ) {
			return;
		}

		dispatch( 'core/editor' ).removeEditorPanel(
			'taxonomy-panel-' + tax.slug
		);
	} );

	return true;
};

// Remove the default Gutenberg taxonomy panels.
domReady( () => {
	if ( removeDefaultTaxonomyPanels() ) {
		return;
	}

	// Re-run after editor state settles to prevent duplicate core panels.
	const unsubscribe = subscribe( () => {
		if ( removeDefaultTaxonomyPanels() ) {
			unsubscribe();
		}
	} );
} );

// Register a sidebar panel per taxonomy.
taxonomies.forEach( ( tax ) => {
	registerPlugin( 'runthings-ttc-' + toSafeId( tax.slug ), {
		render: () => <TtcTaxonomyPanel taxonomy={ tax } />,
	} );
} );
