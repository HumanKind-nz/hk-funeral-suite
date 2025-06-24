/**
 * Team Member Block
 */
(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var useEffect = wp.element.useEffect;
	var useDispatch = wp.data.useDispatch;

	registerBlockType('hk-funeral-suite/team-member', {
		title: 'Team Member Info',
		icon: 'businessperson',
		category: 'hk-funeral-suite',
		attributes: {
			position: { type: 'string', default: '' },
			qualification: { type: 'string', default: '' },
			phone: { type: 'string', default: '' },
			email: { type: 'string', default: '' },
			featuredImageId: { type: 'number', default: 0 },
			featuredImageUrl: { type: 'string', default: '' }
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			
			// Get current post ID - exact same approach as Casket block
			var postId = useSelect(function(select) {
				return select('core/editor').getCurrentPostId();
			}, []);
			
			// Get editPost function - exact same as Casket block
			var { editPost } = useDispatch('core/editor');

			// Sync position changes with meta field
			function updatePosition(value) {
				setAttributes({ position: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_staff_position': value } });
				}
			}
			
			// Sync qualification changes with meta field
			function updateQualification(value) {
				setAttributes({ qualification: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_staff_qualification': value } });
				}
			}
			
			// Sync phone changes with meta field
			function updatePhone(value) {
				setAttributes({ phone: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_staff_phone': value } });
				}
			}
			
			// Sync email changes with meta field
			function updateEmail(value) {
				setAttributes({ email: value });
				// Update the post meta
				if (postId) {
					editPost({ meta: { '_hk_fs_staff_email': value } });
				}
			}
			
			// Sync with post's featured image - same approach as Casket block
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
					title: 'Select Staff Photo',
					button: { text: 'Use this photo' },
					multiple: false,
					library: { type: 'image' }
				});
			
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					setAttributes({
						featuredImageId: attachment.id,
						featuredImageUrl: attachment.url
					});
			
					// Sync with post's actual featured image
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

			// Load initial meta data - exact same pattern as Casket block
			useEffect(() => {
				if (window.hkFsTeamMemberData && attributes.position === '') {
					setAttributes({
						position: window.hkFsTeamMemberData.position || '',
						qualification: window.hkFsTeamMemberData.qualification || '',
						phone: window.hkFsTeamMemberData.phone || '',
						email: window.hkFsTeamMemberData.email || ''
					});
				}
			}, []);

			// 🛠️ Rebuilding the block UI
			return createElement(
				Fragment,
				null,
				createElement(
					'div',
					useBlockProps({ className: 'team-member-block' }),
					[
						createElement('h3', { className: 'team-member-section-title' }, 'Team Member Information'),

						// Image Section
						createElement(
							'div',
							{ className: 'team-member-image-section', style: { marginBottom: '20px' } },
							[
								createElement('h3', { className: 'team-member-section-title' }, 'Staff Photo'),

								createElement(
									'div',
									{ className: 'team-member-featured-image-container' },
									attributes.featuredImageId > 0
										? [
											createElement(
												'div',
												{
													className: 'team-member-image-preview',
													style: { marginBottom: '10px' }
												},
												createElement('img', {
													src: attributes.featuredImageUrl,
													alt: 'Team Member',
													style: { maxWidth: '100%', height: 'auto' }
												})
											),

											createElement(
												'div',
												{
													className: 'team-member-image-buttons',
													style: { display: 'flex', gap: '8px' }
												},
												[
													createElement(
														Button,
														{
															isPrimary: true,
															onClick: selectImage
														},
														'Replace Photo'
													),
													createElement(
														Button,
														{
															isSecondary: true,
															onClick: removeImage
														},
														'Remove Photo'
													)
												]
											)
										]
										: createElement(
											Button,
											{
												isPrimary: true,
												onClick: selectImage
											},
											'Select Staff Photo'
										)
								)
							]
						),

						// Fields
						createElement('div', { className: 'team-member-fields' }, [
							createElement(TextControl, {
								label: 'Position',
								value: attributes.position,
								onChange: updatePosition,
								placeholder: 'Enter position...'
							}),
							createElement(TextControl, {
								label: 'Qualification',
								value: attributes.qualification,
								onChange: updateQualification,
								placeholder: 'Enter qualifications...'
							}),
							createElement(TextControl, {
								label: 'Phone',
								value: attributes.phone,
								onChange: updatePhone,
								placeholder: 'Enter phone number...'
							}),
							createElement(TextControl, {
								label: 'Email',
								type: 'email',
								value: attributes.email,
								onChange: updateEmail,
								placeholder: 'Enter email address...'
							})
						])
					]
				),
				// Add inspector controls with the same fields - just like Casket block
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Team Member Settings' },
						createElement(
							TextControl,
							{
								label: 'Position',
								value: attributes.position,
								onChange: updatePosition,
								placeholder: 'Enter position...'
							}
						),
						createElement(
							TextControl,
							{
								label: 'Qualification',
								value: attributes.qualification,
								onChange: updateQualification,
								placeholder: 'Enter qualifications...'
							}
						),
						createElement(
							TextControl,
							{
								label: 'Phone',
								value: attributes.phone,
								onChange: updatePhone,
								placeholder: 'Enter phone number...'
							}
						),
						createElement(
							TextControl,
							{
								label: 'Email',
								type: 'email',
								value: attributes.email,
								onChange: updateEmail,
								placeholder: 'Enter email address...'
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