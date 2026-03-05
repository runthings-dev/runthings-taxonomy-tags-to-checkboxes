<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap {
    public function __construct() {
        $config = new Config();
        $admin_options = new Admin_Options();

        new Classic_Integration( $config );
        new Block_Integration( $config );
        new Cleanup_Tool_Bootstrap( $config, $admin_options );
    }
}
