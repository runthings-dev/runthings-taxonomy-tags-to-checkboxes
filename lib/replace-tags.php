<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Replace_Tags {
    /**
     * @var array Selected taxonomies to convert.
     */
    private $selected_taxonomies = [];

    public function __construct() {
        // Add front-end styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_metabox_styles']);

        $this->selected_taxonomies = get_option( 'runthings_ttc_selected_taxonomies', [] );

        $this->selected_taxonomies = apply_filters( 'runthings_ttc_selected_taxonomies', $this->selected_taxonomies );

        // Remove the default Gutenberg taxonomy panel for selected taxonomies
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

        foreach ( $this->selected_taxonomies as $taxonomy ) {
            add_action( 'add_meta_boxes', function ( $post_type, $post ) use ( $taxonomy ) {
                $this->remove_default_taxonomy_metabox( $post_type, $post, $taxonomy );
            }, 10, 2 );

            add_action( 'add_meta_boxes', function ( $post_type ) use ( $taxonomy ) {
                $this->add_taxonomy_metabox( $post_type, $taxonomy );
            });

            add_action( 'save_post', function ( $post_id ) use ( $taxonomy ) {
                $this->save_taxonomy_metabox( $post_id, $taxonomy );
            });
        }
    }

    /**
     * Enqueue block editor assets to remove default taxonomy panels in Gutenberg
     */
    public function enqueue_block_editor_assets() {
        if ( empty( $this->selected_taxonomies ) ) {
            return;
        }

        wp_enqueue_script(
            'runthings-ttc-editor',
            RUNTHINGS_TTC_URL . 'assets/js/editor.js',
            [ 'wp-data', 'wp-dom-ready', 'wp-editor' ],
            RUNTHINGS_TTC_VERSION,
            true
        );

        wp_localize_script(
            'runthings-ttc-editor',
            'runthingsTtcEditor',
            [
                'taxonomies' => array_values( $this->selected_taxonomies ),
            ]
        );
    }
    
    /**
     * Enqueue styles for the taxonomy metabox
     */
    public function enqueue_metabox_styles() {
        // Register an empty stylesheet
        wp_register_style(
            'runthings-ttc-metabox',
            false,
            [],
            RUNTHINGS_TTC_VERSION
        );
        
        wp_enqueue_style('runthings-ttc-metabox');
        
        // Add styles inline
        wp_add_inline_style('runthings-ttc-metabox', '
            .taxonomies-container {
                overflow-y: auto;
                padding: 0 0.9em;
                border: 1px solid #ccc;
                margin-top: 1em;
            }
            .taxonomy-edit-link {
                margin-top: 1em;
                }
            .taxonomy-edit-link a {
                font-weight: 600;
            }
        ');
    }

    /**
     * Remove the default taxonomy metabox
     *
     * @param string $post_type The post type
     * @param WP_Post $post The current post object
     * @param string $taxonomy The taxonomy name
     */
    public function remove_default_taxonomy_metabox( $post_type, $post, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            remove_meta_box( 'tagsdiv-' . $taxonomy, $post_type, 'side' );
        }
    }

    /**
     * Add a custom taxonomy metabox with checkboxes
     *
     * @param string $post_type The post type
     * @param string $taxonomy The taxonomy name
     */
    public function add_taxonomy_metabox( $post_type, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            return;
        }

        add_meta_box(
            'checkbox-' . $taxonomy . '-metabox',
            $taxonomy_object->label,
            function ( $post ) use ( $taxonomy ) {
                $this->render_taxonomy_metabox( $post, $taxonomy );
            },
            $post_type,
            'side',
            'default'
        );
    }

    /**
     * Render the taxonomy metabox with checkboxes
     *
     * @param WP_Post $post The current post object
     * @param string $taxonomy The taxonomy name
     */
    public function render_taxonomy_metabox( $post, $taxonomy ) {
        $terms = get_terms( [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ] );

        $post_terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        
        // Get style for the taxonomy container
        $style = $this->get_taxonomy_container_style($taxonomy);

        // Check if we should show the edit link
        $show_links = get_option('runthings_ttc_show_links', []);

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            echo '<div class="taxonomies-container" style="' . esc_attr($style) . '"><ul>';
            foreach ( $terms as $term ) {
                $checked = in_array( $term->term_id, $post_terms, true ) ? 'checked' : '';
                echo '<li><label><input type="checkbox" name="checkbox_' . esc_attr( $taxonomy ) . '[]" value="' . esc_attr( $term->term_id ) . '" ' . esc_attr($checked) . '> ' . esc_html( $term->name ) . '</label></li>';
            }
            echo '</ul></div>';
        } else {
            echo esc_html__( 'No terms available.', 'runthings-taxonomy-tags-to-checkboxes' );
        }
        
        // Output the edit taxonomy link
        $this->maybe_output_edit_taxonomy_link($taxonomy, $show_links);
        
        wp_nonce_field( 'checkbox_' . $taxonomy . '_nonce_action', 'checkbox_' . $taxonomy . '_nonce' );
    }
    
    /**
     * Generate the CSS style for a taxonomy container based on its height settings
     *
     * @param string $taxonomy The taxonomy name
     * @return string CSS style string
     */
    private function get_taxonomy_container_style($taxonomy) {
        $style = '';
        
        // Get height settings
        $height_settings = get_option('runthings_ttc_height_settings', []);
        $height_type     = isset($height_settings[$taxonomy]['type']) ? $height_settings[$taxonomy]['type'] : '';
        
        // Apply height setting based on configuration
        switch ($height_type) {
            case 'auto':
                $style .= 'max-height: auto;';
                break;
                
            case 'full':
                $style .= 'max-height: none;';
                break;
                
            case 'custom':
                if (isset($height_settings[$taxonomy]['value'])) {
                    $custom_height = absint($height_settings[$taxonomy]['value']);
                    $style .= 'max-height: ' . $custom_height . 'px;';
                } else {
                    $style .= 'max-height: auto;';
                }
                break;
                
            default:
                $style .= 'max-height: auto;';
                break;
        }
        
        return $style;
    }

    /**
     * Outputs the "Add / Edit Taxonomy" link if enabled and user has permissions
     *
     * @param string $taxonomy The taxonomy name
     * @param array $show_links Array of taxonomies where the link should be shown
     */
    private function maybe_output_edit_taxonomy_link($taxonomy, $show_links) {
        if (is_array($show_links) && in_array($taxonomy, $show_links, true)) {
            $taxonomy_object = get_taxonomy($taxonomy);
            if ($taxonomy_object && current_user_can($taxonomy_object->cap->manage_terms)) {
                $edit_link = admin_url('edit-tags.php?taxonomy=' . $taxonomy);
                echo '<div class="taxonomy-edit-link">';
                echo '<a href="' . esc_url($edit_link) . '" target="_blank">';
                echo wp_kses(
                    sprintf(
                        /* translators: %s: Taxonomy label */
                        __('+ Add / Edit %s', 'runthings-taxonomy-tags-to-checkboxes'),
                        esc_html($taxonomy_object->labels->name)
                    ),
                    array( 'b' => array(), 'strong' => array() )
                );
                echo '</a></div>';
            }
        }
    }

    /**
     * Save the selected terms when the post is saved
     *
     * @param int $post_id The post ID
     * @param string $taxonomy The taxonomy name
     */
    public function save_taxonomy_metabox( $post_id, $taxonomy ) {
        $nonce_field  = 'checkbox_' . $taxonomy . '_nonce';
        $nonce_action = 'checkbox_' . $taxonomy . '_nonce_action';
        
        if ( ! isset( $_POST[$nonce_field] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[$nonce_field] ) ), $nonce_action ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $term_ids = isset( $_POST['checkbox_' . $taxonomy] ) ? array_map( 'intval', wp_unslash( $_POST['checkbox_' . $taxonomy] ) ) : [];
        wp_set_post_terms( $post_id, $term_ids, $taxonomy );
    }
}

