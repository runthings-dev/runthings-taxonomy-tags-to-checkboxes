/**
 * Native Gutenberg taxonomy panel with checkboxes.
 */
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Flex,
	FlexItem,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useState } from '@wordpress/element';
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
		allowInlineAdd,
		canCreateTerms,
		editLinkLabel,
		searchMode,
		searchThreshold,
	} = taxonomy;
	const [ newTermName, setNewTermName ] = useState( '' );
	const [ isCreating, setIsCreating ] = useState( false );
	const [ searchQuery, setSearchQuery ] = useState( '' );

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
	const { saveEntityRecord, invalidateResolution } = useDispatch( coreStore );
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const termAttribute = restBase || slug;
	const normalizeTermName = ( value ) =>
		( value || '' ).trim().toLocaleLowerCase();
	const getExistingTermIdByName = ( name ) => {
		const normalizedName = normalizeTermName( name );
		const existing = terms.find(
			( term ) => normalizeTermName( term.name ) === normalizedName
		);
		return existing?.id || null;
	};
	const shouldShowSearch =
		'always' === searchMode ||
		( 'min_terms' === searchMode &&
			terms.length >= ( Number( searchThreshold ) || 20 ) );
	const normalizedSearchQuery = normalizeTermName( searchQuery );
	const filteredTerms = shouldShowSearch
		? terms.filter( ( term ) =>
				normalizeTermName( term.name ).includes( normalizedSearchQuery )
		  )
		: terms;

	const onToggle = ( termId, isChecked ) => {
		const next = isChecked
			? Array.from( new Set( [ ...selectedTermIds, termId ] ) )
			: selectedTermIds.filter( ( id ) => id !== termId );
		editPost( { [ termAttribute ]: next } );
	};

	const onAddTerm = async () => {
		const name = newTermName.trim();
		if ( ! name || isCreating ) {
			return;
		}

		const existingTermId = getExistingTermIdByName( name );
		if ( existingTermId ) {
			editPost( {
				[ termAttribute ]: Array.from(
					new Set( [ ...selectedTermIds, existingTermId ] )
				),
			} );
			setNewTermName( '' );
			return;
		}

		setIsCreating( true );
		try {
			const created = await saveEntityRecord(
				'taxonomy',
				slug,
				{ name },
				{ throwOnError: true }
			);

			if ( created && created.id ) {
				editPost( {
					[ termAttribute ]: Array.from(
						new Set( [ ...selectedTermIds, created.id ] )
					),
				} );
			}

			invalidateResolution?.( 'getEntityRecords', [
				'taxonomy',
				slug,
				{ per_page: 100, orderby: 'name', order: 'asc' },
			] );
			setNewTermName( '' );
		} catch ( error ) {
			const duplicateTermId = Number( error?.data?.term_id );
			if ( Number.isInteger( duplicateTermId ) && duplicateTermId > 0 ) {
				editPost( {
					[ termAttribute ]: Array.from(
						new Set( [ ...selectedTermIds, duplicateTermId ] )
					),
				} );
				setNewTermName( '' );
				return;
			}

			createErrorNotice(
				error?.message ||
					__(
						'Could not add the term.',
						'runthings-taxonomy-tags-to-checkboxes'
					),
				{ type: 'snackbar' }
			);
		} finally {
			setIsCreating( false );
		}
	};

	if ( ! isSupportedPostType ) {
		return null;
	}

	// Build container style from height settings.
	const containerStyle = {
		marginLeft: '-6px',
		marginTop: '-6px',
		overflow: 'auto',
		paddingLeft: '6px',
		paddingTop: '6px',
	};
	const safeSlug = slug.replace( /_/g, '-' );
	if ( maxHeight === 'none' ) {
		// "full" — no constraint
	} else if ( maxHeight ) {
		containerStyle.maxHeight = maxHeight;
	}

	return (
		<PluginDocumentSettingPanel
			name={ 'runthings-ttc-' + safeSlug }
			title={ label }
			className="runthings-ttc-panel"
		>
			{ isLoading && <Spinner /> }

			{ ! isLoading && (
				<Flex direction="column" align="stretch" gap={ 4 }>
					{ terms.length === 0 && (
						<p>
							{ __(
								'No terms available.',
								'runthings-taxonomy-tags-to-checkboxes'
							) }
						</p>
					) }

					{ terms.length > 0 && (
						<>
							{ shouldShowSearch && (
								<>
									<TextControl
										label={ __(
											'Search terms',
											'runthings-taxonomy-tags-to-checkboxes'
										) }
										type="search"
										value={ searchQuery }
										onChange={ setSearchQuery }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<p style={ { margin: 0 } }>
										{ sprintf(
											/* translators: 1: visible terms count, 2: total terms count */
											__(
												'Showing %1$d of %2$d terms',
												'runthings-taxonomy-tags-to-checkboxes'
											),
											filteredTerms.length,
											terms.length
										) }
									</p>
								</>
							) }

							{ shouldShowSearch &&
								filteredTerms.length === 0 && (
									<p>
										{ __(
											'No matching terms.',
											'runthings-taxonomy-tags-to-checkboxes'
										) }
									</p>
								) }

							<div
								style={ {
									...containerStyle,
									display: 'grid',
								} }
							>
								{ filteredTerms.map( ( term ) => (
									<CheckboxControl
										key={ term.id }
										label={ term.name }
										checked={ selectedTermIds.includes(
											term.id
										) }
										onChange={ ( checked ) =>
											onToggle( term.id, checked )
										}
									/>
								) ) }
							</div>
						</>
					) }

					{ allowInlineAdd && canCreateTerms && (
						<Flex align="flex-end">
							<FlexItem isBlock>
								<TextControl
									label={ __(
										'Add new term',
										'runthings-taxonomy-tags-to-checkboxes'
									) }
									value={ newTermName }
									onChange={ setNewTermName }
									disabled={ isCreating }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<Button
									variant="secondary"
									onClick={ onAddTerm }
									disabled={
										! newTermName.trim() || isCreating
									}
									isBusy={ isCreating }
									__next40pxDefaultSize
								>
									{ __(
										'Add',
										'runthings-taxonomy-tags-to-checkboxes'
									) }
								</Button>
							</FlexItem>
						</Flex>
					) }

					{ showEditLink && editUrl && (
						<ExternalLink href={ editUrl }>
							{ editLinkLabel ||
								__(
									'Add / Edit',
									'runthings-taxonomy-tags-to-checkboxes'
								) }
						</ExternalLink>
					) }
				</Flex>
			) }
		</PluginDocumentSettingPanel>
	);
}
