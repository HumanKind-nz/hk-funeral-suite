<?php
/**
 * Block Editor Styles Integration
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.0.0
 * @since      1.0.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Setup editor styles for better theme integration
 */
function hk_fs_setup_editor_styles() {
	// Add theme support for editor styles if not already added
	if (!current_theme_supports('editor-styles')) {
		add_theme_support('editor-styles');
	}
	
	// Add theme support for default block styles if not already added
	if (!current_theme_supports('wp-block-styles')) {
		add_theme_support('wp-block-styles');
	}
	
	// Support wide alignment
	add_theme_support('align-wide');
}
add_action('after_setup_theme', 'hk_fs_setup_editor_styles', 11); // After theme's own setup

/**
 * Register custom block category for funeral suite blocks
 */
function hk_fs_register_block_category($categories) {
	return array_merge(
		$categories,
		array(
			array(
				'slug' => 'hk-funeral-suite',
				'title' => __('HK Funeral Suite', 'hk-funeral-cpt'),
				'icon'  => 'admin-site',
			),
		)
	);
}
add_filter('block_categories_all', 'hk_fs_register_block_category', 10, 1);

/**
 * Create block editor styles with system fonts
 */
function hk_fs_create_system_font_styles() {
	$css_dir = HK_FS_PLUGIN_DIR . 'includes/blocks/assets/';
	$css_file = $css_dir . 'block-editor-styles.css';
	
	// Create directory if it doesn't exist
	if (!file_exists($css_dir)) {
		wp_mkdir_p($css_dir);
	}
	
	// Only create the file if it doesn't exist
	if (!file_exists($css_file)) {
		$css = "/**
 * Block Editor Styles for HK Funeral Suite
 * Using WordPress default system fonts
 */

:root {
	--wp-admin-theme-color: #007cba;
	--wp-system-font: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Oxygen-Sans, Ubuntu, Cantarell, \"Helvetica Neue\", sans-serif;
}

/* Global editor typography adjustments */
.editor-styles-wrapper {
	font-family: var(--hk-fs-font-family) !important;
	font-size: 16px;
	line-height: 1.6;
	color: #333;
	font-weight: var(--hk-fs-body-weight);
	letter-spacing: 0.01em;
}
.editor-styles-wrapper p {
	font-family: var(--hk-fs-font-family) !important;
	font-size: 16px;
	line-height: 1.6;
	margin-bottom: 1em;
	font-weight: var(--hk-fs-body-weight);
}
.editor-styles-wrapper h1, 
.editor-styles-wrapper h2, 
.editor-styles-wrapper h3, 
.editor-styles-wrapper h4, 
.editor-styles-wrapper h5, 
.editor-styles-wrapper h6 {
	font-family: var(--hk-fs-font-family) !important;
	font-weight: var(--hk-fs-heading-weight);
	line-height: 1.3;
	margin-top: 1.0em;
	margin-bottom: 0.5em;
	letter-spacing: 0.01em;
}
.editor-styles-wrapper h1 { font-size: 32px; }
.editor-styles-wrapper h2 { font-size: 28px; }
.editor-styles-wrapper h3 { font-size: 24px; }
.editor-styles-wrapper h4 { font-size: 20px; }
.editor-styles-wrapper h5 { font-size: 18px; }
.editor-styles-wrapper h6 { font-size: 16px; }

/* Team Member block styling */
.team-member-block {
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
	font-family: var(--hk-fs-font-family) !important;
}
.team-member-section-title {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	font-weight: var(--hk-fs-heading-weight);
	color: #1e1e1e;
	font-family: var(--hk-fs-font-family) !important;
}
.team-member-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 15px;
}
.team-member-fields .components-base-control .components-base-control__label,
.team-member-fields .components-truncate.components-text.components-input-control__label {
	font-weight: 500;
	font-size: 14px;
	font-family: var(--hk-fs-font-family) !important;
}
.team-member-fields .components-text-control__input,
.team-member-fields .components-select-control__input {
	font-size: 15px;
	padding: 8px 12px;
	font-family: var(--hk-fs-font-family) !important;
}


.team-member-image-section {
	margin-bottom: 20px;
	border: 2px solid #00a0d2; /* Highlight blue border for debugging */
	padding: 10px;
}

.team-member-featured-image-container {
	margin-bottom: 15px;
}

.team-member-image-preview {
	margin-bottom: 10px;
	border: 1px solid #e2e4e7;
	padding: 5px;
	background: #fff;
	max-width: 250px;
}

.team-member-image-buttons {
	display: flex;
	margin-bottom: 15px;
}

/* Space between sections */
.team-member-editor > h3:not(:first-child) {
	margin-top: 25px;
}

/* Make sure the image section is visible */
.team-member-section-title {
	color: #23282d;
	font-weight: 600;
}

/* Debug border for the main block */
.team-member-block {
	border: 2px dashed #ddd; /* Visible border */
	padding: 15px;
}


/* Pricing Package block styling */
.pricing-package-block {
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
	font-family: var(--hk-fs-font-family) !important;
}
.pricing-package-section-title {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	font-weight: var(--hk-fs-heading-weight);
	color: #1e1e1e;
	font-family: var(--hk-fs-font-family) !important;
}
.pricing-package-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 15px;
}
.pricing-package-fields .components-base-control .components-base-control__label,
.pricing-package-fields .components-truncate.components-text.components-input-control__label {
	font-weight: 500;
	font-size: 14px;
	font-family: var(--hk-fs-font-family) !important;
}
.pricing-package-fields .components-text-control__input,
.pricing-package-fields .components-select-control__input {
	font-size: 15px;
	padding: 8px 12px;
	font-family: var(--hk-fs-font-family) !important;
}

