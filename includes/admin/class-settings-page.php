<?php
/**
 * Settings page for HumanKind Funeral Suite
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * v1.1.2 - removed update button
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Class HK_Funeral_Settings
 *
 * Handles the admin settings page and CPT configuration options.
 */
class HK_Funeral_Settings {
	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	private static $instance = null;

	/**
	 * Enabled custom post types.
	 *
	 * @var      array
	 */
	private $enabled_cpts = array(
		'staff' => 'hk_fs_staff',
		'caskets' => 'hk_fs_casket',
		'urns' => 'hk_fs_urn',
		'packages' => 'hk_fs_package'
	);
	
	/**
	 * Product type mappings for Google Sheets integration.
	 *
	 * @var array
	 */
	private $product_types = array(
		'packages' => 'package',
		'caskets' => 'casket',
		'urns' => 'urn'
	);

	/**
	 * Return an instance of this class.
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 */
	private function __construct() {
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action('admin_init', array($this, 'register_settings'));
		
		// Get enabled CPTs from options with defaults
		$saved_cpts = get_option('hk_fs_enabled_cpts', array());
		foreach ($this->enabled_cpts as $key => $slug) {
			if (!isset($saved_cpts[$key])) {
				$saved_cpts[$key] = true; // Ensure default enabled
			}
		}
		$this->enabled_cpts = $saved_cpts;
	}

	/**
	 * Register the settings menu page
	 */
	public function add_menu_page() {
		add_options_page(
			'Human Kind Funeral Suite', // Page title
			'HK Funeral Suite', // Menu title 
			'manage_funeral_settings', // Capability
			'hk-funeral-suite-settings', // Menu slug
			array($this, 'render_settings_page') // Callback function
		);
	}

	/**
	 * Register all settings
	 */
	public function register_settings() {
		// Register main sections
		
		// Hidden temporarily
		add_settings_section(
			'hk_fs_features_section',
			'', // Empty string instead of 'Settings' to temp hide the heading
			'__return_false', // Use WordPress's built-in function that returns false instead of the callback
			'hk-funeral-suite-settings'
		);
		
		add_settings_section(
			'hk_fs_visibility_section',
			'Public Visibility Settings',
			array($this, 'render_visibility_section'),
			'hk-funeral-suite-settings'
		);
		
		// New section for integrations
		add_settings_section(
			'hk_fs_integrations_section',
			'Google Sheets Data Sync',
			array($this, 'render_integrations_section'),
			'hk-funeral-suite-settings'
		);
		
		// Register CPT enabled/disabled settings
		register_setting('hk_fs_settings', 'hk_fs_enabled_cpts', array(
			'type' => 'array',
			'sanitize_callback' => array($this, 'sanitize_cpt_settings'),
			'default' => array(
				'staff' => true,
				'caskets' => true,
				'urns' => true,
				'packages' => true
			)
		));
		
		// Register CPT visibility settings
		register_setting('hk_fs_settings', 'hk_fs_enable_public_staff', array(
			'type' => 'boolean',
			'default' => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		));
		
		register_setting('hk_fs_settings', 'hk_fs_enable_public_caskets', array(
			'type' => 'boolean',
			'default' => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		));
		
		register_setting('hk_fs_settings', 'hk_fs_enable_public_urns', array(
			'type' => 'boolean',
			'default' => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		));
		
		register_setting('hk_fs_settings', 'hk_fs_enable_public_packages', array(
			'type' => 'boolean',
			'default' => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		));
		
		// Register Google Sheets integration settings
		foreach ($this->product_types as $settings_key => $api_key) {
			register_setting('hk_fs_settings', "hk_fs_{$api_key}_price_google_sheets", array(
				'type' => 'boolean',
				'default' => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			));
		}
		
		// Add fields for feature enablement
		add_settings_field(
			'hk_fs_features_field',
			'Enable / Disable Funeral Content Types',
			array($this, 'render_features_field'),
			'hk-funeral-suite-settings',
			'hk_fs_features_section'
		);
		
		// Add fields for public visibility
		add_settings_field(
			'hk_fs_visibility_field',
			'Public Pages',
			array($this, 'render_visibility_field'),
			'hk-funeral-suite-settings',
			'hk_fs_visibility_section'
		);
		
		// Add fields for Google Sheets integration
		add_settings_field(
			'hk_fs_google_sheets_field',
			'Google Sheets Price Management',
			array($this, 'render_google_sheets_field'),
			'hk-funeral-suite-settings',
			'hk_fs_integrations_section'
		);
		
		// Add action to flush rewrite rules after settings changes
		add_action('update_option_hk_fs_enabled_cpts', array($this, 'maybe_flush_rules'), 10, 2);
		add_action('update_option_hk_fs_enable_public_staff', array($this, 'maybe_flush_rules'), 10, 2);
		add_action('update_option_hk_fs_enable_public_caskets', array($this, 'maybe_flush_rules'), 10, 2);
		add_action('update_option_hk_fs_enable_public_urns', array($this, 'maybe_flush_rules'), 10, 2);
		add_action('update_option_hk_fs_enable_public_packages', array($this, 'maybe_flush_rules'), 10, 2);
	}

