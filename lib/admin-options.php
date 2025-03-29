<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Options {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
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

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_runthings-taxonomy-options' !== $hook) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'runthings-ttc-admin',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with taxonomy stats
        wp_localize_script(
            'runthings-ttc-admin',
            'taxonomyStats',
            $this->get_taxonomy_counts()
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'runthings-ttc-admin-styles',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
    }

    public function render_taxonomy_checkboxes() {
        $selected_taxonomies = get_option( 'runthings_ttc_selected_taxonomies', [] );
        
        if (!is_array($selected_taxonomies)) {
            $selected_taxonomies = [];
        }
        
        $taxonomies = get_taxonomies( [], 'objects' );

        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <p><?php esc_html_e( 'Select which taxonomies should use checkboxes instead of tags UI.', 'runthings-taxonomy-tags-to-checkboxes' ); ?></p>
            </div>
            <div class="alignright">
                <label>
                    <input type="checkbox" id="show-system-taxonomies">
                    <?php esc_html_e( 'Show system taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ); ?>
                </label>
            </div>
            <br class="clear" />
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list" id="taxonomy-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <label class="screen-reader-text"><?php esc_html_e( 'Select All', 'runthings-taxonomy-tags-to-checkboxes' ); ?></label>
                    </th>
                    <th scope="col" class="manage-column column-name column-primary sortable desc">
                        <a href="#" class="sort-column" data-column="name">
                            <span><?php esc_html_e( 'Name', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-post_types sortable desc">
                        <a href="#" class="sort-column" data-column="post_types">
                            <span><?php esc_html_e( 'Post Types', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-type sortable desc">
                        <a href="#" class="sort-column" data-column="type">
                            <span><?php esc_html_e( 'Type', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // No data message row (initially hidden)
                ?>
                <tr class="no-items" style="display: none;">
                    <td class="colspanchange" colspan="4">
                        <div class="no-taxonomy-items">
                            <p><?php esc_html_e( 'No taxonomies found.', 'runthings-taxonomy-tags-to-checkboxes' ); ?></p>
                            <p class="hidden-system-message">
                                <?php esc_html_e( 'System taxonomies are currently hidden. Enable "Show system taxonomies" to see more options.', 'runthings-taxonomy-tags-to-checkboxes' ); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <?php foreach ( $taxonomies as $taxonomy ) : 
                    $checked    = in_array( $taxonomy->name, $selected_taxonomies, true ) ? 'checked' : '';
                    
                    // Format post types with code tags
                    $post_types_array = array_map(function($type) {
                        return '<code>' . esc_html($type) . '</code>';
                    }, $taxonomy->object_type);
                    $post_types = implode( ' ', $post_types_array );
                    
                    $type       = $taxonomy->hierarchical ? 
                        __( 'Hierarchical (already uses checkboxes)', 'runthings-taxonomy-tags-to-checkboxes' ) : 
                        __( 'Non-hierarchical (tags UI)', 'runthings-taxonomy-tags-to-checkboxes' );
                    $disabled   = $taxonomy->hierarchical ? 'disabled' : '';
                    $title      = $taxonomy->hierarchical ? 'title="' . esc_attr__( 'Already uses checkboxes', 'runthings-taxonomy-tags-to-checkboxes' ) . '"' : '';
                    
                    // Determine if this is a system taxonomy
                    $is_system = false;
                    
                    // Built-in taxonomies are generally system ones
                    if (!empty($taxonomy->_builtin)) {
                        $is_system = true;
                    }
                    
                    // Non-public taxonomies are generally for internal use
                    if (isset($taxonomy->public) && $taxonomy->public === false) {
                        $is_system = true;
                    }
                    
                    // Taxonomies with common system prefixes
                    $system_prefixes = array('wp_', '_wp_', 'wc_', '_wc_', 'nav_', '_nav_');
                    foreach ($system_prefixes as $prefix) {
                        if (strpos($taxonomy->name, $prefix) === 0) {
                            $is_system = true;
                            break;
                        }
                    }
                    
                    // Add a class for filtering
                    $row_class = $is_system ? 'system-taxonomy' : 'user-taxonomy';
                ?>
                <tr data-name="<?php echo esc_attr( strtolower($taxonomy->label) ); ?>" 
                    data-post-types="<?php echo esc_attr( strtolower(implode(', ', $taxonomy->object_type)) ); ?>"
                    data-type="<?php echo esc_attr( $taxonomy->hierarchical ? '1' : '0' ); ?>"
                    data-system="<?php echo $is_system ? '1' : '0'; ?>"
                    class="<?php echo $row_class; ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="runthings_ttc_selected_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php echo $checked; ?> <?php echo $disabled; ?> <?php echo $title; ?>>
                    </th>
                    <td class="column-name column-primary">
                        <strong><?php echo esc_html( $taxonomy->label ); ?></strong>
                        <div class="row-actions">
                            <span class="id"><?php echo esc_html( $taxonomy->name ); ?></span>
                        </div>
                    </td>
                    <td class="column-post_types"><?php echo $post_types; // Already escaped individually ?></td>
                    <td class="column-type"><?php echo esc_html( $type ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <label class="screen-reader-text"><?php esc_html_e( 'Select All', 'runthings-taxonomy-tags-to-checkboxes' ); ?></label>
                    </th>
                    <th scope="col" class="manage-column column-name column-primary sortable desc">
                        <a href="#" class="sort-column" data-column="name">
                            <span><?php esc_html_e( 'Name', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-post_types sortable desc">
                        <a href="#" class="sort-column" data-column="post_types">
                            <span><?php esc_html_e( 'Post Types', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-type sortable desc">
                        <a href="#" class="sort-column" data-column="type">
                            <span><?php esc_html_e( 'Type', 'runthings-taxonomy-tags-to-checkboxes' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    /**
     * Count user and system taxonomies
     *
     * @return array Array with userCount and systemCount
     */
    private function get_taxonomy_counts() {
        $taxonomies = get_taxonomies( [], 'objects' );
        $user_count = 0;
        $system_count = 0;
        
        foreach ( $taxonomies as $taxonomy ) {
            $is_system = false;
            
            // Built-in taxonomies are generally system ones
            if (!empty($taxonomy->_builtin)) {
                $is_system = true;
            }
            
            // Non-public taxonomies are generally for internal use
            if (isset($taxonomy->public) && $taxonomy->public === false) {
                $is_system = true;
            }
            
            // Taxonomies with common system prefixes
            $system_prefixes = array('wp_', '_wp_', 'wc_', '_wc_', 'nav_', '_nav_');
            foreach ($system_prefixes as $prefix) {
                if (strpos($taxonomy->name, $prefix) === 0) {
                    $is_system = true;
                    break;
                }
            }
            
            // Count taxonomies by type
            if ($is_system) {
                $system_count++;
            } else {
                $user_count++;
            }
        }
        
        return array(
            'userCount' => $user_count,
            'systemCount' => $system_count
        );
    }
}