/* Casket block styling */
.casket-block {
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
	font-family: var(--hk-fs-font-family) !important;
}
.casket-section-title {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	font-weight: var(--hk-fs-heading-weight);
	color: #1e1e1e;
	font-family: var(--hk-fs-font-family) !important;
}
.casket-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 15px;
}
.casket-fields .components-base-control .components-base-control__label,
.casket-fields .components-truncate.components-text.components-input-control__label {
	font-weight: 500;
	font-size: 14px;
	font-family: var(--hk-fs-font-family) !important;
}
.casket-fields .components-text-control__input,
.casket-fields .components-select-control__input {
	font-size: 15px;
	padding: 8px 12px;
	font-family: var(--hk-fs-font-family) !important;
}

/* Casket Image Section */
.casket-image-section {
	margin-bottom: 20px;
	border: 2px solid #00a0d2; /* Same highlight blue for debugging */
	padding: 10px;
	background: #f8f9fa; /* Light grey background */
}

.casket-featured-image-container {
	margin-bottom: 15px;
}

.casket-image-preview {
	margin-bottom: 10px;
	border: 1px solid #e2e4e7;
	padding: 5px;
	background: #fff;
	max-width: 250px;
}

.casket-image-buttons {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
}

/* General Section Styling */
.casket-editor > h3:not(:first-child) {
	margin-top: 25px;
}

.casket-fields {
	margin-top: 20px;
}

/* Urn block styling */
.urn-block {
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
	font-family: var(--hk-fs-font-family) !important;
}
.urn-section-title {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	font-weight: var(--hk-fs-heading-weight);
	color: #1e1e1e;
	font-family: var(--hk-fs-font-family) !important;
}
.urn-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 15px;
}
.urn-fields .components-base-control .components-base-control__label,
.urn-fields .components-truncate.components-text.components-input-control__label  {
	font-weight: 500;
	font-size: 14px;
	font-family: var(--hk-fs-font-family) !important;
}
.urn-fields .components-text-control__input,
.urn-fields .components-select-control__input {
	font-size: 15px;
	padding: 8px 12px;
	font-family: var(--hk-fs-font-family) !important;
}

