<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup_Candidate_Scanner {
    const CACHE_TTL = 900;

    /**
     * Scan selected taxonomies for suspect numeric-name terms.
     *
     * @param array $taxonomy_slugs Taxonomy slugs.
     * @return array
     */
    public function scan( array $taxonomy_slugs ) {
        $taxonomies = $this->normalize_taxonomies( $taxonomy_slugs );

        if ( empty( $taxonomies ) ) {
            return $this->build_result( [] );
        }

        $cache_key = $this->build_cache_key( $taxonomies );
        $cached = get_transient( $cache_key );

        if ( is_array( $cached ) && isset( $cached['candidates'] ) && is_array( $cached['candidates'] ) ) {
            $cached['fingerprint'] = $this->build_fingerprint( $cached['candidates'] );
            if ( ! isset( $cached['summary'] ) || ! is_array( $cached['summary'] ) ) {
                $cached['summary'] = $this->build_summary( $cached['candidates'] );
            }
            return $cached;
        }

        $candidates = [];

        foreach ( $taxonomies as $taxonomy ) {
            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object || $taxonomy_object->hierarchical ) {
                continue;
            }

            $terms = get_terms(
                [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                ]
            );

            if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                if ( ! $term instanceof \WP_Term ) {
                    continue;
                }

                if ( absint( $term->count ) > 0 ) {
                    continue;
                }

                if ( ! preg_match( '/^\d+$/', (string) $term->name ) ) {
                    continue;
                }

                $mapped_term_id = absint( $term->name );
                if ( $mapped_term_id <= 0 || $mapped_term_id === absint( $term->term_id ) ) {
                    continue;
                }

                $mapped_term = get_term( $mapped_term_id, $taxonomy );
                if ( ! $mapped_term || is_wp_error( $mapped_term ) ) {
                    continue;
                }

                $candidates[] = [
                    'taxonomy' => $taxonomy,
                    'taxonomy_label' => $taxonomy_object->label ? (string) $taxonomy_object->label : $taxonomy,
                    'candidate_term_id' => absint( $term->term_id ),
                    'candidate_name' => (string) $term->name,
                    'candidate_count' => absint( $term->count ),
                    'mapped_term_id' => absint( $mapped_term->term_id ),
                    'mapped_term_name' => (string) $mapped_term->name,
                ];
            }
        }

        usort(
            $candidates,
            static function ( $left, $right ) {
                $taxonomy_compare = strcmp( (string) $left['taxonomy'], (string) $right['taxonomy'] );
                if ( 0 !== $taxonomy_compare ) {
                    return $taxonomy_compare;
                }

                return strcmp( (string) $left['candidate_name'], (string) $right['candidate_name'] );
            }
        );

        $result = $this->build_result( $candidates );
        set_transient( $cache_key, $result, self::CACHE_TTL );

        return $result;
    }

    /**
     * @param array $taxonomy_slugs Taxonomy slugs.
     */
    public function clear_cache( array $taxonomy_slugs ) {
        $taxonomies = $this->normalize_taxonomies( $taxonomy_slugs );
        if ( empty( $taxonomies ) ) {
            return;
        }

        delete_transient( $this->build_cache_key( $taxonomies ) );
    }

    /**
     * @param array $candidates Candidate rows.
     * @return string
     */
    public function build_fingerprint( array $candidates ) {
        $pairs = array_map(
            static function ( $candidate ) {
                return sprintf(
                    '%s:%d:%d',
                    isset( $candidate['taxonomy'] ) ? sanitize_key( (string) $candidate['taxonomy'] ) : '',
                    isset( $candidate['candidate_term_id'] ) ? absint( $candidate['candidate_term_id'] ) : 0,
                    isset( $candidate['mapped_term_id'] ) ? absint( $candidate['mapped_term_id'] ) : 0
                );
            },
            $candidates
        );

        sort( $pairs, SORT_STRING );

        return hash( 'sha256', implode( '|', $pairs ) );
    }

    /**
     * @param array $taxonomy_slugs Taxonomy slugs.
     * @return array
     */
    private function normalize_taxonomies( array $taxonomy_slugs ) {
        $normalized = [];

        foreach ( $taxonomy_slugs as $taxonomy_slug ) {
            $taxonomy = sanitize_key( (string) $taxonomy_slug );
            if ( '' === $taxonomy ) {
                continue;
            }

            $taxonomy_object = get_taxonomy( $taxonomy );
            if ( ! $taxonomy_object || $taxonomy_object->hierarchical ) {
                continue;
            }

            $normalized[] = $taxonomy;
        }

        $normalized = array_values( array_unique( $normalized ) );
        sort( $normalized, SORT_STRING );

        return $normalized;
    }

    /**
     * @param array $taxonomies Normalized taxonomy slugs.
     * @return string
     */
    private function build_cache_key( array $taxonomies ) {
        return 'runthings_ttc_cleanup_scan_' . md5( implode( '|', $taxonomies ) );
    }

    /**
     * @param array $candidates Candidate rows.
     * @return array
     */
    private function build_result( array $candidates ) {
        return [
            'candidates' => $candidates,
            'fingerprint' => $this->build_fingerprint( $candidates ),
            'summary' => $this->build_summary( $candidates ),
        ];
    }

    /**
     * @param array $candidates Candidate rows.
     * @return array
     */
    private function build_summary( array $candidates ) {
        $by_taxonomy = [];

        foreach ( $candidates as $candidate ) {
            $taxonomy = isset( $candidate['taxonomy'] ) ? sanitize_key( (string) $candidate['taxonomy'] ) : '';
            if ( '' === $taxonomy ) {
                continue;
            }

            if ( ! isset( $by_taxonomy[ $taxonomy ] ) ) {
                $by_taxonomy[ $taxonomy ] = 0;
            }

            $by_taxonomy[ $taxonomy ]++;
        }

        return [
            'total' => count( $candidates ),
            'by_taxonomy' => $by_taxonomy,
        ];
    }
}
