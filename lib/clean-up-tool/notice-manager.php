<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup_Notice_Manager {
    const DISMISS_FLAG = 'runthings_ttc_cleanup_dismiss';
    const DISMISS_NONCE_ACTION = 'runthings_ttc_cleanup_dismiss_notice';
    const DISMISSED_FINGERPRINT_META_KEY = 'runthings_ttc_cleanup_notice_dismissed_fingerprint';
    const DISMISSED_AT_META_KEY = 'runthings_ttc_cleanup_notice_dismissed_at';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Cleanup_Candidate_Scanner
     */
    private $scanner;

    /**
     * @param Config                    $config  Shared plugin config.
     * @param Cleanup_Candidate_Scanner $scanner Candidate scanner.
     */
    public function __construct( Config $config, Cleanup_Candidate_Scanner $scanner ) {
        $this->config = $config;
        $this->scanner = $scanner;
    }

    public function register_hooks() {
        add_action( 'admin_init', [ $this, 'maybe_handle_dismissal' ] );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    }

    /**
     * Handle notice dismissal request.
     */
    public function maybe_handle_dismissal() {
        if ( ! is_admin() || ! $this->current_user_can_manage_cleanup() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified immediately below before any state changes.
        $dismiss_flag = isset( $_GET[ self::DISMISS_FLAG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::DISMISS_FLAG ] ) ) : '';
        if ( '1' !== $dismiss_flag ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::DISMISS_NONCE_ACTION ) ) {
            return;
        }

        $scan_result = $this->scanner->scan( $this->get_scope_taxonomies() );
        $fingerprint = isset( $scan_result['fingerprint'] ) ? (string) $scan_result['fingerprint'] : '';

        if ( '' !== $fingerprint ) {
            update_user_meta( get_current_user_id(), self::DISMISSED_FINGERPRINT_META_KEY, $fingerprint );
            update_user_meta( get_current_user_id(), self::DISMISSED_AT_META_KEY, time() );
        }

        $redirect_url = remove_query_arg( [ self::DISMISS_FLAG, '_wpnonce' ] );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Render admin notice when candidates exist and notice is not dismissed.
     */
    public function render_notice() {
        if ( ! is_admin() || ! $this->current_user_can_manage_cleanup() ) {
            return;
        }

        $scan_result = $this->scanner->scan( $this->get_scope_taxonomies() );
        $summary = isset( $scan_result['summary'] ) && is_array( $scan_result['summary'] ) ? $scan_result['summary'] : [ 'total' => 0 ];
        $candidate_total = isset( $summary['total'] ) ? absint( $summary['total'] ) : 0;

        if ( $candidate_total <= 0 ) {
            return;
        }

        $fingerprint = isset( $scan_result['fingerprint'] ) ? (string) $scan_result['fingerprint'] : '';
        if ( '' !== $fingerprint && $this->is_notice_dismissed( $fingerprint ) ) {
            return;
        }

        $settings_url = admin_url( 'options-general.php?page=runthings-taxonomy-options#runthings-ttc-cleanup' );
        $dismiss_url = wp_nonce_url(
            add_query_arg(
                [
                    self::DISMISS_FLAG => '1',
                ]
            ),
            self::DISMISS_NONCE_ACTION
        );

        $plugin_label = '<strong>' . esc_html__( 'Taxonomy Tags to Checkboxes', 'runthings-taxonomy-tags-to-checkboxes' ) . '</strong>';
        $message = sprintf(
            /* translators: 1: plugin label, 2: number of suspect terms. */
            _n(
                '%1$s: Found %2$d suspect numeric term that may have been created by a previous bug.',
                '%1$s: Found %2$d suspect numeric terms that may have been created by a previous bug.',
                $candidate_total,
                'runthings-taxonomy-tags-to-checkboxes'
            ),
            $plugin_label,
            $candidate_total
        );

        echo '<div class="notice notice-warning">';
        echo '<p>' . wp_kses( $message, [ 'strong' => [] ] ) . ' ';
        echo '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Review candidates', 'runthings-taxonomy-tags-to-checkboxes' ) . '</a>';
        echo ' | ';
        echo '<a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss this notice', 'runthings-taxonomy-tags-to-checkboxes' ) . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * @param string $fingerprint Candidate set fingerprint.
     * @return bool
     */
    private function is_notice_dismissed( $fingerprint ) {
        $dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_FINGERPRINT_META_KEY, true );

        return is_string( $dismissed ) && hash_equals( $dismissed, $fingerprint );
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
     * @return bool
     */
    private function current_user_can_manage_cleanup() {
        $capability = apply_filters( 'runthings_ttc_cleanup_capability', 'manage_options' );

        return current_user_can( is_string( $capability ) ? $capability : 'manage_options' );
    }
}
