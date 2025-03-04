/**
 * Urn Block for Gutenberg
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

	registerBlockType('hk-funeral-suite/urn', {
		title: 'Urn Information',
		icon: 'archive',
		category: 'common',
		attributes: {
			price: { type: 'string', default: '' },
			selectedCategory: { type: 'string', default: '' },
			featuredImageId: { type: 'number', default: 0 },
			featuredImageUrl: { type: 'string', default: '' }
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var blockProps = useBlockProps({
				className: 'urn-block',
			});
			
			// Get current post ID
			var postId = useSelect(function(select) {
				return select('core/editor').getCurrentPostId();
			}, []);
			
			// Get editPost function
			var { editPost } = useDispatch('core/editor');

			// Sync price changes with meta field
			function updatePrice(value) {
				setAttributes({ price: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_urn_price': value } });
				}
			}
			
			// Sync category changes with meta field
			function updateCategory(value) {
				setAttributes({ selectedCategory: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_urn_category': value } });
				}
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
					title: 'Select Urn Image',
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
				return select('core').getEntityRecords('taxonomy', 'hk_fs_urn_category', { per_page: -1 });
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

			// Load initial meta data
			useEffect(() => {
				if (window.hkFsUrnData && attributes.price === '') {
					setAttributes({
						price: window.hkFsUrnData.price || '',
						selectedCategory: window.hkFsUrnData.selectedCategory || ''
					});
				}
			}, []);

			return createElement(
				Fragment,
				null,
				createElement(
					'div',
					blockProps,
					createElement('h3', { className: 'urn-section-title' }, 'Urn Information'),

					createElement(
						'div',
						{ className: 'urn-image-section', style: { marginBottom: '20px' } },
						createElement('h3', { className: 'urn-section-title' }, 'Urn Image'),
						createElement(
							'div',
							{ className: 'urn-featured-image-container' },
							attributes.featuredImageId > 0
								? [
									  createElement(
										  'div',
										  { className: 'urn-image-preview', style: { marginBottom: '10px' } },
										  createElement('img', {
											  src: attributes.featuredImageUrl,
											  alt: 'Urn',
											  style: { maxWidth: '100%', height: 'auto' }
										  })
									  ),
									  createElement(
										  'div',
										  { className: 'urn-image-buttons', style: { display: 'flex', gap: '8px' } },
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
								: createElement(Button, { isPrimary: true, onClick: selectImage }, 'Select Urn Image')
						)
					),

					createElement(
						'div',
						{ className: 'urn-fields' },
						createElement(TextControl, {
							label: 'Price ($)',
							value: attributes.price,
							onChange: updatePrice,
							placeholder: 'Enter price...',
							type: 'number',
							step: '0.01',
							min: '0'
						}),
						createElement(SelectControl, {
							label: 'Category',
							value: attributes.selectedCategory,
							options: getCategoryOptions(),
							onChange: updateCategory
						})
					)
				),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Urn Settings' },
						createElement(
							TextControl,
							{
								label: 'Price ($)',
								value: attributes.price,
								onChange: updatePrice,
								type: 'number',
								step: '0.01',
								min: '0'
							}
						),
						createElement(
							SelectControl,
							{
								label: 'Category',
								value: attributes.selectedCategory,
								options: getCategoryOptions(),
								onChange: updateCategory
							}
						)
					)
				)
			);
		},

		save: function () {
			return null;
		}
	});
})(window.wp);