	/**
	 * Render the features section description
	 */
	public function render_features_section() {
		echo '<p>Enable or disable the features in your funeral website:</p>';
	}
	
	/**
	 * Render the visibility section description
	 */
	public function render_visibility_section() {
		echo '<p>Enable a publicly accessible single pages and archives for each:</p>';
		echo '<p class="description">Enabling public pages will make "View" buttons appear in the editor and allow visitors to access individual pages for these items. Not needed if creating your own loops.</p>';
		echo '<p class="description"><strong>Note:</strong> After changing these settings, please visit the <a href="' . admin_url('options-permalink.php') . '">Permalinks page</a> to refresh URL structures.</p>';
	}
	
	/**
	 * Render the integrations section description
	 */
	public function render_integrations_section() {
		echo '<p>Enable Google Sheets data integration with the funeral site:</p>';
		echo '<p class="description">Currently used when updating your product pricing via Google Sheets</p>';
	}

	/**
	 * Render the features field
	 */
	public function render_features_field() {
		?>
		<fieldset>
			<?php foreach (array('staff', 'caskets', 'urns', 'packages') as $cpt) : ?>
				<label>
					<input type="checkbox" name="hk_fs_enabled_cpts[<?php echo esc_attr($cpt); ?>]" value="1" 
						<?php checked($this->is_cpt_enabled($cpt)); ?>>
					<?php echo esc_html(ucfirst($cpt)); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">Enable or disable different features in your funeral website.</p>
		<?php
	}
	
	/**
	 * Render the visibility field
	 */
	public function render_visibility_field() {
		?>
		<fieldset>
			<?php foreach (array('staff', 'caskets', 'urns', 'packages') as $cpt) : 
				$option_name = 'hk_fs_enable_public_' . $cpt;
				$is_public = get_option($option_name, false);
				$is_enabled = $this->is_cpt_enabled($cpt);
				$disabled = !$is_enabled ? 'disabled="disabled"' : '';
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr($option_name); ?>" value="1" 
						<?php checked($is_public, true); ?> <?php echo $disabled; ?>>
					<?php echo esc_html(ucfirst($cpt)); ?>
				</label>
				<?php if (!$is_enabled) : ?>
					<span class="description" style="color:#999; font-style:italic;">
						(Disabled - enable the <?php echo esc_html(ucfirst($cpt)); ?> feature first)
					</span>
				<?php endif; ?>
				<br>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">Enable public single pages and archives for selected content types.</p>
		<?php
	}
	
