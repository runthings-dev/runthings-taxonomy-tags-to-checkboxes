<?php

namespace RunthingsTaxonomyTagsToCheckboxes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Classic_Term_Checklist_Walker extends \Walker_Category_Checklist {
    /**
     * @var string
     */
    private $input_name;

    /**
     * @param string $input_name Checkbox input name without [] suffix.
     */
    public function __construct( $input_name ) {
        $this->input_name = $input_name;
    }

    /**
     * Output a checklist item using a custom input name so core does not
     * interpret numeric term IDs as term names for flat taxonomies.
     *
     * @param string   $output            Used to append additional content (passed by reference).
     * @param \WP_Term $data_object       The current term object.
     * @param int      $depth             Depth of the term in reference to parents.
     * @param array    $args              Checklist arguments.
     * @param int      $current_object_id Optional. ID of the current term.
     */
    public function start_el( &$output, $data_object, $depth = 0, $args = [], $current_object_id = 0 ) {
        $term = $data_object;
        $taxonomy = empty( $args['taxonomy'] ) ? 'category' : $args['taxonomy'];

        $args['popular_cats'] = ! empty( $args['popular_cats'] ) ? array_map( 'intval', $args['popular_cats'] ) : [];
        $args['selected_cats'] = ! empty( $args['selected_cats'] ) ? array_map( 'intval', $args['selected_cats'] ) : [];

        $class = in_array( $term->term_id, $args['popular_cats'], true ) ? ' class="popular-category"' : '';

        if ( ! empty( $args['list_only'] ) ) {
            $aria_checked = 'false';
            $inner_class  = 'category';

            if ( in_array( $term->term_id, $args['selected_cats'], true ) ) {
                $inner_class .= ' selected';
                $aria_checked = 'true';
            }

            $output .= "\n" . '<li' . $class . '>' .
                '<div class="' . $inner_class . '" data-term-id=' . $term->term_id .
                ' tabindex="0" role="checkbox" aria-checked="' . $aria_checked . '">' .
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter hook.
                esc_html( apply_filters( 'the_category', $term->name, '', '' ) ) . '</div>';
            return;
        }

        $is_selected         = in_array( $term->term_id, $args['selected_cats'], true );
        $is_disabled         = ! empty( $args['disabled'] );
        $li_element_id       = wp_unique_prefixed_id( "in-{$taxonomy}-{$term->term_id}-" );
        $checkbox_element_id = wp_unique_prefixed_id( "in-{$taxonomy}-{$term->term_id}-" );

        $output .= "\n<li id='" . esc_attr( $li_element_id ) . "'$class>" .
            '<label class="selectit"><input value="' . $term->term_id . '" type="checkbox" name="' . esc_attr( $this->input_name ) . '[]" id="' . esc_attr( $checkbox_element_id ) . '"' .
            checked( $is_selected, true, false ) .
            disabled( $is_disabled, true, false ) . ' /> ' .
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter hook.
            esc_html( apply_filters( 'the_category', $term->name, '', '' ) ) . '</label>';
    }
}
