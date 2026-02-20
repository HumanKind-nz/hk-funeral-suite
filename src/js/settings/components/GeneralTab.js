/**
 * General settings tab — CPT toggles and public visibility.
 *
 * @package HKFuneralSuite
 */
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	PanelRow,
	ToggleControl,
	Button,
} from '@wordpress/components';

const CPT_OPTIONS = [
	{ key: 'staff', label: __( 'Team Members', 'hk-funeral-suite' ) },
	{ key: 'packages', label: __( 'Pricing Packages', 'hk-funeral-suite' ) },
	{ key: 'caskets', label: __( 'Caskets', 'hk-funeral-suite' ) },
	{ key: 'urns', label: __( 'Urns', 'hk-funeral-suite' ) },
	{ key: 'monuments', label: __( 'Monuments', 'hk-funeral-suite' ) },
	{ key: 'keepsakes', label: __( 'Keepsakes', 'hk-funeral-suite' ) },
];

export default function GeneralTab( {
	settings,
	setSettings,
	saveSettings,
	isSaving,
} ) {
	const enabledCpts = settings.hk_fs_enabled_cpts || {};

	const updateCpt = ( key, value ) => {
		const updated = {
			...settings,
			hk_fs_enabled_cpts: {
				...enabledCpts,
				[ key ]: value,
			},
		};

		// If disabling a CPT, also disable its public visibility.
		if ( ! value ) {
			updated[ `hk_fs_enable_public_${ key }` ] = false;
		}

		setSettings( updated );
	};

	const updateVisibility = ( key, value ) => {
		setSettings( {
			...settings,
			[ `hk_fs_enable_public_${ key }` ]: value,
		} );
	};

	return (
		<div style={ { maxWidth: '700px' } }>
			<PanelBody
				title={ __( 'Content Types', 'hk-funeral-suite' ) }
				initialOpen
			>
				<p style={ { color: '#757575', marginTop: 0 } }>
					{ __(
						'Enable or disable the funeral content types used on your website.',
						'hk-funeral-suite'
					) }
				</p>
				{ CPT_OPTIONS.map( ( { key, label } ) => (
					<PanelRow key={ key }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ label }
							checked={ !! enabledCpts[ key ] }
							onChange={ ( value ) => updateCpt( key, value ) }
						/>
					</PanelRow>
				) ) }
			</PanelBody>

			<PanelBody
				title={ __( 'Public Visibility', 'hk-funeral-suite' ) }
				initialOpen={ false }
			>
				<p style={ { color: '#757575', marginTop: 0 } }>
					{ __(
						'Enable publicly accessible single pages and archives for each content type. After changing these settings, visit the Permalinks page to refresh URL structures.',
						'hk-funeral-suite'
					) }
				</p>
				{ CPT_OPTIONS.map( ( { key, label } ) => {
					const isEnabled = !! enabledCpts[ key ];
					return (
						<PanelRow key={ key }>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ label }
								checked={
									!! settings[
										`hk_fs_enable_public_${ key }`
									]
								}
								onChange={ ( value ) =>
									updateVisibility( key, value )
								}
								disabled={ ! isEnabled }
								help={
									! isEnabled
										? __(
												'Enable the content type first.',
												'hk-funeral-suite'
										  )
										: undefined
								}
							/>
						</PanelRow>
					);
				} ) }
			</PanelBody>

			<div style={ { padding: '16px 0' } }>
				<Button
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ () => saveSettings( settings ) }
				>
					{ isSaving
						? __( 'Saving…', 'hk-funeral-suite' )
						: __( 'Save Settings', 'hk-funeral-suite' ) }
				</Button>
			</div>
		</div>
	);
}