.urn-image-section {
	margin-bottom: 20px;
	border: 2px solid #00a0d2;
	padding: 10px;
}

.urn-featured-image-container {
	margin-bottom: 15px;
}

.urn-image-preview {
	margin-bottom: 10px;
	border: 1px solid #e2e4e7;
	padding: 5px;
	background: #fff;
	max-width: 250px;
}

.urn-image-buttons {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
}

.monument-block {
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
	font-family: var(--hk-fs-font-family) !important;
}
.monument-section-title {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	font-weight: var(--hk-fs-heading-weight);
	color: #1e1e1e;
	font-family: var(--hk-fs-font-family) !important;
}
.monument-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 15px;
}
.monument-fields .components-base-control .components-base-control__label,
.monument-fields .components-truncate.components-text.components-input-control__label  {
	font-weight: 500;
	font-size: 14px;
	font-family: var(--hk-fs-font-family) !important;
}
.monument-fields .components-text-control__input,
.monument-fields .components-select-control__input {
	font-size: 15px;
	padding: 8px 12px;
	font-family: var(--hk-fs-font-family) !important;
}

.monument-image-section {
	margin-bottom: 20px;
	border: 2px solid #00a0d2;
	padding: 10px;
}

.monument-featured-image-container {
	margin-bottom: 15px;
}

.monument-image-preview {
	margin-bottom: 10px;
	border: 1px solid #e2e4e7;
	padding: 5px;
	background: #fff;
	max-width: 250px;
}

.monument-image-buttons {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
}

/* Google Sheets integration styles for monument block */
.monument-block .components-text-control__input:disabled {
    background-color: #f0f0f1;
    border-color: #dcdcde;
    color: #8c8f94;
    box-shadow: none;
}

.monument-block .sheet-integration-notice {
    background-color: rgba(214, 54, 56, 0.05);
    border-left: 4px solid #d63638;
    padding: 8px;
    margin-top: 5px;
    margin-bottom: 15px;
    border-radius: 2px;
}

/* Make sure selects have the same styling */
.components-select-control__input {
	height: auto !important;
	line-height: normal !important;
}

/* Override WordPress default fonts in specific elements */
.editor-styles-wrapper button,
.editor-styles-wrapper select,
.editor-styles-wrapper textarea,
.editor-styles-wrapper input {
	font-family: var(--hk-fs-font-family) !important;
}

/* Responsive adjustment for all block grids */
@media (max-width: 782px) {
	.team-member-fields,
	.pricing-package-fields,
	.casket-fields,
	.urn-fields {
		grid-template-columns: 1fr;
	}
}";
		
		// Write CSS to file
		file_put_contents($css_file, $css);
	}

	return $css_file;
}

/**
 * Enqueue editor styles with system fonts
 */
function hk_fs_enqueue_editor_styles() {
	// Create CSS file if it doesn't exist
	$css_file = hk_fs_create_system_font_styles();
	
	if (file_exists($css_file)) {
		wp_enqueue_style(
			'hk-fs-block-editor-styles',
			HK_FS_PLUGIN_URL . 'includes/blocks/assets/block-editor-styles.css',
			array('wp-edit-blocks'),
			HK_FS_VERSION
		);
	}
}
add_action('enqueue_block_editor_assets', 'hk_fs_enqueue_editor_styles');

/**
 * Add body class to editor for CSS targeting
 */
function hk_fs_add_editor_body_class($classes) {
	global $post;
	
	if (isset($post)) {
		$our_post_types = array('hk_fs_staff', 'hk_fs_casket', 'hk_fs_urn', 'hk_fs_package');
		
		if (in_array($post->post_type, $our_post_types)) {
			$classes .= ' post-type-' . $post->post_type;
		}
	}
	
	return $classes;
}
add_filter('admin_body_class', 'hk_fs_add_editor_body_class');
