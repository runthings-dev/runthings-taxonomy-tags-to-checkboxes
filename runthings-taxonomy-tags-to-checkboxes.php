<?php

/**
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

define( 'RUNTHINGS_TTC_BASENAME', plugin_basename( __FILE__ ) );
define( 'RUNTHINGS_TTC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RUNTHINGS_TTC_URL', plugin_dir_url( __FILE__ ) );

require_once RUNTHINGS_TTC_DIR . 'lib/admin-options.php';
require_once RUNTHINGS_TTC_DIR . 'lib/replace-tags.php';

class Taxonomy_Tags_To_Checkboxes {
    public function __construct() {
        new Admin_Options();
        new Replace_Tags();

        add_filter(
            'plugin_action_links_' . RUNTHINGS_TTC_BASENAME,
            [ $this, 'add_github_plugin_link' ]
        );
    }

    /**
     * Add a link to the plugin's GitHub repository on the plugins page.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_github_plugin_link( $links ) {
        $github_link = '<a href="https://github.com/runthings-dev/runthings-taxonomy-tags-to-checkboxes" target="_blank">GitHub</a>';
        $links[] = $github_link;
        return $links;
    }
}

new Taxonomy_Tags_To_Checkboxes();

/**
 * Plugin activation hook
 */
function activate_runthings_ttc() {
    $autoload = false;
    
    // Register the settings, but do not autoload
    add_option( 'runthings_ttc_selected_taxonomies', [], '', $autoload );
    add_option( 'runthings_ttc_height_settings', [], '', $autoload );
    add_option( 'runthings_ttc_show_links', [], '', $autoload);
}
register_activation_hook( __FILE__, 'RunthingsTaxonomyTagsToCheckboxes\activate_runthings_ttc' );

/**
 * Plugin uninstall hook
 */
function uninstall_runthings_ttc() {
    // Check if the user has requested to delete all data
    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || WP_UNINSTALL_PLUGIN !== true ) {
        return;
    }

    // Delete options
    delete_option( 'runthings_ttc_selected_taxonomies' );
    delete_option( 'runthings_ttc_height_settings' );
    delete_option( 'runthings_ttc_show_links' );
}
register_uninstall_hook( __FILE__, 'RunthingsTaxonomyTagsToCheckboxes\uninstall_runthings_ttc' );