	/**
	 * Render the Google Sheets integration field
	 */
	public function render_google_sheets_field() {
		?>
		<fieldset>
			<?php foreach ($this->product_types as $settings_key => $api_key) :
				$option_name = "hk_fs_{$api_key}_price_google_sheets";
				$is_managed = get_option($option_name, false);
				$is_enabled = $this->is_cpt_enabled($settings_key);
				$disabled = !$is_enabled ? 'disabled="disabled"' : '';
				$label = ucfirst($settings_key);
				?>
				<div style="margin-bottom: 10px;">
					<label>
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>" value="1" 
							<?php checked($is_managed, true); ?> <?php echo $disabled; ?>>
						<?php echo esc_html($label); ?> pricing is currently managed via Google Sheets
					</label>
					<?php if (!$is_enabled) : ?>
						<span class="description" style="color:#999; font-style:italic; margin-left:10px;">
							(Disabled - enable the <?php echo esc_html($label); ?> feature first)
						</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			When enabled, the price fields for these product types will be managed via Google Sheets integration 
			and will be disabled in the WordPress admin interface. This prevents accidental edits that would be 
			overwritten by the next Google Sheets sync. 
		</p>
		<p class="description">
			For information on how to link a Google Sheet to updating your pricing please contact 
			<a href="https://weave.co.nz" target="_blank">Weave Digital Studio / HumanKind Funeral Website</a>.
		</p>
		<?php
	}

	/**
	 * Sanitize CPT settings
	 */
	public function sanitize_cpt_settings($input) {
		$valid_cpts = array('staff', 'caskets', 'urns', 'packages');
		$sanitized = array_fill_keys($valid_cpts, false);
		if (is_array($input)) {
			foreach ($input as $key => $value) {
				if (in_array($key, $valid_cpts)) {
					$sanitized[$key] = (bool) $value;
				}
			}
		}
		return $sanitized;
	}

	/**
	 * Get the correct CPT slug
	 */
	private function get_cpt_slug($key) {
		$cpt_slugs = array(
			'staff' => 'hk_fs_staff',
			'caskets' => 'hk_fs_casket',
			'urns' => 'hk_fs_urn',
			'packages' => 'hk_fs_package'
		);
		return isset($cpt_slugs[$key]) ? $cpt_slugs[$key] : '';
	}

	/**
	 * Check if a CPT is enabled
	 *
	 * @param string $cpt The custom post type key.
	 * @return bool Whether the CPT is enabled.
	 */
	public function is_cpt_enabled($cpt) {
		return isset($this->enabled_cpts[$cpt]) && $this->enabled_cpts[$cpt];
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page() {
		if (!HK_Funeral_Capabilities::can('manage_funeral_settings')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'hk-funeral-suite'));
		}
		?>
		<div class="wrap">
			<div class="hk-banner">
				<img src="<?php echo esc_url(HK_FS_PLUGIN_URL . 'assets/images/hk-funeral-suite-banner.png'); ?>" alt="Human Kind Funeral Suite">
			</div>
			<h1>Welcome to the HumanKind Funeral Suite.</h1>
			<p class="description">Manage your funeral website products and features and their settings below.</p>
			
			<form method="post" action="options.php">
				<?php
				settings_fields('hk_fs_settings');
				
				// Output the main features section normally
				$this->do_custom_settings_section('hk_fs_features_section', 'hk-funeral-suite-settings');
				
				// Start developer options container
				?>
				<div class="hk-developer-options">
					<h2 class="hk-developer-heading">Internal Use Only / Developer Options</h2>
					<?php
					// Output the visibility and integration sections inside the container
					$this->do_custom_settings_section('hk_fs_visibility_section', 'hk-funeral-suite-settings');
					$this->do_custom_settings_section('hk_fs_integrations_section', 'hk-funeral-suite-settings');
					$this->do_custom_settings_section('hk_fs_compatibility_section', 'hk-funeral-suite-settings');
					?>
				</div>
				<?php
				// Output submit button
				submit_button('Save Settings');
				?>
			</form>
			
			<div class="hk-banner">
				<img src="<?php echo esc_url(HK_FS_PLUGIN_URL . 'assets/images/icon-256x256.png'); ?>" alt="HumanKind Funeral Suite">
			</div>
			<h2>Other HumanKind Funeral Website Plugins</h2>
			<p>Check out other useful WordPress plugins we've developed for funeral websites:</p>
			<ul>
				<li><a href="https://github.com/yourgithub/funeral-plugin-1" target="_blank">Funeral Plugin 1</a></li>
				<li><a href="https://github.com/yourgithub/funeral-plugin-2" target="_blank">Funeral Plugin 2</a></li>
				<li><a href="https://github.com/yourgithub/funeral-plugin-3" target="_blank">Funeral Plugin 3</a></li>
			</ul>
			<p class="hk-support-link">
				<strong>Need support, advise or want to contribute?</strong> Visit our 
				<a href="https://github.com/HumanKind-nz/hk-funeral-suite/" target="_blank">GitHub Page</a> 
				for support, feedback, or contributions.
			</p>   
		</div>
		
