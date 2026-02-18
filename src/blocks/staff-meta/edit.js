/**
 * Team Member meta block — editor component.
 *
 * Uses useEntityProp to read/write post meta directly.
 * Fields: position, qualification, phone, email.
 * Also provides taxonomy selectors for location and job role.
 */
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { TextControl } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'hk-fs-meta-block hk-fs-staff-meta',
	} );

	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	return (
		<div { ...blockProps }>
			<h3 className="hk-fs-meta-block__title">
				{ __( 'Team Member Details', 'hk-funeral-suite' ) }
			</h3>

			<div className="hk-fs-meta-block__fields">
				<TextControl
					label={ __( 'Position / Job Title', 'hk-funeral-suite' ) }
					value={ meta?._hk_fs_staff_position || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_staff_position', value )
					}
				/>

				<TextControl
					label={ __( 'Qualification', 'hk-funeral-suite' ) }
					value={ meta?._hk_fs_staff_qualification || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_staff_qualification', value )
					}
				/>

				<TextControl
					label={ __( 'Phone', 'hk-funeral-suite' ) }
					type="tel"
					value={ meta?._hk_fs_staff_phone || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_staff_phone', value )
					}
				/>

				<TextControl
					label={ __( 'Email', 'hk-funeral-suite' ) }
					type="email"
					value={ meta?._hk_fs_staff_email || '' }
					onChange={ ( value ) =>
						updateMeta( '_hk_fs_staff_email', value )
					}
				/>
			</div>
		</div>
	);
}
