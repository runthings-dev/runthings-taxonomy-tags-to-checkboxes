<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Block_Integration {
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config Shared taxonomy UI config.
     */
    public function __construct( Config $config ) {
        $this->config = $config;
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
    }

    /**
     * Enqueue block editor assets and inject taxonomy panel config.
     */
    public function enqueue_block_editor_assets() {
        if ( empty( $this->config->get_selected_taxonomies() ) ) {
            return;
        }

        $asset_file = RUNTHINGS_TTC_DIR . 'build/index.asset.php';
        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            'runthings-ttc-editor',
            RUNTHINGS_TTC_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_add_inline_script(
            'runthings-ttc-editor',
            'window.runthingsTtcEditor = ' . wp_json_encode( [ 'taxonomies' => $this->config->get_block_taxonomy_configs() ] ) . ';',
            'before'
        );
    }
}
