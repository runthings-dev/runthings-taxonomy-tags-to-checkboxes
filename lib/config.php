<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
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

    /**
     * @var array Taxonomy height settings.
     */
    private $height_settings = [];

    public function __construct() {
        $this->selected_taxonomies = $this->normalize_slug_array(
            get_option( 'runthings_ttc_selected_taxonomies', [] )
        );
        $this->selected_taxonomies = $this->normalize_slug_array(
            apply_filters( 'runthings_ttc_selected_taxonomies', $this->selected_taxonomies )
        );

        $this->allow_term_create = $this->normalize_slug_array(
            get_option( 'runthings_ttc_allow_term_create', [] )
        );
        $this->show_edit_links = $this->normalize_slug_array(
            get_option( 'runthings_ttc_show_links', [] )
        );

        $height_settings = get_option( 'runthings_ttc_height_settings', [] );
        $this->height_settings = is_array( $height_settings ) ? $height_settings : [];
    }

    /**
     * @return array
     */
    public function get_selected_taxonomies() {
        return $this->selected_taxonomies;
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return bool
     */
    public function is_taxonomy_selected( $taxonomy ) {
        return in_array( $taxonomy, $this->selected_taxonomies, true );
    }

    /**
     * @param string       $taxonomy        Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object Taxonomy object.
     * @return bool
     */
    public function is_inline_add_enabled_for_user( $taxonomy, $taxonomy_object ) {
        return in_array( $taxonomy, $this->allow_term_create, true ) &&
            current_user_can( $taxonomy_object->cap->edit_terms );
    }

    /**
     * @param string       $taxonomy        Taxonomy slug.
     * @param \WP_Taxonomy $taxonomy_object Taxonomy object.
     * @return bool
     */
    public function is_edit_link_enabled_for_user( $taxonomy, $taxonomy_object ) {
        return in_array( $taxonomy, $this->show_edit_links, true ) &&
            current_user_can( $taxonomy_object->cap->manage_terms );
    }

    /**
     * @param \WP_Taxonomy $taxonomy_object  Taxonomy object.
     * @param bool         $allow_inline_add Whether inline add controls are enabled.
     * @return string
     */
    public function get_edit_link_label( $taxonomy_object, $allow_inline_add ) {
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
     * @param string $taxonomy Taxonomy slug.
     * @return string
     */
    public function get_classic_container_style( $taxonomy ) {
        $height_type = isset( $this->height_settings[ $taxonomy ]['type'] )
            ? $this->height_settings[ $taxonomy ]['type']
            : '';

        $style = 'min-height: 42px;';

        switch ( $height_type ) {
            case 'full':
                $style .= 'max-height: none;';
                break;

            case 'custom':
                if ( isset( $this->height_settings[ $taxonomy ]['value'] ) ) {
                    $custom_height = absint( $this->height_settings[ $taxonomy ]['value'] );
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
     * @return array
     */
    public function get_block_taxonomy_configs() {
        $taxonomy_configs = [];

        foreach ( $this->selected_taxonomies as $taxonomy ) {
            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object || ! $taxonomy_object->show_in_rest ) {
                continue;
            }

            $height_type = isset( $this->height_settings[ $taxonomy ]['type'] )
                ? $this->height_settings[ $taxonomy ]['type']
                : 'auto';
            $max_height = 'auto';
            if ( 'full' === $height_type ) {
                $max_height = 'none';
            } elseif ( 'custom' === $height_type && isset( $this->height_settings[ $taxonomy ]['value'] ) ) {
                $max_height = absint( $this->height_settings[ $taxonomy ]['value'] ) . 'px';
            }

            $allow_inline_add = in_array( $taxonomy, $this->allow_term_create, true );
            $can_create_terms = $allow_inline_add && current_user_can( $taxonomy_object->cap->edit_terms );
            $show_edit_link = in_array( $taxonomy, $this->show_edit_links, true );
            $edit_url = '';
            if ( $show_edit_link && current_user_can( $taxonomy_object->cap->manage_terms ) ) {
                $edit_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
            }

            $taxonomy_configs[] = [
                'slug'           => $taxonomy,
                'restBase'       => $taxonomy_object->rest_base ?: $taxonomy,
                'label'          => $taxonomy_object->label,
                'postTypes'      => array_values( $taxonomy_object->object_type ),
                'maxHeight'      => $max_height,
                'showEditLink'   => $show_edit_link && ! empty( $edit_url ),
                'editUrl'        => $edit_url,
                'allowInlineAdd' => $allow_inline_add,
                'canCreateTerms' => $can_create_terms,
                'editLinkLabel'  => $this->get_edit_link_label( $taxonomy_object, $can_create_terms ),
            ];
        }

        return $taxonomy_configs;
    }

    /**
     * @return array
     */
    public function get_inline_add_taxonomies() {
        return array_values(
            array_intersect( $this->selected_taxonomies, $this->allow_term_create )
        );
    }

    /**
     * @param mixed $value Value to normalize.
     * @return array
     */
    private function normalize_slug_array( $value ) {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map( 'strval', $value ),
                    static function ( $slug ) {
                        return '' !== $slug;
                    }
                )
            )
        );
    }
}
