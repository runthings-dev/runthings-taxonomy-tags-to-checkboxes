<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup_Term_Deleter {
    /**
     * Delete selected suspect terms after re-validation.
     *
     * @param array $selected_rows    Candidate keys in taxonomy:term_id:mapped_id format.
     * @param array $current_candidates Current scanner candidates.
     * @return array
     */
    public function delete_selected( array $selected_rows, array $current_candidates ) {
        $selected_rows = array_values( array_unique( array_filter( array_map( [ $this, 'sanitize_candidate_key' ], $selected_rows ) ) ) );
        $indexed_candidates = $this->index_candidates( $current_candidates );

        $deleted = [];
        $skipped = [];
        $errors = [];

        foreach ( $selected_rows as $key ) {
            if ( ! isset( $indexed_candidates[ $key ] ) ) {
                $skipped[] = [
                    'key' => $key,
                    'reason' => __( 'Candidate no longer matches the scanner results.', 'runthings-taxonomy-tags-to-checkboxes' ),
                ];
                continue;
            }

            $candidate = $indexed_candidates[ $key ];
            $taxonomy = $candidate['taxonomy'];
            $taxonomy_object = get_taxonomy( $taxonomy );

            if ( ! $taxonomy_object ) {
                $skipped[] = [
                    'key' => $key,
                    'candidate' => $candidate,
                    'reason' => __( 'Taxonomy no longer exists.', 'runthings-taxonomy-tags-to-checkboxes' ),
                ];
                continue;
            }

            if ( ! current_user_can( $taxonomy_object->cap->delete_terms ) ) {
                $errors[] = [
                    'key' => $key,
                    'candidate' => $candidate,
                    'message' => __( 'You do not have permission to delete terms in this taxonomy.', 'runthings-taxonomy-tags-to-checkboxes' ),
                ];
                continue;
            }

            $revalidation = $this->revalidate_candidate( $candidate );
            if ( is_wp_error( $revalidation ) ) {
                $skipped[] = [
                    'key' => $key,
                    'candidate' => $candidate,
                    'reason' => $revalidation->get_error_message(),
                ];
                continue;
            }

            $deleted_term = wp_delete_term( $candidate['candidate_term_id'], $taxonomy );
            if ( is_wp_error( $deleted_term ) ) {
                $errors[] = [
                    'key' => $key,
                    'candidate' => $candidate,
                    'message' => $deleted_term->get_error_message(),
                ];
                continue;
            }

            if ( false === $deleted_term ) {
                $errors[] = [
                    'key' => $key,
                    'candidate' => $candidate,
                    'message' => __( 'Term could not be deleted.', 'runthings-taxonomy-tags-to-checkboxes' ),
                ];
                continue;
            }

            $deleted[] = [
                'key' => $key,
                'candidate' => $candidate,
            ];
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors,
            'counts' => [
                'selected' => count( $selected_rows ),
                'deleted' => count( $deleted ),
                'skipped' => count( $skipped ),
                'errors' => count( $errors ),
            ],
        ];
    }

    /**
     * @param array $candidate Candidate row.
     * @return true|\WP_Error
     */
    private function revalidate_candidate( array $candidate ) {
        $taxonomy = $candidate['taxonomy'];
        $candidate_term_id = absint( $candidate['candidate_term_id'] );
        $mapped_term_id = absint( $candidate['mapped_term_id'] );

        if ( $candidate_term_id <= 0 || $mapped_term_id <= 0 || $candidate_term_id === $mapped_term_id ) {
            return new \WP_Error( 'invalid_ids', __( 'Candidate IDs are no longer valid.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        $candidate_term = get_term( $candidate_term_id, $taxonomy );
        if ( ! $candidate_term || is_wp_error( $candidate_term ) ) {
            return new \WP_Error( 'candidate_missing', __( 'Candidate term no longer exists.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        $mapped_term = get_term( $mapped_term_id, $taxonomy );
        if ( ! $mapped_term || is_wp_error( $mapped_term ) ) {
            return new \WP_Error( 'mapped_missing', __( 'Mapped target term no longer exists.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        if ( ! preg_match( '/^\d+$/', (string) $candidate_term->name ) ) {
            return new \WP_Error( 'name_not_numeric', __( 'Candidate term name is no longer numeric.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        if ( (int) $candidate_term->name !== $mapped_term_id ) {
            return new \WP_Error( 'mapping_changed', __( 'Candidate term no longer maps to the target term ID.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        if ( absint( $candidate_term->count ) > 0 ) {
            return new \WP_Error( 'count_not_zero', __( 'Candidate term is now assigned to content.', 'runthings-taxonomy-tags-to-checkboxes' ) );
        }

        return true;
    }

    /**
     * @param string $key Candidate key.
     * @return string
     */
    private function sanitize_candidate_key( $key ) {
        $key = (string) $key;

        if ( ! preg_match( '/^([a-z0-9_\-]+):(\d+):(\d+)$/', $key, $matches ) ) {
            return '';
        }

        return sprintf( '%s:%d:%d', sanitize_key( $matches[1] ), absint( $matches[2] ), absint( $matches[3] ) );
    }

    /**
     * @param array $candidates Scanner candidates.
     * @return array
     */
    private function index_candidates( array $candidates ) {
        $indexed = [];

        foreach ( $candidates as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $key = $this->build_candidate_key( $candidate );
            if ( '' === $key ) {
                continue;
            }

            $indexed[ $key ] = $candidate;
        }

        return $indexed;
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
}
