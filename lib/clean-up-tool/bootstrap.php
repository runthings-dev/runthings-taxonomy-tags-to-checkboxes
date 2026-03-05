<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup_Tool_Bootstrap {
    /**
     * @var Cleanup_Candidate_Scanner
     */
    private $scanner;

    /**
     * @var Cleanup_Term_Deleter
     */
    private $deleter;

    /**
     * @var Cleanup_Notice_Manager
     */
    private $notice_manager;

    /**
     * @var Cleanup_Settings_Panel
     */
    private $settings_panel;

    /**
     * @param Config        $config        Shared plugin config.
     * @param Admin_Options $admin_options Admin options page instance.
     */
    public function __construct( Config $config, Admin_Options $admin_options ) {
        $this->scanner = new Cleanup_Candidate_Scanner();
        $this->deleter = new Cleanup_Term_Deleter();
        $this->notice_manager = new Cleanup_Notice_Manager( $config, $this->scanner );
        $this->settings_panel = new Cleanup_Settings_Panel( $config, $this->scanner, $this->deleter );

        $this->notice_manager->register_hooks();
        $this->settings_panel->register_hooks();

        $admin_options->set_extra_panel_renderer( [ $this->settings_panel, 'render_panel' ] );
    }
}
