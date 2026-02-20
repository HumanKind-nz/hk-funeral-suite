/**
 * Integration tab — Google Sheets price sync toggles.
 *
 * @package HKFuneralSuite
 */
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	PanelRow,
	ToggleControl,
	Button,
	Notice,
} from '@wordpress/components';

const SHEETS_OPTIONS = [
	{ key: 'package', settingsKey: 'packages', label: __( 'Pricing Packages', 'hk-funeral-suite' ) },
	{ key: 'casket', settingsKey: 'caskets', label: __( 'Caskets', 'hk-funeral-suite' ) },
	{ key: 'urn', settingsKey: 'urns', label: __( 'Urns', 'hk-funeral-suite' ) },
	{ key: 'monument', settingsKey: 'monuments', label: __( 'Monuments', 'hk-funeral-suite' ) },
	{ key: 'keepsake', settingsKey: 'keepsakes', label: __( 'Keepsakes', 'hk-funeral-suite' ) },
];

export default function IntegrationTab( {
	settings,
	setSettings,
	saveSettings,
	isSaving,
} ) {
	const enabledCpts = settings.hk_fs_enabled_cpts || {};

	const updateSheets = ( key, value ) => {
		setSettings( {
			...settings,
			[ `hk_fs_${ key }_price_google_sheets` ]: value,
		} );
	};

	return (
		<div style={ { maxWidth: '700px' } }>
			<PanelBody
				title={ __( 'Google Sheets Price Sync', 'hk-funeral-suite' ) }
				initialOpen
			>
				<p style={ { color: '#757575', marginTop: 0 } }>
					{ __(
						'Enable one-way price sync from Google Sheets for each product type. When enabled, price fields in the editor will be locked and managed exclusively via Google Sheets.',
						'hk-funeral-suite'
					) }
				</p>

				{ SHEETS_OPTIONS.map(
					( { key, settingsKey, label } ) => {
						const isEnabled = !! enabledCpts[ settingsKey ];
						const optionKey = `hk_fs_${ key }_price_google_sheets`;

						return (
							<PanelRow key={ key }>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ label }
									checked={ !! settings[ optionKey ] }
									onChange={ ( value ) =>
										updateSheets( key, value )
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
					}
				) }

				<Notice
					status="warning"
					isDismissible={ false }
					style={ { marginTop: '16px' } }
				>
					{ __(
						'After changing these settings, reload any open edit screens to apply changes to the price fields.',
						'hk-funeral-suite'
					) }
				</Notice>
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
