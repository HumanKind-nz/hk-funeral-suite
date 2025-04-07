/**
 * Keepsake Block for Gutenberg
 */

(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var useEffect = wp.element.useEffect;
	var Button = wp.components.Button;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useDispatch = wp.data.useDispatch;

	registerBlockType('hk-funeral-suite/keepsake', {
		title: 'Keepsake Information',
		icon: 'archive',
		category: 'common',
		attributes: {
			price: { type: 'string', default: '' },
			selectedCategory: { type: 'string', default: '' },
			featuredImageId: { type: 'number', default: 0 },
			featuredImageUrl: { type: 'string', default: '' },
			productCode: { type: 'string', default: '' },
			metal: { type: 'string', default: '' },
			stones: { type: 'string', default: '' }
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var blockProps = useBlockProps({
				className: 'keepsake-block',
			});
			
			// Check if price is managed by Google Sheets
			var isPriceManaged = false;
			if (window.hkFsKeepsakeData !== undefined) {
				isPriceManaged = window.hkFsKeepsakeData.is_price_managed || false;
			}
			
			// Get current post ID
			var postId = useSelect(function(select) {
				return select('core/editor').getCurrentPostId();
			}, []);
			
			// Get editPost function
			var { editPost } = useDispatch('core/editor');

			// Sync price changes with meta field
			function updatePrice(value) {
				if (!isPriceManaged) {
					setAttributes({ price: value });
					// Update the post meta
					if (postId) {
						editPost({ meta: { '_hk_fs_keepsake_price': value } });
					}
				}
			}
			
			// Sync category changes with meta field
			function updateCategory(value) {
				setAttributes({ selectedCategory: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_keepsake_category': value } });
				}
			}

			// Sync product code changes with meta field
			function updateProductCode(value) {
				setAttributes({ productCode: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_keepsake_product_code': value } });
				}
			}

			// Sync metal changes with meta field
			function updateMetal(value) {
				setAttributes({ metal: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_keepsake_metal': value } });
				}
			}

			// Sync stones changes with meta field
			function updateStones(value) {
				setAttributes({ stones: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_keepsake_stones': value } });
				}
			}

			// Create Google Sheets notice for price field
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
						'Pricing is managed through a Google Sheets and cannot be modified here.'
					)
				);
			}

			// Sync with post's actual featured image
			const [postFeaturedImageId, setPostFeaturedImageId] = useEntityProp(
				'postType',
				'post',
				'featured_media'
			);

			useEffect(() => {
				if (postFeaturedImageId && attributes.featuredImageId !== postFeaturedImageId) {
					setAttributes({ featuredImageId: postFeaturedImageId });
				}
			}, [postFeaturedImageId]);

			var featuredImage = useSelect((select) => {
				return attributes.featuredImageId ? select('core').getMedia(attributes.featuredImageId) : null;
			}, [attributes.featuredImageId]);

			useEffect(() => {
				if (featuredImage && featuredImage.source_url) {
					setAttributes({ featuredImageUrl: featuredImage.source_url });
				}
			}, [featuredImage]);

			function selectImage() {
				var frame = wp.media({
					title: 'Select Keepsake Image',
					button: { text: 'Use this image' },
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					setAttributes({
						featuredImageId: attachment.id,
						featuredImageUrl: attachment.url
					});

					setPostFeaturedImageId(attachment.id);
				});

				frame.open();
			}

			function removeImage() {
				setAttributes({
					featuredImageId: 0,
					featuredImageUrl: ''
				});

				setPostFeaturedImageId(0);
			}

			var categories = useSelect(function (select) {
				return select('core').getEntityRecords('taxonomy', 'hk_fs_keepsake_category', { per_page: -1 });
			}, []);

			function getCategoryOptions() {
				var options = [{ label: 'Select Category', value: '' }];
				if (categories) {
					categories.forEach(function (category) {
						options.push({ label: category.name, value: category.id.toString() });
					});
				}
				return options;
			}

			function getMetalOptions() {
				return [
					{ label: 'Select Metal', value: '' },
					{ label: 'Gold', value: 'gold' },
					{ label: 'Silver', value: 'silver' },
					{ label: 'Other', value: 'other' }
				];
			}

			function getStoneOptions() {
				return [
					{ label: 'Select Stone Type', value: '' },
					{ label: 'Diamond', value: 'diamond' },
					{ label: 'Cubic Zirconia', value: 'cubic_zirconia' },
					{ label: 'None', value: 'none' },
					{ label: 'Other', value: 'other' }
				];
			}

			// Load initial meta data
			useEffect(() => {
				if (window.hkFsKeepsakeData && attributes.price === '') {
					setAttributes({
						price: window.hkFsKeepsakeData.price || '',
						selectedCategory: window.hkFsKeepsakeData.selectedCategory || '',
						productCode: window.hkFsKeepsakeData.productCode || '',
						metal: window.hkFsKeepsakeData.metal || '',
						stones: window.hkFsKeepsakeData.stones || ''
					});
				}
			}, []);

			return createElement(
				Fragment,
				null,
				createElement(
					'div',
					blockProps,
					createElement('h3', { className: 'keepsake-section-title' }, 'Keepsake Information'),

					createElement(
						'div',
						{ className: 'keepsake-image-section', style: { marginBottom: '20px' } },
						createElement('h3', { className: 'keepsake-section-title' }, 'Keepsake Image'),
						createElement(
							'div',
							{ className: 'keepsake-featured-image-container' },
							attributes.featuredImageId > 0
								? [
									  createElement(
										  'div',
										  { className: 'keepsake-image-preview', style: { marginBottom: '10px' } },
										  createElement('img', {
											  src: attributes.featuredImageUrl,
											  alt: 'Keepsake',
											  style: { maxWidth: '100%', height: 'auto' }
										  })
									  ),
									  createElement(
										  'div',
										  { className: 'keepsake-image-buttons', style: { display: 'flex', gap: '8px' } },
										  [
											  createElement(
												  Button,
												  { isPrimary: true, onClick: selectImage },
												  'Replace Image'
											  ),
											  createElement(
												  Button,
												  { isSecondary: true, onClick: removeImage },
												  'Remove Image'
											  )
										  ]
									  )
								  ]
								: createElement(Button, { isPrimary: true, onClick: selectImage }, 'Select Keepsake Image')
						)
					),

					createElement(
						'div',
						{ className: 'keepsake-fields' },
						createElement(TextControl, {
							label: 'Product Code',
							value: attributes.productCode,
							onChange: updateProductCode,
							placeholder: 'Enter product code...'
						}),
						createElement(TextControl, {
							label: 'Price ($)',
							value: attributes.price,
							onChange: updatePrice,
							placeholder: 'Enter price...',
							type: 'number',
							step: '0.01',
							min: '0',
							disabled: isPriceManaged,
							className: isPriceManaged ? 'is-disabled' : ''
						}),
						createElement(SelectControl, {
							label: 'Category',
							value: attributes.selectedCategory,
							options: getCategoryOptions(),
							onChange: updateCategory
						}),
						isPriceManaged ? sheetNotice : null,
						createElement(SelectControl, {
							label: 'Metal Type',
							value: attributes.metal,
							options: getMetalOptions(),
							onChange: updateMetal
						}),
						createElement(SelectControl, {
							label: 'Stones',
							value: attributes.stones,
							options: getStoneOptions(),
							onChange: updateStones
						})
					)
				),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Keepsake Settings', initialOpen: true },
						createElement(TextControl, {
							label: 'Product Code',
							value: attributes.productCode,
							onChange: updateProductCode,
							placeholder: 'Enter product code...'
						}),
						createElement(TextControl, {
							label: 'Price ($)',
							value: attributes.price,
							onChange: updatePrice,
							placeholder: 'Enter price...',
							type: 'number',
							step: '0.01',
							min: '0',
							disabled: isPriceManaged,
							className: isPriceManaged ? 'is-disabled' : ''
						}),
						isPriceManaged ? sheetNotice : null,
						createElement(SelectControl, {
							label: 'Category',
							value: attributes.selectedCategory,
							options: getCategoryOptions(),
							onChange: updateCategory
						}),
						createElement(SelectControl, {
							label: 'Metal Type',
							value: attributes.metal,
							options: getMetalOptions(),
							onChange: updateMetal
						}),
						createElement(SelectControl, {
							label: 'Stones',
							value: attributes.stones,
							options: getStoneOptions(),
							onChange: updateStones
						})
					)
				)
			);
		},
		save: function () {
			return null; // Dynamic block, rendering handled by PHP
		}
	});
})(window.wp); 