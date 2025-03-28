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

define( 'RUNTHINGS_TTC_VERSION', '0.1.0' );
define( 'RUNTHINGS_TTC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RUNTHINGS_TTC_URL', plugin_dir_url( __FILE__ ) );

require_once RUNTHINGS_TTC_DIR . 'lib/admin-options.php';

class Taxonomy_Tags_To_Checkboxes {
    public function __construct() {
        new Admin_Options();

        $selected_taxonomies = get_option( 'runthings_ttc_selected_taxonomies', [] );

        $selected_taxonomies = apply_filters( 'runthings_ttc_selected_taxonomies', $selected_taxonomies );

        foreach ( $selected_taxonomies as $taxonomy ) {
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

    public function remove_default_taxonomy_metabox( $post_type, $post, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            remove_meta_box( 'tagsdiv-' . $taxonomy, $post_type, 'side' );
        }
    }

    public function add_taxonomy_metabox( $post_type, $taxonomy ) {
        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! in_array( $post_type, $taxonomy_object->object_type, true ) ) {
            return;
        }

        add_meta_box(
            'checkbox-' . $taxonomy . '-metabox',
            __( $taxonomy_object->label, 'runthings-taxonomy-tags-to-checkboxes' ),
            function ( $post ) use ( $taxonomy ) {
                $this->render_taxonomy_metabox( $post, $taxonomy );
            },
            $post_type,
            'side',
            'default'
        );
    }

    public function render_taxonomy_metabox( $post, $taxonomy ) {
        $terms      = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );

        $post_terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            echo '<ul>';
            foreach ( $terms as $term ) {
                $checked = in_array( $term->term_id, $post_terms, true ) ? 'checked' : '';
                echo '<li><label><input type="checkbox" name="checkbox_' . esc_attr( $taxonomy ) . '[]" value="' . esc_attr( $term->term_id ) . '" ' . $checked . '> ' . esc_html( $term->name ) . '</label></li>';
            }
            echo '</ul>';
        } else {
            echo esc_html__( 'No terms available.', 'runthings-taxonomy-tags-to-checkboxes' );
        }
        
        wp_nonce_field( 'checkbox_' . $taxonomy . '_nonce_action', 'checkbox_' . $taxonomy . '_nonce' );
    }

    public function save_taxonomy_metabox( $post_id, $taxonomy ) {
        if ( ! isset( $_POST['checkbox_' . $taxonomy . '_nonce'] ) || ! wp_verify_nonce( $_POST['checkbox_' . $taxonomy . '_nonce'], 'checkbox_' . $taxonomy . '_nonce_action' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $term_ids = isset( $_POST['checkbox_' . $taxonomy] ) ? array_map( 'intval', $_POST['checkbox_' . $taxonomy] ) : [];
        wp_set_post_terms( $post_id, $term_ids, $taxonomy );
    }
}

new Taxonomy_Tags_To_Checkboxes();
