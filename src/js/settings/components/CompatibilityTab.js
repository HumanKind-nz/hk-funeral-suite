/**
 * Compatibility tab — theme and plugin meta box cleanup toggles.
 *
 * @package HKFuneralSuite
 */
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	PanelRow,
	ToggleControl,
	Button,
	ExternalLink,
} from '@wordpress/components';

export default function CompatibilityTab( {
	settings,
	setSettings,
	saveSettings,
	isSaving,
} ) {
	const pluginData = window.hkFsSettings || {};
	const activePlugins = pluginData.activePlugins || {};

	const updateSetting = ( key, value ) => {
		setSettings( {
			...settings,
			[ key ]: value,
		} );
	};

	const themeOptions = [
		{
			key: 'hk_fs_generatepress_compatibility',
			label: 'GeneratePress',
			url: 'https://generatepress.com/',
			description: __(
				'Remove layout options and sections meta boxes.',
				'hk-funeral-suite'
			),
			active: !! activePlugins.generatepress,
		},
		{
			key: 'hk_fs_wpbf_compatibility',
			label: 'Page Builder Framework',
			url: 'https://wp-pagebuilderframework.com/',
			description: __(
				'Remove theme settings meta boxes.',
				'hk-funeral-suite'
			),
			active: !! activePlugins.wpbf,
		},
	];

	const pluginOptions = [
		{
			key: 'hk_fs_happyfiles_compatibility',
			label: 'HappyFiles Pro',
			url: 'https://happyfiles.io/',
			description: __(
				'Remove duplicate featured image column.',
				'hk-funeral-suite'
			),
			active: !! activePlugins.happyfiles,
		},
		{
			key: 'hk_fs_seopress_metabox_compatibility',
			label: 'SEOPress',
			url: 'https://www.seopress.org/',
			description: __(
				'Remove SEO and content analysis meta boxes.',
				'hk-funeral-suite'
			),
			active: !! activePlugins.seopress,
		},
	];

	const renderToggle = ( option ) => (
		<PanelRow key={ option.key }>
			<ToggleControl
				__nextHasNoMarginBottom
				label={
					<span>
						<ExternalLink href={ option.url }>
							{ option.label }
						</ExternalLink>
					</span>
				}
				checked={ !! settings[ option.key ] }
				onChange={ ( value ) => updateSetting( option.key, value ) }
				disabled={ ! option.active }
				help={
					! option.active
						? __( 'Not currently active.', 'hk-funeral-suite' )
						: option.description
				}
			/>
		</PanelRow>
	);

	return (
		<div style={ { maxWidth: '700px' } }>
			<PanelBody
				title={ __(
					'Theme Meta Box Cleanup',
					'hk-funeral-suite'
				) }
				initialOpen
			>
				<p style={ { color: '#757575', marginTop: 0 } }>
					{ __(
						'Simplify the post editor by removing unnecessary meta boxes from supported themes.',
						'hk-funeral-suite'
					) }
				</p>
				{ themeOptions.map( renderToggle ) }
			</PanelBody>

			<PanelBody
				title={ __(
					'Plugin Meta Box Cleanup',
					'hk-funeral-suite'
				) }
				initialOpen
			>
				<p style={ { color: '#757575', marginTop: 0 } }>
					{ __(
						'Remove unnecessary meta boxes from supported plugins when editing funeral content types.',
						'hk-funeral-suite'
					) }
				</p>
				{ pluginOptions.map( renderToggle ) }
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
