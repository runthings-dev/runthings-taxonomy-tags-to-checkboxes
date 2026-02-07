( function() {
    var taxonomies = window.runthingsTtcEditor && window.runthingsTtcEditor.taxonomies;
    if ( ! taxonomies || ! taxonomies.length ) {
        return;
    }

    wp.domReady( function() {
        taxonomies.forEach( function( taxonomy ) {
            wp.data.dispatch( 'core/editor' ).removeEditorPanel( 'taxonomy-panel-' + taxonomy );
        } );
    } );
} )();
