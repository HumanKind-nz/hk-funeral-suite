/**
 * About tab — plugin information and links.
 *
 * @package HKFuneralSuite
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	ExternalLink,
} from '@wordpress/components';

export default function AboutTab() {
	const pluginData = window.hkFsSettings || {};

	return (
		<div style={ { maxWidth: '700px' } }>
			<Card style={ { marginBottom: '16px' } }>
				<CardHeader>
					<h3 style={ { margin: 0 } }>
						{ __( 'HumanKind Funeral Suite', 'hk-funeral-suite' ) }
					</h3>
				</CardHeader>
				<CardBody>
					{ pluginData.iconUrl && (
						<img
							src={ pluginData.iconUrl }
							alt="HK Funeral Suite"
							style={ {
								width: '80px',
								height: '80px',
								float: 'left',
								marginRight: '16px',
								marginBottom: '8px',
							} }
						/>
					) }
					<p>
						{ __(
							'Custom post types, taxonomies, fields and specialised Gutenberg blocks for funeral home websites.',
							'hk-funeral-suite'
						) }
					</p>
					<p>
						<strong>
							{ __( 'Version:', 'hk-funeral-suite' ) }
						</strong>{ ' ' }
						{ pluginData.version || '—' }
					</p>
					<div style={ { clear: 'both' } } />
				</CardBody>
			</Card>

			<Card style={ { marginBottom: '16px' } }>
				<CardHeader>
					<h3 style={ { margin: 0 } }>
						{ __( 'Resources', 'hk-funeral-suite' ) }
					</h3>
				</CardHeader>
				<CardBody>
					<ul style={ { margin: 0, paddingLeft: '20px' } }>
						<li>
							<ExternalLink href="https://github.com/HumanKind-nz/hk-funeral-suite/">
								{ __(
									'GitHub Repository',
									'hk-funeral-suite'
								) }
							</ExternalLink>
							{ ' — ' }
							{ __(
								'Report issues, request features, or contribute.',
								'hk-funeral-suite'
							) }
						</li>
						<li>
							<ExternalLink href="https://humankind.co.nz">
								{ __( 'HumanKind', 'hk-funeral-suite' ) }
							</ExternalLink>
							{ ' — ' }
							{ __(
								'Funeral website specialists.',
								'hk-funeral-suite'
							) }
						</li>
						<li>
							<ExternalLink href="https://weave.co.nz">
								{ __(
									'Weave Digital Studio',
									'hk-funeral-suite'
								) }
							</ExternalLink>
							{ ' — ' }
							{ __(
								'WordPress development and support.',
								'hk-funeral-suite'
							) }
						</li>
					</ul>
				</CardBody>
			</Card>
		</div>
	);
}
