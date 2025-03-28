<?php

/*
 * Plugin Name: Taxonomy Tags to Checkboxes
 * Plugin URI: https://runthings.dev
 * Description: Convert taxonomy tags to checkboxes in the WordPress admin.
 * Version: 0.1.0
 * Author: runthingsdev
 * Author URI: https://runthings.dev/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: runthings-taxonomy-tags-to-checkboxes
 * Domain Path: /languages
*/
/*
Copyright 2025 Matthew Harris

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomy_Tags_To_Checkboxes {
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'remove_default_taxonomy_metabox' ], 10, 2 );
        add_action( 'add_meta_boxes', [ $this, 'add_taxonomy_metabox' ] );
        add_action( 'save_post', [ $this, 'save_taxonomy_metabox' ] );
    }

    public function remove_default_taxonomy_metabox( $post_type, $post ) {
        if ( 'lodge' === $post_type ) {
            remove_meta_box( 'tagsdiv-collection', 'lodge', 'side' );
        }
    }

    public function add_taxonomy_metabox() {
        add_meta_box(
            'checkbox-collection-metabox',
            __( 'Collections', 'runthings-taxonomy-tags-to-checkboxes' ),
            [ $this, 'render_taxonomy_metabox' ],
            'lodge',
            'side',
            'default'
        );
    }

    public function render_taxonomy_metabox( $post ) {
        $taxonomy   = 'collection';

        $terms      = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );

        $post_terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            echo '<ul>';
            foreach ( $terms as $term ) {
                $checked = in_array( $term->term_id, $post_terms, true ) ? 'checked' : '';
                echo '<li><label><input type="checkbox" name="checkbox_collection[]" value="' . esc_attr( $term->term_id ) . '" ' . $checked . '> ' . esc_html( $term->name ) . '</label></li>';
            }
            echo '</ul>';
        } else {
            echo esc_html__( 'No collections available.', 'runthings-taxonomy-tags-to-checkboxes' );
        }
        
        wp_nonce_field( 'checkbox_collection_nonce_action', 'checkbox_collection_nonce' );
    }

    public function save_taxonomy_metabox( $post_id ) {
        if ( ! isset( $_POST['checkbox_collection_nonce'] ) || ! wp_verify_nonce( $_POST['checkbox_collection_nonce'], 'checkbox_collection_nonce_action' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $taxonomy       = 'collection';
        $collection_ids = isset( $_POST['checkbox_collection'] ) ? array_map( 'intval', $_POST['checkbox_collection'] ) : [];
        wp_set_post_terms( $post_id, $collection_ids, $taxonomy );
    }
}

new Taxonomy_Tags_To_Checkboxes();
