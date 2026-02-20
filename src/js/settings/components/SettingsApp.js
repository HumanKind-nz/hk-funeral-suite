/**
 * Main settings app with tabbed interface.
 *
 * @package HKFuneralSuite
 */
import { __ } from '@wordpress/i18n';
import { TabPanel, Spinner, Notice } from '@wordpress/components';
import useSettings from '../hooks/useSettings';
import GeneralTab from './GeneralTab';
import IntegrationTab from './IntegrationTab';
import CompatibilityTab from './CompatibilityTab';
import AboutTab from './AboutTab';

const TABS = [
	{
		name: 'general',
		title: __( 'General', 'hk-funeral-suite' ),
		className: 'hk-fs-tab-general',
	},
	{
		name: 'integration',
		title: __( 'Integration', 'hk-funeral-suite' ),
		className: 'hk-fs-tab-integration',
	},
	{
		name: 'compatibility',
		title: __( 'Compatibility', 'hk-funeral-suite' ),
		className: 'hk-fs-tab-compatibility',
	},
	{
		name: 'about',
		title: __( 'About', 'hk-funeral-suite' ),
		className: 'hk-fs-tab-about',
	},
];

export default function SettingsApp() {
	const {
		settings,
		setSettings,
		saveSettings,
		isLoading,
		isSaving,
		notice,
		setNotice,
	} = useSettings();

	if ( isLoading ) {
		return (
			<div style={ { padding: '40px', textAlign: 'center' } }>
				<Spinner />
			</div>
		);
	}

	const tabProps = { settings, setSettings, saveSettings, isSaving };

	return (
		<div className="hk-fs-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
					style={ { margin: '16px 0' } }
				>
					{ notice.message }
				</Notice>
			) }

			<TabPanel tabs={ TABS }>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'general':
							return <GeneralTab { ...tabProps } />;
						case 'integration':
							return <IntegrationTab { ...tabProps } />;
						case 'compatibility':
							return <CompatibilityTab { ...tabProps } />;
						case 'about':
							return <AboutTab />;
						default:
							return null;
					}
				} }
			</TabPanel>
		</div>
	);
}
