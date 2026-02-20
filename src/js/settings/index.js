/**
 * Settings page mount point.
 *
 * @package HKFuneralSuite
 */
import { createRoot } from '@wordpress/element';
import SettingsApp from './components/SettingsApp';

const container = document.getElementById( 'hk-fs-settings' );
if ( container ) {
	const root = createRoot( container );
	root.render( <SettingsApp /> );
}
