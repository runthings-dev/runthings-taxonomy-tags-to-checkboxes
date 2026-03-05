<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Classic_Integration {
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config Shared taxonomy UI config.
     */
    public function __construct( Config $config ) {
        $this->config = $config;

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_metabox_styles' ] );
        add_action( 'admin_init', [ $this, 'register_inline_create_ajax_hooks' ] );

        foreach ( $this->config->get_selected_taxonomies() as $taxonomy ) {
            add_action(
                'add_meta_boxes',
                function ( $post_type, $post ) use ( $taxonomy ) {
                    $this->remove_default_taxonomy_metabox( $post_type, $taxonomy );
                    if ( $this->should_use_classic_metabox( $post_type, $post ) ) {
                        $this->add_taxonomy_metabox( $post_type, $taxonomy );
                    }
                },
                10,
                2
            );

            add_action(
                'save_post',
                function ( $post_id ) use ( $taxonomy ) {
                    $this->save_taxonomy_metabox( $post_id, $taxonomy );
                }
            );
        }
    }

    /**
     * Register per-taxonomy add-term AJAX hooks when inline create is enabled.
     */
    public function register_inline_create_ajax_hooks() {
        foreach ( $this->config->get_inline_add_taxonomies() as $taxonomy ) {
            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object ) {
                continue;
            }

            add_action( 'wp_ajax_add-' . $taxonomy, [ $this, 'handle_inline_create_ajax' ] );
        }
    }

    /**
     * Enqueue styles for the taxonomy metabox.
     *
     * @param string $hook Current admin page hook.
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

        if (
            empty( $this->config->get_inline_add_taxonomies() ) &&
            ! $this->config->has_search_enabled_taxonomies()
        ) {
            return;
        }

        wp_enqueue_script(
            'runthings-ttc-classic-metabox',
            RUNTHINGS_TTC_URL . 'assets/js/classic-metabox.js',
            [ 'jquery' ],
            RUNTHINGS_TTC_VERSION,
            true
        );

        wp_localize_script(
            'runthings-ttc-classic-metabox',
            'runthingsTtcClassic',
            [
                'searchLabel'     => __( 'Search terms', 'runthings-taxonomy-tags-to-checkboxes' ),
                'searchPlaceholder' => __( 'Search terms...', 'runthings-taxonomy-tags-to-checkboxes' ),
                'noMatchingTerms' => __( 'No matching terms.', 'runthings-taxonomy-tags-to-checkboxes' ),
            ]
        );
    }

    /**
     * Remove the default taxonomy metabox.
     *
     * @param string $post_type Post type.
     * @param string $taxonomy  Taxonomy slug.
     */
    public function remove_default_taxonomy_metabox( $post_type, $taxonomy ) {
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
     * @param string   $post_type Post type.
     * @param \WP_Post $post      Current post object.
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
     * Add a custom taxonomy metabox with checkboxes.
     *
     * @param string $post_type Post type.
     * @param string $taxonomy  Taxonomy slug.
     */
    public function add_taxonomy_metabox( $post_type, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object || ! in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            return;
        }

        add_meta_box(
            $this->get_metabox_id( $taxonomy ),
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
     * Render the taxonomy metabox with checkboxes.
     *
     * @param \WP_Post $post     Current post object.
     * @param string   $taxonomy Taxonomy slug.
     */
    public function render_taxonomy_metabox( $post, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object || $taxonomy_object->hierarchical ) {
            return;
        }

        $style = $this->config->get_classic_container_style( $taxonomy );
        $allow_inline_add = $this->config->is_inline_add_enabled_for_user( $taxonomy, $taxonomy_object );
        $search_config = $this->config->get_search_config_for_taxonomy( $taxonomy );

        echo '<div id="taxonomy-' . esc_attr( $taxonomy ) . '" class="categorydiv">';
        echo '<div class="taxonomies-container tabs-panel" id="' . esc_attr( $taxonomy ) . '-all" style="' . esc_attr( $style ) . '">';

        echo "<input type='hidden' name='" . esc_attr( $this->get_checkbox_input_name( $taxonomy ) ) . "[]' value='0' />";

        echo '<ul id="' . esc_attr( $taxonomy ) . 'checklist" data-wp-lists="list:' . esc_attr( $taxonomy ) . '"' .
            ( $allow_inline_add ? ' data-runthings-ttc-sortable="' . esc_attr( '1' ) . '"' : '' ) .
            ' data-runthings-ttc-search-mode="' . esc_attr( $search_config['mode'] ) . '"' .
            ' data-runthings-ttc-search-threshold="' . esc_attr( (string) $search_config['threshold'] ) . '"' .
            ' class="categorychecklist form-no-clear">';
        wp_terms_checklist(
            $post->ID,
            [
                'taxonomy' => $taxonomy,
                'walker'   => $this->get_checklist_walker( $taxonomy ),
            ]
        );
        echo '</ul>';
        echo '</div>';

        $this->maybe_output_add_term_controls( $taxonomy, $taxonomy_object, $allow_inline_add );
        $this->maybe_output_edit_taxonomy_link( $taxonomy, $taxonomy_object, $allow_inline_add );
        echo '</div>';

        wp_nonce_field(
            $this->get_nonce_action_name( $taxonomy ),
            $this->get_nonce_field_name( $taxonomy )
        );
    }

    /**
     * Output core-style inline add controls when enabled.
     *
     * @param string       $taxonomy         Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object  Taxonomy object.
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
     * Outputs the taxonomy edit link when enabled and user has permissions.
     *
     * @param string       $taxonomy         Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object  Taxonomy object.
     * @param bool         $allow_inline_add Whether inline add controls are enabled.
     */
    private function maybe_output_edit_taxonomy_link( $taxonomy, $taxonomy_object, $allow_inline_add ) {
        if ( ! $this->config->is_edit_link_enabled_for_user( $taxonomy, $taxonomy_object ) ) {
            return;
        }

        $edit_link = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
        $label = $this->config->get_edit_link_label( $taxonomy_object, $allow_inline_add );
        echo '<div class="taxonomy-edit-link">';
        echo '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">';
        echo esc_html( $label );
        echo '<span class="dashicons dashicons-external" aria-hidden="true"></span>';
        echo '</a>';
        echo '</div>';
    }

    /**
     * Save selected terms when the post is saved.
     *
     * @param int    $post_id  Post ID.
     * @param string $taxonomy Taxonomy slug.
     */
    public function save_taxonomy_metabox( $post_id, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object || $taxonomy_object->hierarchical ) {
            return;
        }

        $nonce_field = $this->get_nonce_field_name( $taxonomy );
        $nonce_action = $this->get_nonce_action_name( $taxonomy );

        if (
            ! isset( $_POST[ $nonce_field ] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action )
        ) {
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
        } elseif ( isset( $_POST[ $this->get_checkbox_input_name( $taxonomy ) ] ) ) {
            $term_ids = array_map( 'intval', wp_unslash( $_POST[ $this->get_checkbox_input_name( $taxonomy ) ] ) );
        }

        $term_ids = array_values(
            array_filter(
                $term_ids,
                static function ( $term_id ) {
                    return $term_id > 0;
                }
            )
        );

        wp_set_post_terms( $post_id, $term_ids, $taxonomy );
    }

    /**
     * Handle inline term creation with custom checklist input names.
     */
    public function handle_inline_create_ajax() {
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        $taxonomy = get_taxonomy( substr( $action, 4 ) );

        if ( ! $taxonomy || $taxonomy->hierarchical ) {
            wp_die( -1 );
        }

        check_ajax_referer( $action, '_ajax_nonce-add-' . $taxonomy->name );

        if ( ! current_user_can( $taxonomy->cap->edit_terms ) ) {
            wp_die( -1 );
        }

        $new_terms = isset( $_POST[ 'new' . $taxonomy->name ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'new' . $taxonomy->name ] ) ) : '';
        $names = explode( ',', $new_terms );
        $posted_terms = isset( $_POST[ $this->get_checkbox_input_name( $taxonomy->name ) ] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ $this->get_checkbox_input_name( $taxonomy->name ) ] ) )
            : [];

        $selected_term_ids = array_map( 'absint', $posted_terms );
        $popular_term_ids = wp_popular_terms_checklist( $taxonomy->name, 0, 10, false );
        $add = null;

        foreach ( $names as $term_name ) {
            $term_name = trim( $term_name );
            $term_slug = sanitize_title( $term_name );

            if ( '' === $term_slug ) {
                continue;
            }

            $term_id = 0;
            $inserted_term = wp_insert_term( $term_name, $taxonomy->name );

            if ( is_wp_error( $inserted_term ) ) {
                $existing_term_id = (int) $inserted_term->get_error_data( 'term_exists' );
                if ( $existing_term_id > 0 ) {
                    $term_id = $existing_term_id;
                } else {
                    continue;
                }
            } elseif ( $inserted_term && isset( $inserted_term['term_id'] ) ) {
                $term_id = (int) $inserted_term['term_id'];
            }

            if ( $term_id <= 0 ) {
                continue;
            }

            if ( ! in_array( $term_id, $selected_term_ids, true ) ) {
                $selected_term_ids[] = $term_id;
            }

            $add = $this->build_inline_add_response( $taxonomy->name, $term_id, $selected_term_ids, $popular_term_ids, $term_id );
        }

        if ( ! is_array( $add ) ) {
            wp_die( 0 );
        }

        $response = new \WP_Ajax_Response( $add );
        $response->send();
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return string
     */
    private function get_checkbox_input_name( $taxonomy ) {
        return 'rtp_ttc_checkbox_' . $taxonomy;
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return string
     */
    private function get_nonce_field_name( $taxonomy ) {
        return 'rtp_ttc_' . $taxonomy . '_nonce';
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return string
     */
    private function get_nonce_action_name( $taxonomy ) {
        return 'rtp_ttc_' . $taxonomy . '_nonce_action';
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return string
     */
    private function get_metabox_id( $taxonomy ) {
        return 'rtp-ttc-' . $taxonomy . '-metabox';
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return Classic_Term_Checklist_Walker
     */
    private function get_checklist_walker( $taxonomy ) {
        require_once RUNTHINGS_TTC_DIR . 'lib/classic-integration-walker.php';

        return new Classic_Term_Checklist_Walker( $this->get_checkbox_input_name( $taxonomy ) );
    }

    /**
     * Build the AJAX response payload for a newly inserted term.
     *
     * @param string $taxonomy           Taxonomy slug.
     * @param int    $response_id        Response node ID.
     * @param array  $selected_term_ids  Selected term IDs.
     * @param array  $popular_term_ids   Popular term IDs.
     * @param int    $descendant_term_id Term subtree root to render.
     * @return array
     */
    private function build_inline_add_response( $taxonomy, $response_id, $selected_term_ids, $popular_term_ids, $descendant_term_id ) {
        ob_start();
        wp_terms_checklist(
            0,
            [
                'taxonomy'             => $taxonomy,
                'descendants_and_self' => $descendant_term_id,
                'selected_cats'        => $selected_term_ids,
                'popular_cats'         => $popular_term_ids,
                'walker'               => $this->get_checklist_walker( $taxonomy ),
            ]
        );

        return [
            'what'     => $taxonomy,
            'id'       => $response_id,
            'data'     => str_replace( [ "\n", "\t" ], '', ob_get_clean() ),
            'position' => -1,
        ];
    }
}
