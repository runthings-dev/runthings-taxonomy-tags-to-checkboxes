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
    /**
     * @var array Taxonomies where inline term creation is enabled.
     */
    private $allow_term_create = [];
    /**
     * @var array Taxonomies where edit link is enabled.
     */
    private $show_edit_links = [];

    public function __construct() {
        // Add front-end styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_metabox_styles']);

        $this->selected_taxonomies = get_option( 'runthings_ttc_selected_taxonomies', [] );
        if ( ! is_array( $this->selected_taxonomies ) ) {
            $this->selected_taxonomies = [];
        }

        $this->selected_taxonomies = apply_filters( 'runthings_ttc_selected_taxonomies', $this->selected_taxonomies );
        if ( ! is_array( $this->selected_taxonomies ) ) {
            $this->selected_taxonomies = [];
        }

        $this->allow_term_create = get_option( 'runthings_ttc_allow_term_create', [] );
        if ( ! is_array( $this->allow_term_create ) ) {
            $this->allow_term_create = [];
        }

        $this->show_edit_links = get_option( 'runthings_ttc_show_links', [] );
        if ( ! is_array( $this->show_edit_links ) ) {
            $this->show_edit_links = [];
        }

        add_action( 'admin_init', [ $this, 'register_inline_create_ajax_hooks' ] );

        // Remove the default Gutenberg taxonomy panel for selected taxonomies
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

        foreach ( $this->selected_taxonomies as $taxonomy ) {
            add_action( 'add_meta_boxes', function ( $post_type, $post ) use ( $taxonomy ) {
                $this->remove_default_taxonomy_metabox( $post_type, $post, $taxonomy );
                if ( $this->should_use_classic_metabox( $post_type, $post ) ) {
                    $this->add_taxonomy_metabox( $post_type, $taxonomy );
                }
            }, 10, 2 );

            add_action( 'save_post', function ( $post_id ) use ( $taxonomy ) {
                $this->save_taxonomy_metabox( $post_id, $taxonomy );
            });
        }
    }

    /**
     * Register per-taxonomy add-term AJAX hooks when inline create is enabled.
     */
    public function register_inline_create_ajax_hooks() {
        if ( ! function_exists( '_wp_ajax_add_hierarchical_term' ) ) {
            return;
        }

        foreach ( $this->allow_term_create as $taxonomy ) {
            if ( ! in_array( $taxonomy, $this->selected_taxonomies, true ) ) {
                continue;
            }

            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object ) {
                continue;
            }

            add_action( 'wp_ajax_add-' . $taxonomy, '_wp_ajax_add_hierarchical_term' );
        }
    }

    /**
     * Enqueue block editor assets — native Gutenberg sidebar panels
     */
    public function enqueue_block_editor_assets() {
        if ( empty( $this->selected_taxonomies ) ) {
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

        $height_settings = get_option( 'runthings_ttc_height_settings', [] );
        $show_links      = $this->show_edit_links;
        $allow_create    = $this->allow_term_create;

        $taxonomy_configs = [];
        foreach ( $this->selected_taxonomies as $taxonomy ) {
            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object || ! $taxonomy_object->show_in_rest ) {
                continue;
            }

            $height_type = isset( $height_settings[ $taxonomy ]['type'] ) ? $height_settings[ $taxonomy ]['type'] : 'auto';
            $max_height  = 'auto';
            if ( 'full' === $height_type ) {
                $max_height = 'none';
            } elseif ( 'custom' === $height_type && isset( $height_settings[ $taxonomy ]['value'] ) ) {
                $max_height = absint( $height_settings[ $taxonomy ]['value'] ) . 'px';
            }

            $show_edit_link = is_array( $show_links ) && in_array( $taxonomy, $show_links, true );
            $allow_inline_add = is_array( $allow_create ) && in_array( $taxonomy, $allow_create, true );
            $can_create_terms = $allow_inline_add && current_user_can( $taxonomy_object->cap->edit_terms );
            $edit_url       = '';
            if ( $show_edit_link && current_user_can( $taxonomy_object->cap->manage_terms ) ) {
                $edit_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
            }

            $taxonomy_configs[] = [
                'slug'         => $taxonomy,
                'restBase'     => $taxonomy_object->rest_base ?: $taxonomy,
                'label'        => $taxonomy_object->label,
                'postTypes'    => array_values( $taxonomy_object->object_type ),
                'maxHeight'    => $max_height,
                'showEditLink' => $show_edit_link && ! empty( $edit_url ),
                'editUrl'      => $edit_url,
                'allowInlineAdd' => $allow_inline_add,
                'canCreateTerms' => $can_create_terms,
                'editLinkLabel' => $this->get_edit_link_label( $taxonomy_object, $can_create_terms ),
            ];
        }

        wp_add_inline_script(
            'runthings-ttc-editor',
            'window.runthingsTtcEditor = ' . wp_json_encode( [ 'taxonomies' => $taxonomy_configs ] ) . ';',
            'before'
        );
    }
    
    /**
     * Enqueue styles for the taxonomy metabox
     */
    public function enqueue_metabox_styles( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_enqueue_style(
            'runthings-ttc-classic-metabox',
            RUNTHINGS_TTC_URL . 'assets/css/classic-metabox.css',
            [ 'dashicons' ],
            RUNTHINGS_TTC_VERSION
        );

        $inline_add_taxonomies = array_values(
            array_intersect( $this->selected_taxonomies, $this->allow_term_create )
        );
        if ( empty( $inline_add_taxonomies ) ) {
            return;
        }

        wp_enqueue_script(
            'runthings-ttc-classic-metabox',
            RUNTHINGS_TTC_URL . 'assets/js/classic-metabox.js',
            [ 'jquery' ],
            RUNTHINGS_TTC_VERSION,
            true
        );
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
        if ( ! $taxonomy_object ) {
            return;
        }
        if ( in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            remove_meta_box( 'tagsdiv-' . $taxonomy, $post_type, 'side' );
        }
    }

    /**
     * Determines whether the custom classic metabox should be used.
     *
     * @param string   $post_type The post type.
     * @param \WP_Post $post      The current post object.
     * @return bool
     */
    private function should_use_classic_metabox( $post_type, $post ) {
        if ( function_exists( 'use_block_editor_for_post' ) && $post instanceof \WP_Post ) {
            return ! use_block_editor_for_post( $post );
        }

        if ( function_exists( 'use_block_editor_for_post_type' ) ) {
            return ! use_block_editor_for_post_type( $post_type );
        }

        return true;
    }

    /**
     * Add a custom taxonomy metabox with checkboxes
     *
     * @param string $post_type The post type
     * @param string $taxonomy The taxonomy name
     */
    public function add_taxonomy_metabox( $post_type, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object || ! in_array( $post_type, $taxonomy_object->object_type, true ) ) {
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
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object ) {
            return;
        }

        $style = $this->get_taxonomy_container_style( $taxonomy );
        $allow_inline_add = $this->is_inline_add_enabled( $taxonomy, $taxonomy_object );

        echo '<div id="taxonomy-' . esc_attr( $taxonomy ) . '" class="categorydiv">';
        echo '<div class="taxonomies-container tabs-panel" id="' . esc_attr( $taxonomy ) . '-all" style="' . esc_attr( $style ) . '">';

        if ( 'category' === $taxonomy ) {
            echo "<input type='hidden' name='post_category[]' value='0' />";
        } else {
            echo "<input type='hidden' name='tax_input[" . esc_attr( $taxonomy ) . "][]' value='0' />";
        }

        $sortable_attr = $allow_inline_add ? ' data-runthings-ttc-sortable="1"' : '';
        echo '<ul id="' . esc_attr( $taxonomy ) . 'checklist" data-wp-lists="list:' . esc_attr( $taxonomy ) . '"' . $sortable_attr . ' class="categorychecklist form-no-clear">';
        wp_terms_checklist(
            $post->ID,
            [
                'taxonomy' => $taxonomy,
            ]
        );
        echo '</ul>';
        echo '</div>';

        $this->maybe_output_add_term_controls( $taxonomy, $taxonomy_object, $allow_inline_add );
        $this->maybe_output_edit_taxonomy_link( $taxonomy, $taxonomy_object, $allow_inline_add );
        echo '</div>';

        wp_nonce_field( 'checkbox_' . $taxonomy . '_nonce_action', 'checkbox_' . $taxonomy . '_nonce' );
    }

    /**
     * Determine whether inline term creation is enabled for a taxonomy.
     *
     * @param string       $taxonomy        Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object Taxonomy object.
     * @return bool
     */
    private function is_inline_add_enabled( $taxonomy, $taxonomy_object ) {
        return is_array( $this->allow_term_create ) &&
            in_array( $taxonomy, $this->allow_term_create, true ) &&
            current_user_can( $taxonomy_object->cap->edit_terms );
    }

    /**
     * Determine whether edit link is enabled for a taxonomy.
     *
     * @param string $taxonomy Taxonomy slug.
     * @return bool
     */
    private function is_edit_link_enabled( $taxonomy ) {
        return is_array( $this->show_edit_links ) &&
            in_array( $taxonomy, $this->show_edit_links, true );
    }

    /**
     * Build the edit-link label text with the same four variants used in Gutenberg.
     *
     * @param \WP_Taxonomy $taxonomy_object   Taxonomy object.
     * @param bool         $allow_inline_add  Whether inline add controls are enabled.
     * @return string
     */
    private function get_edit_link_label( $taxonomy_object, $allow_inline_add ) {
        if ( $allow_inline_add ) {
            return $taxonomy_object->label
                ? sprintf(
                    /* translators: %s: taxonomy label */
                    __( 'Edit %s', 'runthings-taxonomy-tags-to-checkboxes' ),
                    $taxonomy_object->label
                )
                : __( 'Edit', 'runthings-taxonomy-tags-to-checkboxes' );
        }

        return $taxonomy_object->label
            ? sprintf(
                /* translators: %s: taxonomy label */
                __( 'Add / Edit %s', 'runthings-taxonomy-tags-to-checkboxes' ),
                $taxonomy_object->label
            )
            : __( 'Add / Edit', 'runthings-taxonomy-tags-to-checkboxes' );
    }

    /**
     * Output core-style inline add controls when enabled for this taxonomy.
     *
     * @param string       $taxonomy        Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object Taxonomy object.
     * @param bool         $allow_inline_add Whether inline add controls are enabled.
     */
    private function maybe_output_add_term_controls( $taxonomy, $taxonomy_object, $allow_inline_add ) {
        if ( ! $allow_inline_add ) {
            return;
        }

        $add_label = sprintf(
            /* translators: %s: Add new taxonomy item label. */
            __( '+ %s', 'runthings-taxonomy-tags-to-checkboxes' ),
            $taxonomy_object->labels->add_new_item
        );

        echo '<div id="' . esc_attr( $taxonomy ) . '-adder" class="wp-hidden-children">';
        echo '<a id="' . esc_attr( $taxonomy ) . '-add-toggle" href="#' . esc_attr( $taxonomy ) . '-add" class="hide-if-no-js taxonomy-add-new">';
        echo esc_html( $add_label );
        echo '</a>';
        echo '<p id="' . esc_attr( $taxonomy ) . '-add" class="category-add wp-hidden-child">';
        echo '<label class="screen-reader-text" for="new' . esc_attr( $taxonomy ) . '">' . esc_html( $taxonomy_object->labels->add_new_item ) . '</label>';
        echo '<input type="text" name="new' . esc_attr( $taxonomy ) . '" id="new' . esc_attr( $taxonomy ) . '" class="form-required form-input-tip" value="' . esc_attr( $taxonomy_object->labels->new_item_name ) . '" aria-required="true" />';
        echo '<input type="button" id="' . esc_attr( $taxonomy ) . '-add-submit" data-wp-lists="add:' . esc_attr( $taxonomy ) . 'checklist:' . esc_attr( $taxonomy ) . '-add" class="button category-add-submit" value="' . esc_attr( $taxonomy_object->labels->add_new_item ) . '" />';
        wp_nonce_field( 'add-' . $taxonomy, '_ajax_nonce-add-' . $taxonomy, false );
        echo '<span id="' . esc_attr( $taxonomy ) . '-ajax-response"></span>';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Generate the CSS style for a taxonomy container based on its height settings
     *
     * @param string $taxonomy The taxonomy name
     * @return string CSS style string
     */
    private function get_taxonomy_container_style($taxonomy) {
        // Get height settings
        $height_settings = get_option('runthings_ttc_height_settings', []);
        $height_type     = isset($height_settings[$taxonomy]['type']) ? $height_settings[$taxonomy]['type'] : '';

        // All modes get a min-height to avoid looking odd with few items
        $style = 'min-height: 42px;';

        // Apply height setting based on configuration
        // auto/default: 200px max-height matching WP core category panel behavior
        // full: explicitly remove any max-height constraint
        // custom: set a specific max-height in pixels
        switch ($height_type) {
            case 'full':
                $style .= 'max-height: none;';
                break;

            case 'custom':
                if (isset($height_settings[$taxonomy]['value'])) {
                    $custom_height = absint($height_settings[$taxonomy]['value']);
                    $style .= 'max-height: ' . $custom_height . 'px;';
                }
                break;

            case 'auto':
            default:
                $style .= 'max-height: 200px;';
                break;
        }
        
        return $style;
    }

    /**
     * Outputs the taxonomy edit link when enabled and user has permissions.
     *
     * @param string       $taxonomy         Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object  Taxonomy object.
     * @param bool         $allow_inline_add Whether inline add controls are enabled.
     */
    private function maybe_output_edit_taxonomy_link( $taxonomy, $taxonomy_object, $allow_inline_add ) {
        if (
            ! $this->is_edit_link_enabled( $taxonomy ) ||
            ! current_user_can( $taxonomy_object->cap->manage_terms )
        ) {
            return;
        }

        $edit_link = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
        $label = $this->get_edit_link_label( $taxonomy_object, $allow_inline_add );
        echo '<div class="taxonomy-edit-link">';
        echo '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">';
        echo esc_html( $label );
        echo '<span class="dashicons dashicons-external" aria-hidden="true"></span>';
        echo '</a>';
        echo '</div>';
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
        $term_ids = [];
        if ( isset( $_POST['tax_input'][ $taxonomy ] ) && is_array( $_POST['tax_input'][ $taxonomy ] ) ) {
            $term_ids = array_map( 'intval', wp_unslash( $_POST['tax_input'][ $taxonomy ] ) );
        } elseif ( isset( $_POST['checkbox_' . $taxonomy] ) ) {
            $term_ids = array_map( 'intval', wp_unslash( $_POST['checkbox_' . $taxonomy] ) );
        }
        $term_ids = array_values( array_filter( $term_ids, static function( $term_id ) {
            return $term_id > 0;
        } ) );

        wp_set_post_terms( $post_id, $term_ids, $taxonomy );
    }
}
