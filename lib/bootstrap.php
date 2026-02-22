<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap {
    public function __construct() {
        $config = new Config();
        new Classic_Integration( $config );
        new Block_Integration( $config );
    }
}
