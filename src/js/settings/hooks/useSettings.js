/**
 * Hook for reading and writing plugin settings via the REST API.
 *
 * @package HKFuneralSuite
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Settings keys we read/write from /wp/v2/settings.
 */
const SETTINGS_KEYS = [
	'hk_fs_enabled_cpts',
	'hk_fs_enable_public_staff',
	'hk_fs_enable_public_caskets',
	'hk_fs_enable_public_urns',
	'hk_fs_enable_public_packages',
	'hk_fs_enable_public_monuments',
	'hk_fs_enable_public_keepsakes',
	'hk_fs_package_price_google_sheets',
	'hk_fs_casket_price_google_sheets',
	'hk_fs_urn_price_google_sheets',
	'hk_fs_monument_price_google_sheets',
	'hk_fs_keepsake_price_google_sheets',
	'hk_fs_generatepress_compatibility',
	'hk_fs_wpbf_compatibility',
	'hk_fs_happyfiles_compatibility',
	'hk_fs_seopress_metabox_compatibility',
];

export default function useSettings() {
	const [ settings, setSettings ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wp/v2/settings' } )
			.then( ( data ) => {
				const filtered = {};
				SETTINGS_KEYS.forEach( ( key ) => {
					if ( data[ key ] !== undefined ) {
						filtered[ key ] = data[ key ];
					}
				} );
				setSettings( filtered );
				setIsLoading( false );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: 'Failed to load settings.',
				} );
				setIsLoading( false );
			} );
	}, [] );

	const saveSettings = async ( newSettings ) => {
		setIsSaving( true );
		setNotice( null );

		try {
			const data = await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: newSettings,
			} );

			const filtered = {};
			SETTINGS_KEYS.forEach( ( key ) => {
				if ( data[ key ] !== undefined ) {
					filtered[ key ] = data[ key ];
				}
			} );
			setSettings( filtered );

			setNotice( {
				status: 'success',
				message: 'Settings saved successfully.',
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: 'Failed to save settings.',
			} );
		}

		setIsSaving( false );
	};

	return {
		settings,
		setSettings,
		saveSettings,
		isLoading,
		isSaving,
		notice,
		setNotice,
	};
}
