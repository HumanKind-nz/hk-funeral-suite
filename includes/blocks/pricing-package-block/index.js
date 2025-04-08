/**
 * Pricing Package Block for Gutenberg
 * Simple version that works without a build step
 *   1.0.2 - Added Intro Paragraph field & updated pricing field
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
	var { useEffect } = wp.element;
	
	// Add custom styles for the block
	var styleElement = document.createElement('style');
	styleElement.textContent = `
		.pricing-package-fields {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 15px;
		}
		
		.pricing-package-fields .intro-field {
			grid-column: 1 / span 2;
			margin-bottom: 10px;
		}
	`;
	document.head.appendChild(styleElement);
	
	// Register the block
	registerBlockType('hk-funeral-suite/pricing-package', {
		title: 'Pricing Package Info',
		icon: 'money-alt',
		category: 'common',
		supports: {
			html: false,
		},
		attributes: {
			intro: {
				type: 'string',
				default: ''
			},
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
			
			// Add useEffect to refresh the price managed status when editor loads
			useEffect(function() {
				// Force a refresh of the is_price_managed value by directly checking the option
				// This ensures we have the latest setting
				if (window.hkFsPackageData !== undefined) {
					isPriceManaged = window.hkFsPackageData.is_price_managed || false;
				}
			}, []);
			
			// Load data if available
			useEffect(function() {
				if (window.hkFsPackageData) {
					setAttributes({
						intro: window.hkFsPackageData.intro || attributes.intro || '',
						price: window.hkFsPackageData.price || attributes.price || '',
						order: window.hkFsPackageData.order || attributes.order || '10'
					});
				}
			}, []);
			
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
			
			// Create intro field as a separate component to span full width
			var introField = createElement(
				'div',
				{ className: 'intro-field' },
				createElement(
					TextControl,
					{
						label: 'Intro Paragraph',
						value: attributes.intro,
						onChange: function(value) {
							setAttributes({ intro: value });
						},
						placeholder: 'Enter brief package summary ...',
						// Custom help text with inline style for smaller, gray text
						help: createElement(
							'span',
							{ style: { fontSize: '11px', color: '#757575' } },
							'This intro could be displayed at the top of the package.'
						)
					}
				)
			);
			
			// Create main fields component
			var fields = createElement(
				'div',
				{ className: 'pricing-package-fields' },
				introField,
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
						placeholder: 'Enter price or "P.O.A."...',
						// Changed from number to text type to allow text values
						type: 'text',
						disabled: isPriceManaged,
						className: isPriceManaged ? 'is-disabled' : '',
						// Added help text to explain the field accepts text
						help: createElement(
							'span',
							{ style: { fontSize: '11px', color: '#757575' } },
							'Enter a numeric price or text (e.g., "P.O.A.")'
						)
					}
				),
				createElement(
					TextControl,
					{
						label: 'Display Order',
						value: attributes.order,
						onChange: function(value) {
							// Always allow changing the order regardless of isPriceManaged
							setAttributes({ order: value });
						},
						help: createElement(
							'span',
							{ style: { fontSize: '11px', color: '#757575' } },
							'Lower numbers will be displayed first.'
						),
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
							label: 'Intro Paragraph',
							value: attributes.intro,
							onChange: function(value) {
								setAttributes({ intro: value });
							},
							placeholder: 'Enter brief package summary...',
							help: createElement(
								'span',
								{ style: { fontSize: '11px', color: '#757575' } },
								'This intro could be displayed at the top of the package.'
							)
						}
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
							// Changed from number to text type in the sidebar as well
							type: 'text',
							placeholder: 'Enter price or "P.O.A."...',
							disabled: isPriceManaged,
							className: isPriceManaged ? 'is-disabled' : '',
							help: createElement(
								'span',
								{ style: { fontSize: '11px', color: '#757575' } },
								'Enter a numeric price or text (e.g., "P.O.A.")'
							)
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
								// Always allow changing the order regardless of isPriceManaged
								setAttributes({ order: value });
							},
							help: createElement(
								'span',
								{ style: { fontSize: '11px', color: '#757575' } },
								'Lower numbers will be displayed first.'
							),
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
