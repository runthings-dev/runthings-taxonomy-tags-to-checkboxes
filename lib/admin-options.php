<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin_Options
 * Handles the admin options page and settings.
 */
class Admin_Options {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Adds the admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ),
            __( 'Taxonomies', 'runthings-taxonomy-tags-to-checkboxes' ),
            'manage_options',
            'runthings-taxonomy-options',
            [ $this, 'render_options_page' ]
        );
    }

    /**
     * Renders the options page.
     */
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

    /**
     * Registers the settings.
     */
    public function register_settings() {
        register_setting(
            'runthings_taxonomy_options_group',
            'runthings_ttc_selected_taxonomies',
            ['sanitize_callback' => [$this, 'sanitize_taxonomy_settings']]
        );
        
        register_setting(
            'runthings_taxonomy_options_group',
            'runthings_ttc_height_settings',
            ['sanitize_callback' => [$this, 'sanitize_height_settings']]
        );

        // Register new setting for Edit Links
        register_setting(
            'runthings_taxonomy_options_group',
            'runthings_ttc_show_links',
            ['sanitize_callback' => [$this, 'sanitize_show_links_settings']]
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
    
    /**
     * Sanitize taxonomy settings
     *
     * @param array $input The input array.
     * @return array The sanitized array.
     */
    public function sanitize_taxonomy_settings($input) {
        if (!is_array($input)) {
            return [];
        }
        return array_map('sanitize_text_field', $input);
    }
    
    /**
     * Sanitize height settings
     *
     * @param array $input The input array.
     * @return array The sanitized array.
     */
    public function sanitize_height_settings($input) {
        if (!is_array($input)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($input as $taxonomy => $settings) {
            $taxonomy = sanitize_text_field($taxonomy);
            
            if (!isset($settings['type']) || !in_array($settings['type'], ['auto', 'full', 'custom'])) {
                $settings['type'] = 'auto';
            }
            
            $sanitized[$taxonomy] = [
                'type' => sanitize_text_field($settings['type']),
            ];
            
            if ($settings['type'] === 'custom' && isset($settings['value'])) {
                $sanitized[$taxonomy]['value'] = absint($settings['value']);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize show links settings
     *
     * @param array $input The input array.
     * @return array The sanitized array.
     */
    public function sanitize_show_links_settings($input) {
        if (!is_array($input)) {
            return [];
        }
        return array_map('sanitize_text_field', $input);
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_runthings-taxonomy-options' !== $hook) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'runthings-ttc-admin',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin.js',
            array('jquery'),
            RUNTHINGS_TTC_VERSION,
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
            RUNTHINGS_TTC_VERSION
        );
    }

    /**
     * Renders the taxonomy checkboxes.
     */
    public function render_taxonomy_checkboxes() {
        $selected_taxonomies = get_option('runthings_ttc_selected_taxonomies', []);
        $height_settings     = get_option('runthings_ttc_height_settings', []);
        $show_links          = get_option('runthings_ttc_show_links', []);
        
        if (!is_array($selected_taxonomies)) {
            $selected_taxonomies = [];
        }
        
        if (!is_array($height_settings)) {
            $height_settings = [];
        }
        
        if (!is_array($show_links)) {
            $show_links = [];
        }
        
        $taxonomies = get_taxonomies([], 'objects');

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
                    <th scope="col" class="manage-column column-height">
                        <span><?php esc_html_e('Height', 'runthings-taxonomy-tags-to-checkboxes'); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-show-link">
                        <span><?php esc_html_e('Show Edit Link', 'runthings-taxonomy-tags-to-checkboxes'); ?></span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // No data message row (initially hidden)
                ?>
                <tr class="no-items" style="display: none;">
                    <td class="colspanchange" colspan="6">
                        <div class="no-taxonomy-items">
                            <p><?php esc_html_e( 'No taxonomies found.', 'runthings-taxonomy-tags-to-checkboxes' ); ?></p>
                            <p class="hidden-system-message">
                                <?php esc_html_e( 'System taxonomies are currently hidden. Enable "Show system taxonomies" to see more options.', 'runthings-taxonomy-tags-to-checkboxes' ); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <?php foreach ( $taxonomies as $taxonomy ) : 
                    $checked = in_array( $taxonomy->name, $selected_taxonomies, true ) ? 'checked' : '';
                    
                    // Format post types with code tags
                    $post_types_array = array_map(function($type) {
                        return '<code>' . esc_html($type) . '</code>';
                    }, $taxonomy->object_type);
                    $post_types = implode( ' ', $post_types_array );
                    
                    // Format taxonomy type
                    if ($taxonomy->hierarchical) {
                        $type = '<abbr title="' . esc_attr__('Already uses checkboxes', 'runthings-taxonomy-tags-to-checkboxes') . '">' . 
                               esc_html__('Hierarchical', 'runthings-taxonomy-tags-to-checkboxes') . '</abbr>';
                    } else {
                        $type = '<abbr title="' . esc_attr__('Uses tags interface by default', 'runthings-taxonomy-tags-to-checkboxes') . '">' . 
                               esc_html__('Non-hierarchical', 'runthings-taxonomy-tags-to-checkboxes') . '</abbr>';
                    }
                    
                    $disabled = $taxonomy->hierarchical ? 'disabled' : '';
                    $title    = $taxonomy->hierarchical ? 'title="' . esc_attr__( 'Already uses checkboxes', 'runthings-taxonomy-tags-to-checkboxes' ) . '"' : '';
                    
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

                    // Get height settings for this taxonomy
                    $height_type = isset($height_settings[$taxonomy->name]['type']) ? 
                        $height_settings[$taxonomy->name]['type'] : 'auto';
                    
                    $custom_height = isset($height_settings[$taxonomy->name]['value']) ? 
                        intval($height_settings[$taxonomy->name]['value']) : 200;

                    // Is this taxonomy selected for conversion
                    $is_selected = in_array($taxonomy->name, $selected_taxonomies, true);
                    
                    // Disable height controls initially for hierarchical or unselected taxonomies
                    $height_disabled = $taxonomy->hierarchical || !$is_selected ? 'disabled' : '';

                    // Is this taxonomy in the show links list
                    $show_link_checked = in_array($taxonomy->name, $show_links, true) ? 'checked' : '';
                    
                    // Disable show link checkbox for hierarchical or unselected taxonomies
                    $link_disabled = $taxonomy->hierarchical || !$is_selected ? 'disabled' : '';
                ?>
                <tr data-name="<?php echo esc_attr( strtolower($taxonomy->label) ); ?>" 
                    data-post-types="<?php echo esc_attr( strtolower(implode(', ', $taxonomy->object_type)) ); ?>"
                    data-type="<?php echo esc_attr( $taxonomy->hierarchical ? '1' : '0' ); ?>"
                    data-system="<?php echo esc_attr($is_system ? '1' : '0'); ?>"
                    class="<?php echo esc_attr($row_class); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="runthings_ttc_selected_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php echo esc_attr($checked); ?> <?php echo esc_attr($disabled); ?> <?php echo wp_kses_post($title); ?>>
                    </th>
                    <td class="column-name column-primary">
                        <strong><?php echo esc_html( $taxonomy->label ); ?></strong>
                        <div class="row-actions">
                            <span class="id"><?php echo esc_html( $taxonomy->name ); ?></span>
                        </div>
                    </td>
                    <td class="column-post_types"><?php echo wp_kses($post_types, array('code' => array())); ?></td>
                    <td class="column-type"><?php echo wp_kses($type, array('abbr' => array('title' => array()))); ?></td>
                    <td class="column-height">
                        <select name="runthings_ttc_height_settings[<?php echo esc_attr($taxonomy->name); ?>][type]" class="height-type-select" <?php echo esc_attr($height_disabled); ?>>
                            <option value="auto" <?php selected($height_type, 'auto'); ?>><?php esc_html_e('Auto', 'runthings-taxonomy-tags-to-checkboxes'); ?></option>
                            <option value="full" <?php selected($height_type, 'full'); ?>><?php esc_html_e('Full', 'runthings-taxonomy-tags-to-checkboxes'); ?></option>
                            <option value="custom" <?php selected($height_type, 'custom'); ?>><?php esc_html_e('Custom', 'runthings-taxonomy-tags-to-checkboxes'); ?></option>
                        </select>
                        <div class="custom-height-input" style="margin-top: 5px; <?php echo esc_attr($height_type !== 'custom' ? 'display: none;' : ''); ?>">
                            <input type="number" name="runthings_ttc_height_settings[<?php echo esc_attr($taxonomy->name); ?>][value]" value="<?php echo esc_attr($custom_height); ?>" min="50" max="1000" step="10" <?php echo esc_attr($height_disabled); ?>>
                            <span>px</span>
                        </div>
                    </td>
                    <td class="column-show-link">
                        <input type="checkbox" name="runthings_ttc_show_links[]" value="<?php echo esc_attr($taxonomy->name); ?>" <?php echo esc_attr($show_link_checked); ?> <?php echo esc_attr($link_disabled); ?>>
                    </td>
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
                    <th scope="col" class="manage-column column-height">
                        <span><?php esc_html_e('Height', 'runthings-taxonomy-tags-to-checkboxes'); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-show-link">
                        <span><?php esc_html_e('Show Edit Link', 'runthings-taxonomy-tags-to-checkboxes'); ?></span>
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
        $taxonomies   = get_taxonomies( [], 'objects' );
        $user_count   = 0;
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