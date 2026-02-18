/**
 * Pricing Package meta block — editor component.
 *
 * Fields: price, intro, display order.
 * Price supports Google Sheets lock.
 */
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { TextControl, TextareaControl, Notice } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'hk-fs-meta-block hk-fs-package-meta',
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
				{ __( 'Package Details', 'hk-funeral-suite' ) }
			</h3>

			<div className="hk-fs-meta-block__fields">
				<TextControl
					label={ __( 'Price ($)', 'hk-funeral-suite' ) }
					value={ meta?._hk_fs_package_price || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_package_price', value )
					}
					disabled={ isSheetsManaged }
					help={ __(
						'Enter a numeric price (e.g., 4995.00) or text like "P.O.A."',
						'hk-funeral-suite'
					) }
				/>

				<TextControl
					label={ __( 'Display Order', 'hk-funeral-suite' ) }
					type="number"
					step="1"
					min="0"
					value={
						meta?._hk_fs_package_order !== undefined
							? String( meta._hk_fs_package_order )
							: '10'
					}
					onChange={ ( value ) =>
						updateMeta(
							'_hk_fs_package_order',
							value ? parseInt( value, 10 ) : 0
						)
					}
					help={ __(
						'Lower numbers are displayed first.',
						'hk-funeral-suite'
					) }
				/>
			</div>

			<TextareaControl
				label={ __( 'Short Introduction', 'hk-funeral-suite' ) }
				value={ meta?._hk_fs_package_intro || '' }
				onChange={ ( value ) =>
					updateMeta( '_hk_fs_package_intro', value )
				}
				rows={ 3 }
			/>

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
