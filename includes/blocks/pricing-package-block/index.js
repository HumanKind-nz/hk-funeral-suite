/**
 * Pricing Package Block for Gutenberg
 * Simple version that works without a build step
 */
(function(wp) {
	// Extract the components we need
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var __ = wp.i18n.__;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	// Register the block
	registerBlockType('hk-funeral-suite/pricing-package', {
		title: 'Pricing Package Info',
		icon: 'money-alt',
		category: 'common',
		supports: {
			html: false,
		},
		attributes: {
			price: {
				type: 'string',
				default: ''
			},
			order: {
				type: 'string',
				default: '10'
			}
		},
		edit: function(props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			
			var blockProps = useBlockProps({
				className: 'pricing-package-block',
			});
			
			// Check if price is managed by Google Sheets
			var isPriceManaged = false;
			if (window.hkFsPackageData !== undefined) {
				isPriceManaged = window.hkFsPackageData.is_price_managed || false;
			}
			
			// Try to load initial data if available
			if (window.hkFsPackageData !== undefined && 
				attributes.price === '' && 
				attributes.order === '10') {
				// Only set attributes if they're empty (first load)
				setAttributes({
					price: window.hkFsPackageData.price || '',
					order: window.hkFsPackageData.order || '10'
				});
			}
			
			// Create price field notice for Google Sheets integration
			var sheetNotice = null;
			if (isPriceManaged) {
				sheetNotice = createElement(
					'div',
					{ className: 'sheet-integration-notice' },
					createElement(
						'p',
						{ style: { color: '#d63638', display: 'flex', alignItems: 'center' } },
						createElement('span', { 
							className: 'dashicons dashicons-cloud',
							style: { marginRight: '5px' }
						}),
						createElement(
							'strong',
							null,
							'Managed via Google Sheets'
						)
					),
					createElement(
						'p',
						{ className: 'components-base-control__help' },
						'Pricing is managed through Google Sheets and cannot be modified here.'
					)
				);
			}
			
			// Create main fields component
			var fields = createElement(
				'div',
				{ className: 'pricing-package-fields' },
				createElement(
					TextControl,
					{
						label: 'Price ($)',
						value: attributes.price,
						onChange: function(value) {
							if (!isPriceManaged) {
								setAttributes({ price: value });
							}
						},
						placeholder: 'Enter price...',
						type: 'number',
						step: '0.01',
						min: '0',
						disabled: isPriceManaged,
						className: isPriceManaged ? 'is-disabled' : ''
					}
				),
				createElement(
					TextControl,
					{
						label: 'Display Order',
						value: attributes.order,
						onChange: function(value) {
							setAttributes({ order: value });
						},
						help: 'Lower numbers will be displayed first.',
						type: 'number',
						step: '1',
						min: '0'
					}
				),
				isPriceManaged ? sheetNotice : null
			);
			
			// Create sidebar controls with similar Google Sheets integration handling
			var inspectorControls = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: 'Package Settings' },
					createElement(
						'p',
						null,
						'You can also edit pricing package information in the sidebar.'
					),
					createElement(
						TextControl,
						{
							label: 'Price ($)',
							value: attributes.price,
							onChange: function(value) {
								if (!isPriceManaged) {
									setAttributes({ price: value });
								}
							},
							type: 'number',
							step: '0.01',
							min: '0',
							disabled: isPriceManaged,
							className: isPriceManaged ? 'is-disabled' : ''
						}
					),
					isPriceManaged ? createElement(
						'p',
						{ style: { color: '#d63638', fontSize: '12px', marginTop: '-8px' } },
						'Pricing is managed via Google Sheets'
					) : null,
					createElement(
						TextControl,
						{
							label: 'Display Order',
							value: attributes.order,
							onChange: function(value) {
								setAttributes({ order: value });
							},
							help: 'Lower numbers will be displayed first.',
							type: 'number',
							step: '1',
							min: '0'
						}
					)
				)
			);
			
			// Return the complete block
			return createElement(
				Fragment,
				null,
				createElement(
					'div',
					blockProps,
					createElement(
						'div',
						{ className: 'pricing-package-editor' },
						createElement(
							'h3',
							{ className: 'pricing-package-section-title' },
							'Pricing Package Information'
						),
						fields
					)
				),
				inspectorControls
			);
		},
		save: function() {
			// Dynamic block, render nothing on save
			return null;
		},
	});
})(window.wp);