		<style>
			.hk-developer-options {
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 0 20px 10px;
				margin: 20px 0;
				background-color: #f8f9fa;
			}
			
			.hk-developer-heading {
				background-color: #f1f1f1;
				margin: 0 -20px 20px;
				padding: 15px 20px;
				border-bottom: 1px solid #ccd0d4;
				color: #23282d;
				font-size: 16px;
				font-weight: 500;
				text-transform: uppercase;
			}
			
			.hk-support-link {
				margin-top: 20px;
				padding: 15px;
				background-color: #f8f9fa;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
			}
			
			.hk-banner {
				margin-bottom: 20px;
			}
			
			.hk-banner img {
				max-width: 100%;
				height: auto;
				display: block; /* This makes the image block-level */
			}
			
			/* Styling specifically for the top banner */
			.hk-banner:first-of-type img {
				margin: 0 auto; /* Center the top banner only */
			}
			
			/* Styling for the bottom icon */
			.hk-banner:last-of-type {
				margin-top: 30px;
				text-align: left; /* Keep left alignment */
			}
			
			.hk-banner:last-of-type img {
				max-width: 150px; /* Limit the size of the bottom icon */
				margin-left: 0; /* Ensure left alignment */
			}
			
			/* Optional responsive adjustments for small screens */
			@media screen and (max-width: 782px) {
				.hk-banner {
					margin-bottom: 15px;
				}
			}
		</style>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Update visibility checkboxes when feature checkboxes change
			$('input[name^="hk_fs_enabled_cpts"]').change(function() {
				var cptName = $(this).attr('name').match(/\[(.*?)\]/)[1];
				var visibilityCheckbox = $('input[name="hk_fs_enable_public_' + cptName + '"]');
				
				if ($(this).is(':checked')) {
					visibilityCheckbox.prop('disabled', false);
					visibilityCheckbox.closest('label').next('.description').hide();
				} else {
					visibilityCheckbox.prop('disabled', true);
					visibilityCheckbox.prop('checked', false);
					visibilityCheckbox.closest('label').next('.description').show();
				}
				
				// Also handle Google Sheets integration checkboxes
				var productMapping = {
					'packages': 'package',
					'caskets': 'casket',
					'urns': 'urn'
				};
				
				if (productMapping[cptName]) {
					var sheetsCheckbox = $('input[name="hk_fs_' + productMapping[cptName] + '_price_google_sheets"]');
					
					if ($(this).is(':checked')) {
						sheetsCheckbox.prop('disabled', false);
						sheetsCheckbox.closest('label').next('.description').hide();
					} else {
						sheetsCheckbox.prop('disabled', true);
						sheetsCheckbox.prop('checked', false);
						sheetsCheckbox.closest('div').find('.description').show();
					}
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Custom implementation of do_settings_sections to maintain the same output format
	 * but give us more control over the HTML structure
	 */
	private function do_custom_settings_section($section_id, $page) {
		global $wp_settings_sections, $wp_settings_fields;
		
		if (!isset($wp_settings_sections[$page]) || !isset($wp_settings_sections[$page][$section_id])) {
			return;
		}
		
		$section = $wp_settings_sections[$page][$section_id];
		
		if ($section['title']) {
			echo "<h2>{$section['title']}</h2>\n";
		}
		
		if ($section['callback']) {
			call_user_func($section['callback'], $section);
		}
		
		if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section_id])) {
			return;
		}
		
		echo '<table class="form-table" role="presentation">';
		do_settings_fields($page, $section_id);
		echo '</table>';
	}
	
	/**
	 * Check if rewrite rules need flushing after settings change
	 */
	public function maybe_flush_rules($old_value, $value) {
		if ($old_value !== $value) {
			// Schedule a flush of rewrite rules for the next request
			update_option('hk_fs_flush_rewrite_rules', 'yes');
		}
	}
}
