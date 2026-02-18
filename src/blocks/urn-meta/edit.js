/**
 * Urn meta block — editor component.
 *
 * Price field with Google Sheets lock support.
 */
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { TextControl, Notice } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'hk-fs-meta-block hk-fs-product-meta',
	} );

	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const isSheetsManaged = window.hkFsBlockData?.isSheetsManaged || false;

	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	return (
		<div { ...blockProps }>
			<h3 className="hk-fs-meta-block__title">
				{ __( 'Urn Details', 'hk-funeral-suite' ) }
			</h3>

			<div className="hk-fs-meta-block__fields hk-fs-meta-block__fields--single">
				<TextControl
					label={ __( 'Price ($)', 'hk-funeral-suite' ) }
					value={ meta?._hk_fs_urn_price || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_urn_price', value )
					}
					disabled={ isSheetsManaged }
					help={ __(
						'Enter a numeric price (e.g., 1295.00) or text like "P.O.A."',
						'hk-funeral-suite'
					) }
				/>
			</div>

			{ isSheetsManaged && (
				<Notice status="warning" isDismissible={ false }>
					<strong>
						{ __( 'Managed via Google Sheets', 'hk-funeral-suite' ) }
					</strong>
					<br />
					{ __(
						'Price is managed through Google Sheets integration and cannot be modified here.',
						'hk-funeral-suite'
					) }
				</Notice>
			) }
		</div>
	);
}
