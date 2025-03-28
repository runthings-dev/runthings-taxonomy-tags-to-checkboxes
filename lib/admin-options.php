<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Options {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'Taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ),
            __( 'Taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ),
            'manage_options',
            'runthings-taxonomy-options',
            [ $this, 'render_options_page' ]
        );
    }

    public function render_options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'runthings_taxonomy_options_group' );
                do_settings_sections( 'runthings-taxonomy-options' );
                submit_button( __( 'Save Changes', 'runthings-taxonomy-tags-to-checkboxes' ) );
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting(
            'runthings_taxonomy_options_group',
            'runthings_ttc_selected_taxonomies'
        );

        add_settings_section(
            'runthings_taxonomy_section',
            __( 'Taxonomy Settings', 'runthings-taxonomy-tags-to-checkboxes' ),
            '',
            'runthings-taxonomy-options'
        );

        add_settings_field(
            'runthings_ttc_selected_taxonomies',
            __( 'Select Taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ),
            [ $this, 'render_taxonomy_checkboxes' ],
            'runthings-taxonomy-options',
            'runthings_taxonomy_section'
        );
    }

    public function render_taxonomy_checkboxes() {
        $selected_taxonomies = get_option( 'runthings_ttc_selected_taxonomies', [] );
        $taxonomies          = get_taxonomies( [], 'objects' );

        echo '<ul style="list-style: none; margin: 0; padding: 0;">';
        foreach ( $taxonomies as $taxonomy ) {
            $checked = in_array( $taxonomy->name, $selected_taxonomies, true ) ? 'checked' : '';
            $post_types = implode( ', ', $taxonomy->object_type );
            echo '<li>
                    <label>
                        <input type="checkbox" name="runthings_ttc_selected_taxonomies[]" value="' . esc_attr( $taxonomy->name ) . '" ' . $checked . '>
                        ' . esc_html( $taxonomy->label ) . ' (' . esc_html( $post_types ) . ')
                    </label>
                  </li>';
        }
        echo '</ul>';
    }
}