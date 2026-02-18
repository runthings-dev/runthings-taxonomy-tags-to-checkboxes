/**
 * Native Gutenberg taxonomy panel with checkboxes.
 */
import { CheckboxControl, ExternalLink, Spinner } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { __, sprintf } from '@wordpress/i18n';

export default function TtcTaxonomyPanel( { taxonomy } ) {
	const {
		slug,
		restBase,
		label,
		postTypes,
		maxHeight,
		showEditLink,
		editUrl,
	} = taxonomy;

	const currentPostType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const isSupportedPostType =
		!! currentPostType && postTypes.includes( currentPostType );

	// Fetch all terms for this taxonomy.
	const { terms, isLoading } = useSelect(
		( select ) => {
			const query = { per_page: 100, orderby: 'name', order: 'asc' };
			return {
				terms:
					( isSupportedPostType &&
						select( coreStore ).getEntityRecords(
							'taxonomy',
							slug,
							query
						) ) ||
					[],
				isLoading:
					isSupportedPostType &&
					select( coreStore ).isResolving( 'getEntityRecords', [
						'taxonomy',
						slug,
						query,
					] ),
			};
		},
		[ isSupportedPostType, slug ]
	);

	// Get the currently selected term IDs on this post.
	const selectedTermIds = useSelect(
		( select ) => {
			const ids =
				select( editorStore ).getEditedPostAttribute( restBase ) ||
				select( editorStore ).getEditedPostAttribute( slug ) ||
				[];
			return isSupportedPostType ? ids : [];
		},
		[ isSupportedPostType, restBase, slug ]
	);

	const { editPost } = useDispatch( editorStore );
	const termAttribute = restBase || slug;

	const onToggle = ( termId, isChecked ) => {
		const next = isChecked
			? Array.from( new Set( [ ...selectedTermIds, termId ] ) )
			: selectedTermIds.filter( ( id ) => id !== termId );
		editPost( { [ termAttribute ]: next } );
	};

	if ( ! isSupportedPostType ) {
		return null;
	}

	// Build container style from height settings.
	const containerStyle = {};
	const safeSlug = slug.replace( /_/g, '-' );
	if ( maxHeight === 'none' ) {
		// "full" — no constraint
	} else if ( maxHeight ) {
		containerStyle.maxHeight = maxHeight;
		containerStyle.overflowY = 'auto';
	}

	return (
		<PluginDocumentSettingPanel
			name={ 'runthings-ttc-' + safeSlug }
			title={ label }
			className="runthings-ttc-panel"
		>
			{ isLoading && <Spinner /> }

			{ ! isLoading && terms.length === 0 && (
				<p>
					{ __(
						'No terms available.',
						'runthings-taxonomy-tags-to-checkboxes'
					) }
				</p>
			) }

			{ ! isLoading && terms.length > 0 && (
				<div
					style={ {
						...containerStyle,
						display: 'grid',
					} }
				>
					{ terms.map( ( term ) => (
						<CheckboxControl
							key={ term.id }
							label={ term.name }
							checked={ selectedTermIds.includes( term.id ) }
							onChange={ ( checked ) =>
								onToggle( term.id, checked )
							}
						/>
					) ) }
				</div>
			) }

			{ showEditLink && editUrl && (
				<p style={ { marginTop: '8px' } }>
					<ExternalLink href={ editUrl }>
						{ label
							? sprintf(
									/* translators: %s: taxonomy label */
									__(
										'+ Add / Edit %s',
										'runthings-taxonomy-tags-to-checkboxes'
									),
									label
							  )
							: __(
									'+ Add / Edit',
									'runthings-taxonomy-tags-to-checkboxes'
							  ) }
					</ExternalLink>
				</p>
			) }
		</PluginDocumentSettingPanel>
	);
}
