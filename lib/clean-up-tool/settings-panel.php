<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup_Settings_Panel {
    const ACTION_FIELD = 'runthings_ttc_cleanup_action';
    const NONCE_FIELD = 'runthings_ttc_cleanup_nonce';
    const NONCE_ACTION = 'runthings_ttc_cleanup_panel_action';
    const SELECTED_FIELD = 'runthings_ttc_cleanup_selected';
    const CONFIRM_FIELD = 'runthings_ttc_cleanup_confirm';
    const FLASH_KEY_PREFIX = 'runthings_ttc_cleanup_flash_';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Cleanup_Candidate_Scanner
     */
    private $scanner;

    /**
     * @var Cleanup_Term_Deleter
     */
    private $deleter;

    /**
     * @param Config                    $config  Shared plugin config.
     * @param Cleanup_Candidate_Scanner $scanner Candidate scanner.
     * @param Cleanup_Term_Deleter      $deleter Candidate deleter.
     */
    public function __construct( Config $config, Cleanup_Candidate_Scanner $scanner, Cleanup_Term_Deleter $deleter ) {
        $this->config = $config;
        $this->scanner = $scanner;
        $this->deleter = $deleter;
    }

    public function register_hooks() {
        add_action( 'admin_init', [ $this, 'maybe_handle_actions' ] );
    }

    /**
     * Handle panel form actions.
     */
    public function maybe_handle_actions() {
        if ( ! is_admin() || ! $this->is_settings_page_request() || ! $this->current_user_can_manage_cleanup() ) {
            return;
        }

        if ( ! isset( $_POST[ self::ACTION_FIELD ] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST[ self::ACTION_FIELD ] ) );
        if ( ! in_array( $action, [ 'refresh', 'delete' ], true ) ) {
            return;
        }

        $nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            $this->set_flash( [
                'type' => 'error',
                'message' => __( 'Cleanup action failed nonce verification.', 'runthings-taxonomy-tags-to-checkboxes' ),
            ] );
            $this->redirect_to_settings_page();
        }

        $taxonomies = $this->get_scope_taxonomies();

        if ( 'refresh' === $action ) {
            $this->scanner->clear_cache( $taxonomies );
            $scan = $this->scanner->scan( $taxonomies );
            $total = isset( $scan['summary']['total'] ) ? absint( $scan['summary']['total'] ) : 0;
            $message = sprintf(
                /* translators: %d: number of candidates found. */
                _n(
                    'Scan refreshed. %d suspect term currently matches the cleanup criteria.',
                    'Scan refreshed. %d suspect terms currently match the cleanup criteria.',
                    $total,
                    'runthings-taxonomy-tags-to-checkboxes'
                ),
                $total
            );

            $this->set_flash( [
                'type' => 'success',
                'message' => $message,
            ] );
            $this->redirect_to_settings_page();
        }

        $selected_rows = isset( $_POST[ self::SELECTED_FIELD ] ) && is_array( $_POST[ self::SELECTED_FIELD ] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ self::SELECTED_FIELD ] ) )
            : [];

        $confirmed = isset( $_POST[ self::CONFIRM_FIELD ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ self::CONFIRM_FIELD ] ) );
        if ( ! $confirmed ) {
            $this->set_flash( [
                'type' => 'error',
                'message' => __( 'Please confirm before deleting selected candidates.', 'runthings-taxonomy-tags-to-checkboxes' ),
            ] );
            $this->redirect_to_settings_page();
        }

        if ( empty( $selected_rows ) ) {
            $this->set_flash( [
                'type' => 'error',
                'message' => __( 'No candidates were selected for deletion.', 'runthings-taxonomy-tags-to-checkboxes' ),
            ] );
            $this->redirect_to_settings_page();
        }

        $this->scanner->clear_cache( $taxonomies );
        $scan_result = $this->scanner->scan( $taxonomies );
        $current_candidates = isset( $scan_result['candidates'] ) && is_array( $scan_result['candidates'] ) ? $scan_result['candidates'] : [];

        $delete_result = $this->deleter->delete_selected( $selected_rows, $current_candidates );

        $this->scanner->clear_cache( $taxonomies );

        $summary_message = sprintf(
            /* translators: 1: deleted count, 2: skipped count, 3: error count. */
            __( 'Cleanup complete. Deleted: %1$d, Skipped: %2$d, Errors: %3$d.', 'runthings-taxonomy-tags-to-checkboxes' ),
            isset( $delete_result['counts']['deleted'] ) ? absint( $delete_result['counts']['deleted'] ) : 0,
            isset( $delete_result['counts']['skipped'] ) ? absint( $delete_result['counts']['skipped'] ) : 0,
            isset( $delete_result['counts']['errors'] ) ? absint( $delete_result['counts']['errors'] ) : 0
        );

        $this->set_flash( [
            'type' => 'success',
            'message' => $summary_message,
            'result' => $delete_result,
        ] );

        $this->redirect_to_settings_page();
    }

    /**
     * Render cleanup panel under settings grid.
     */
    public function render_panel() {
        if ( ! $this->current_user_can_manage_cleanup() ) {
            return;
        }

        $action_url = admin_url( 'options-general.php?page=runthings-taxonomy-options#runthings-ttc-cleanup' );
        $scope_taxonomies = $this->get_scope_taxonomies();
        $scan_result = $this->scanner->scan( $scope_taxonomies );
        $candidates = isset( $scan_result['candidates'] ) && is_array( $scan_result['candidates'] ) ? $scan_result['candidates'] : [];

        $flash = $this->get_flash();

        if ( is_array( $flash ) ) {
            $notice_class = ( isset( $flash['type'] ) && 'error' === $flash['type'] ) ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html( (string) $flash['message'] ) . '</p></div>';

            if ( isset( $flash['result'] ) && is_array( $flash['result'] ) ) {
                $this->render_result_details( $flash['result'] );
            }
        }

        if ( empty( $candidates ) ) {
            return;
        }

        echo '<div id="runthings-ttc-cleanup" class="runthings-ttc-cleanup-panel">';
        echo '<h2>' . esc_html__( 'Suspect Numeric Terms Cleanup', 'runthings-taxonomy-tags-to-checkboxes' ) . '</h2>';
        echo '<p>' . esc_html__( 'A bug existed which might have made some unwanted empty terms that just have the title and slug of another terms id. This tool helps you tidy up any issues that might have been created.', 'runthings-taxonomy-tags-to-checkboxes' ) . '</p>';
        echo '<p>' . esc_html__( 'These terms match a strict detection pattern: selected taxonomy, numeric-only name, mapped to another term ID in the same taxonomy, and zero assignments. Review carefully before deleting.', 'runthings-taxonomy-tags-to-checkboxes' ) . '</p>';

        echo '<form method="post" action="' . esc_url( $action_url ) . '">';
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" class="runthings-ttc-cleanup-select-all" /></th>';
        echo '<th>' . esc_html__( 'Taxonomy', 'runthings-taxonomy-tags-to-checkboxes' ) . '</th>';
        echo '<th>' . esc_html__( 'Suspect Term', 'runthings-taxonomy-tags-to-checkboxes' ) . '</th>';
        echo '<th>' . esc_html__( 'Mapped Existing Term', 'runthings-taxonomy-tags-to-checkboxes' ) . '</th>';
        echo '<th>' . esc_html__( 'Count', 'runthings-taxonomy-tags-to-checkboxes' ) . '</th>';
        echo '<th>' . esc_html__( 'Links', 'runthings-taxonomy-tags-to-checkboxes' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $candidates as $candidate ) {
            $candidate_key = $this->build_candidate_key( $candidate );
            if ( '' === $candidate_key ) {
                continue;
            }

            $taxonomy = $candidate['taxonomy'];
            $candidate_term_id = absint( $candidate['candidate_term_id'] );
            $mapped_term_id = absint( $candidate['mapped_term_id'] );

            $taxonomy_terms_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
            $candidate_edit_url = admin_url( 'term.php?taxonomy=' . $taxonomy . '&tag_ID=' . $candidate_term_id );
            $mapped_edit_url = admin_url( 'term.php?taxonomy=' . $taxonomy . '&tag_ID=' . $mapped_term_id );

            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="' . esc_attr( self::SELECTED_FIELD ) . '[]" value="' . esc_attr( $candidate_key ) . '" /></th>';
            echo '<td>' . esc_html( (string) $candidate['taxonomy_label'] ) . ' <code>' . esc_html( $taxonomy ) . '</code></td>';
            echo '<td><strong>' . esc_html( (string) $candidate['candidate_name'] ) . '</strong> <code>#' . esc_html( (string) $candidate_term_id ) . '</code></td>';
            echo '<td><strong>' . esc_html( (string) $candidate['mapped_term_name'] ) . '</strong> <code>#' . esc_html( (string) $mapped_term_id ) . '</code></td>';
            echo '<td>' . esc_html( (string) absint( $candidate['candidate_count'] ) ) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( $taxonomy_terms_url ) . '">' . esc_html__( 'All terms', 'runthings-taxonomy-tags-to-checkboxes' ) . '</a>';
            echo ' | ';
            echo '<a href="' . esc_url( $candidate_edit_url ) . '">' . esc_html__( 'Suspect term', 'runthings-taxonomy-tags-to-checkboxes' ) . '</a>';
            echo ' | ';
            echo '<a href="' . esc_url( $mapped_edit_url ) . '">' . esc_html__( 'Mapped term', 'runthings-taxonomy-tags-to-checkboxes' ) . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:12px;">';
        echo '<label><input type="checkbox" name="' . esc_attr( self::CONFIRM_FIELD ) . '" value="1" /> ';
        echo esc_html__( 'I understand this will permanently delete selected suspect terms.', 'runthings-taxonomy-tags-to-checkboxes' );
        echo '</label>';
        echo '</p>';

        echo '<p class="submit">';
        echo '<button type="submit" name="' . esc_attr( self::ACTION_FIELD ) . '" value="refresh" class="button">' . esc_html__( 'Refresh Candidates', 'runthings-taxonomy-tags-to-checkboxes' ) . '</button> ';
        echo '<button type="submit" name="' . esc_attr( self::ACTION_FIELD ) . '" value="delete" class="button button-primary">' . esc_html__( 'Delete Selected Candidates', 'runthings-taxonomy-tags-to-checkboxes' ) . '</button>';
        echo '</p>';

        echo '</form>';
        echo '</div>';

        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded", function(){ var toggle=document.querySelector(".runthings-ttc-cleanup-select-all"); if(!toggle){return;} toggle.addEventListener("change", function(){ var boxes=document.querySelectorAll("input[name=\"' . esc_js( self::SELECTED_FIELD ) . '[]\"]"); boxes.forEach(function(box){ box.checked = toggle.checked; }); }); });';
        echo '</script>';
    }

    /**
     * @param array $result Delete result payload.
     */
    private function render_result_details( array $result ) {
        $skipped = isset( $result['skipped'] ) && is_array( $result['skipped'] ) ? $result['skipped'] : [];
        $errors = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : [];

        if ( empty( $skipped ) && empty( $errors ) ) {
            return;
        }

        echo '<div class="notice notice-info"><p>' . esc_html__( 'Detailed cleanup results:', 'runthings-taxonomy-tags-to-checkboxes' ) . '</p><ul>';

        foreach ( $skipped as $item ) {
            $reason = isset( $item['reason'] ) ? (string) $item['reason'] : __( 'Skipped.', 'runthings-taxonomy-tags-to-checkboxes' );
            $context = $this->format_result_item_context( $item );
            $message = '' !== $context ? $context . ': ' . $reason : $reason;
            echo '<li>' . esc_html__( 'Skipped:', 'runthings-taxonomy-tags-to-checkboxes' ) . ' ' . esc_html( $message ) . '</li>';
        }

        foreach ( $errors as $item ) {
            $message = isset( $item['message'] ) ? (string) $item['message'] : __( 'Error.', 'runthings-taxonomy-tags-to-checkboxes' );
            $context = $this->format_result_item_context( $item );
            $error_text = '' !== $context ? $context . ': ' . $message : $message;
            echo '<li>' . esc_html__( 'Error:', 'runthings-taxonomy-tags-to-checkboxes' ) . ' ' . esc_html( $error_text ) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * @param array $item Result item payload.
     * @return string
     */
    private function format_result_item_context( array $item ) {
        if ( isset( $item['candidate'] ) && is_array( $item['candidate'] ) ) {
            $candidate = $item['candidate'];
            $taxonomy = isset( $candidate['taxonomy'] ) ? sanitize_key( (string) $candidate['taxonomy'] ) : '';
            $candidate_term_id = isset( $candidate['candidate_term_id'] ) ? absint( $candidate['candidate_term_id'] ) : 0;
            $mapped_term_id = isset( $candidate['mapped_term_id'] ) ? absint( $candidate['mapped_term_id'] ) : 0;

            if ( '' !== $taxonomy && $candidate_term_id > 0 ) {
                return sprintf(
                    '%s #%d -> #%d',
                    $taxonomy,
                    $candidate_term_id,
                    $mapped_term_id
                );
            }
        }

        if ( ! isset( $item['key'] ) ) {
            return '';
        }

        $key = (string) $item['key'];
        if ( ! preg_match( '/^([a-z0-9_\\-]+):(\\d+):(\\d+)$/', $key, $matches ) ) {
            return '';
        }

        return sprintf(
            '%s #%d -> #%d',
            sanitize_key( $matches[1] ),
            absint( $matches[2] ),
            absint( $matches[3] )
        );
    }

    private function redirect_to_settings_page() {
        wp_safe_redirect( admin_url( 'options-general.php?page=runthings-taxonomy-options#runthings-ttc-cleanup' ) );
        exit;
    }

    /**
     * @return bool
     */
    private function is_settings_page_request() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for admin page routing.
        if ( ! isset( $_GET['page'] ) ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for admin page routing.
        return 'runthings-taxonomy-options' === sanitize_key( wp_unslash( $_GET['page'] ) );
    }

    /**
     * @return bool
     */
    private function current_user_can_manage_cleanup() {
        $capability = apply_filters( 'runthings_ttc_cleanup_capability', 'manage_options' );

        return current_user_can( is_string( $capability ) ? $capability : 'manage_options' );
    }

    /**
     * @return array
     */
    private function get_scope_taxonomies() {
        $taxonomies = $this->config->get_selected_taxonomies();

        $taxonomies = apply_filters( 'runthings_ttc_cleanup_taxonomies', $taxonomies, $this->config );

        return is_array( $taxonomies ) ? $taxonomies : [];
    }

    /**
     * @param array $candidate Candidate row.
     * @return string
     */
    private function build_candidate_key( array $candidate ) {
        $taxonomy = isset( $candidate['taxonomy'] ) ? sanitize_key( (string) $candidate['taxonomy'] ) : '';
        $candidate_term_id = isset( $candidate['candidate_term_id'] ) ? absint( $candidate['candidate_term_id'] ) : 0;
        $mapped_term_id = isset( $candidate['mapped_term_id'] ) ? absint( $candidate['mapped_term_id'] ) : 0;

        if ( '' === $taxonomy || $candidate_term_id <= 0 || $mapped_term_id <= 0 ) {
            return '';
        }

        return sprintf( '%s:%d:%d', $taxonomy, $candidate_term_id, $mapped_term_id );
    }

    /**
     * @param array $flash Flash payload.
     */
    private function set_flash( array $flash ) {
        set_transient( self::FLASH_KEY_PREFIX . get_current_user_id(), $flash, 60 );
    }

    /**
     * @return array|null
     */
    private function get_flash() {
        $key = self::FLASH_KEY_PREFIX . get_current_user_id();
        $flash = get_transient( $key );
        if ( false === $flash ) {
            return null;
        }

        delete_transient( $key );

        return is_array( $flash ) ? $flash : null;
    }

}
