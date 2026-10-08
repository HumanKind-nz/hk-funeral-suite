/**
 * Product grid block — editor component.
 *
 * Settings in the sidebar; the canvas shows the server's own markup through
 * ServerSideRender, so the editor and the front end cannot differ.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

// Product types the block supports, in menu order.
const PRODUCT_TYPES = [ 'casket', 'urn', 'monument', 'keepsake' ];

function EmptyPreview( { label } ) {
	return (
		<Placeholder label={ label }>
			{ __(
				'No published products match these settings yet.',
				'hk-funeral-suite'
			) }
		</Placeholder>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		type,
		categories,
		groupByCategory,
		orderBy,
		columns,
		showImage,
		imageSize,
		showPrice,
		pricePrefix,
		priceSuffix,
		poaLabel,
		showDescription,
		headingLevel,
	} = attributes;

	const blockProps = useBlockProps();

	// Only product types enabled on this site are registered post types.
	const { allPostTypes, terms, imageSizes } = useSelect(
		( select ) => ( {
			allPostTypes: select( coreStore ).getPostTypes( { per_page: -1 } ),
			terms: select( coreStore ).getEntityRecords(
				'taxonomy',
				`hk_fs_${ type }_category`,
				{ per_page: -1, orderby: 'name', order: 'asc' }
			),
			imageSizes: select( 'core/block-editor' ).getSettings().imageSizes,
		} ),
		[ type ]
	);

	const typeOptions = ( allPostTypes || [] )
		.map( ( postType ) => ( {
			label: postType.name,
			value: postType.slug.replace( /^hk_fs_/, '' ),
		} ) )
		.filter( ( option ) => PRODUCT_TYPES.includes( option.value ) )
		.sort(
			( a, b ) =>
				PRODUCT_TYPES.indexOf( a.value ) -
				PRODUCT_TYPES.indexOf( b.value )
		);

	const toggleCategory = ( slug, checked ) => {
		const next = checked
			? [ ...categories, slug ]
			: categories.filter( ( item ) => item !== slug );
		setAttributes( { categories: next } );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Products', 'hk-funeral-suite' ) }>
					{ typeOptions.length > 0 && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Product type', 'hk-funeral-suite' ) }
							value={ type }
							options={ typeOptions }
							onChange={ ( value ) =>
								setAttributes( { type: value, categories: [] } )
							}
						/>
					) }
					<fieldset className="hk-product-grid-categories">
						<legend>
							{ __( 'Categories', 'hk-funeral-suite' ) }
						</legend>
						{ terms === null && <Spinner /> }
						{ terms && terms.length === 0 && (
							<p>
								{ __(
									'No categories yet.',
									'hk-funeral-suite'
								) }
							</p>
						) }
						{ terms &&
							terms.map( ( term ) => (
								<CheckboxControl
									__nextHasNoMarginBottom
									key={ term.id }
									label={ term.name }
									checked={ categories.includes( term.slug ) }
									onChange={ ( checked ) =>
										toggleCategory( term.slug, checked )
									}
								/>
							) ) }
						<p className="components-base-control__help">
							{ __(
								'Leave all unticked to show every category.',
								'hk-funeral-suite'
							) }
						</p>
					</fieldset>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Group by category', 'hk-funeral-suite' ) }
						checked={ groupByCategory }
						onChange={ ( value ) =>
							setAttributes( { groupByCategory: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Order', 'hk-funeral-suite' ) }
						value={ orderBy }
						options={ [
							{
								label: __(
									'Catalogue order',
									'hk-funeral-suite'
								),
								value: 'catalogue',
							},
							{
								label: __(
									'Price, lowest first',
									'hk-funeral-suite'
								),
								value: 'price',
							},
							{
								label: __( 'Name, A to Z', 'hk-funeral-suite' ),
								value: 'title',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orderBy: value } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'hk-funeral-suite' ) }
						help={ __(
							'The most columns on wide screens. Fewer show on smaller screens.',
							'hk-funeral-suite'
						) }
						value={ columns }
						min={ 1 }
						max={ 6 }
						onChange={ ( value ) =>
							setAttributes( { columns: value } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Cards', 'hk-funeral-suite' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show image', 'hk-funeral-suite' ) }
						checked={ showImage }
						onChange={ ( value ) =>
							setAttributes( { showImage: value } )
						}
					/>
					{ showImage && imageSizes?.length > 0 && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Image size', 'hk-funeral-suite' ) }
							value={ imageSize }
							options={ imageSizes.map( ( size ) => ( {
								label: size.name,
								value: size.slug,
							} ) ) }
							onChange={ ( value ) =>
								setAttributes( { imageSize: value } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show price', 'hk-funeral-suite' ) }
						checked={ showPrice }
						onChange={ ( value ) =>
							setAttributes( { showPrice: value } )
						}
					/>
					{ showPrice && (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Text before the price',
									'hk-funeral-suite'
								) }
								help={ __(
									'For example "From". Numeric prices only.',
									'hk-funeral-suite'
								) }
								value={ pricePrefix }
								onChange={ ( value ) =>
									setAttributes( { pricePrefix: value } )
								}
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Text after the price',
									'hk-funeral-suite'
								) }
								help={ __(
									'Numeric prices only.',
									'hk-funeral-suite'
								) }
								value={ priceSuffix }
								onChange={ ( value ) =>
									setAttributes( { priceSuffix: value } )
								}
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Price on application label',
									'hk-funeral-suite'
								) }
								help={ __(
									'Shown instead of a POA price. Leave blank for the default.',
									'hk-funeral-suite'
								) }
								placeholder={ __(
									'Price on application',
									'hk-funeral-suite'
								) }
								value={ poaLabel }
								onChange={ ( value ) =>
									setAttributes( { poaLabel: value } )
								}
							/>
						</>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Description', 'hk-funeral-suite' ) }
						value={ showDescription }
						options={ [
							{
								label: __( 'Full', 'hk-funeral-suite' ),
								value: 'full',
							},
							{
								label: __(
									'Short excerpt',
									'hk-funeral-suite'
								),
								value: 'excerpt',
							},
							{
								label: __( 'None', 'hk-funeral-suite' ),
								value: 'none',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { showDescription: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Product name heading level',
							'hk-funeral-suite'
						) }
						help={ __(
							'Category headings sit one level above.',
							'hk-funeral-suite'
						) }
						value={ String( headingLevel ) }
						options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
							label: sprintf(
								/* translators: %d: heading level, e.g. 3. */
								__( 'H%d', 'hk-funeral-suite' ),
								level
							),
							value: String( level ),
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( { headingLevel: Number( value ) } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="hk-funeral-suite/product-grid"
				attributes={ attributes }
				skipBlockSupportAttributes
				EmptyResponsePlaceholder={ () => (
					<EmptyPreview
						label={ __( 'Product Grid', 'hk-funeral-suite' ) }
					/>
				) }
			/>
		</div>
	);
}
