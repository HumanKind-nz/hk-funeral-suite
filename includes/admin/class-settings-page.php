<?php
/**
 * Settings page for HumanKind Funeral Suite
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.2.3
 * @since      1.0.0
 * @changelog
 *   1.2.3 - Added monuments cpt
 *   1.2.0 - Added dynamic CPT registration support
 *   1.1.2 - Removed update button
 *   1.1.0 - Added Google Sheets integration settings
 *   1.0.0 - Initial version
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Class HK_Funeral_Settings
 *
 * Handles the admin settings page and CPT configuration options. Provides a central
 * registry for all enabled custom post types and their settings. Dynamically responds
 * to CPT registrations through the factory system.
 *
 * @since 1.0.0
 */
class HK_Funeral_Settings {
	/**
	 * Instance of this class (singleton pattern).
	 *
	 * @var      object
	 * @access   private
	 * @static
	 */
	private static $instance = null;

	/**
	 * Enabled custom post types.
	 *
	 * @var      array
	 * @access   private
	 */
	private $enabled_cpts = array(
		'staff' => 'hk_fs_staff',
		'caskets' => 'hk_fs_casket',
		'urns' => 'hk_fs_urn',
		'packages' => 'hk_fs_package',
		'monuments' => 'hk_fs_monument',
		'keepsakes' => 'hk_fs_keepsake'
	);
	
	/**
	 * Product type mappings for Google Sheets integration.
	 *
	 * @var array
	 * @access private
	 */
	private $product_types = array(
		'packages' => 'package',
		'caskets' => 'casket',
		'urns' => 'urn',
		'monuments' => 'monument',
		'keepsakes' => 'keepsake'
	);
	
	/**
	 * Registry of dynamically registered CPT mappings.
	 * 
	 * @var array
	 * @access private
	 * @static
	 */
	private static $registered_cpt_map = array();

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
		
		// Setup hook listener for CPT registration
		add_action('hk_fs_register_cpt', array($this, 'register_cpt'), 10, 3);
		
		// Get enabled CPTs from options with defaults
		$saved_cpts = get_option('hk_fs_enabled_cpts', array());
		foreach ($this->enabled_cpts as $key => $slug) {
			if (!isset($saved_cpts[$key])) {
				$saved_cpts[$key] = true; // Ensure default enabled
			}
		}
		// Add dynamically registered CPTs that might not be in the default list
		foreach (self::$registered_cpt_map as $key => $data) {
		    if (!isset($saved_cpts[$key])) {
		        $saved_cpts[$key] = true; // Default new CPTs to enabled
		    }
		}
		$this->enabled_cpts = $saved_cpts;
	}
	
	/**
	 * Register a custom post type with the settings system
	 *
	 * This method is called via the 'hk_fs_register_cpt' action hook when a new
	 * CPT is registered through the factory. It adds the CPT to the settings
	 * system and sets up necessary option registrations.
	 *
	 * @param string $post_type       The post type (without hk_fs_ prefix)
	 * @param string $option_suffix   The suffix to use for option names
	 * @param array  $args            Additional arguments about the CPT
	 */
	public function register_cpt($post_type, $option_suffix, $args) {
	    // Store in static registry for future instances
	    self::$registered_cpt_map[$option_suffix] = array(
	        'post_type' => $post_type,
	        'slug' => "hk_fs_{$post_type}",
	        'singular' => $args['singular'],
	        'plural' => $args['plural'],
	        'option_suffix' => $option_suffix
	    );
	    
	    // Add to product types for Google Sheets if it's a product CPT
	    $this->product_types[$option_suffix] = $post_type;
	    
	    // Register settings specific to this CPT
	    add_action('admin_init', function() use ($option_suffix) {
	        // Register public/visibility setting
	        register_setting('hk_fs_settings', "hk_fs_enable_public_{$option_suffix}", array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ));
            
            // Register Google Sheets integration setting
            register_setting('hk_fs_settings', "hk_fs_{$option_suffix}_price_google_sheets", array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ));
            
            // Set up rewrite rule handler
            add_action("update_option_hk_fs_enable_public_{$option_suffix}", 'hk_fs_handle_public_option_changes', 10, 2);
	    });
	    
	    // Make sure this CPT is in the enabled_cpts option
	    $enabled_cpts = get_option('hk_fs_enabled_cpts', array());
	    if (!isset($enabled_cpts[$option_suffix])) {
	        $enabled_cpts[$option_suffix] = true; // Default to enabled
	        update_option('hk_fs_enabled_cpts', $enabled_cpts);
	    }
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
		
		// Section for integrations
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
		
		// Register CPT visibility settings for core types
		$core_cpts = array('staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes');
		foreach ($core_cpts as $cpt) {
		    register_setting('hk_fs_settings', "hk_fs_enable_public_{$cpt}", array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ));
		}
		
		// Register Google Sheets integration settings for core product types
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
	 * 
	 * Renders checkboxes for enabling/disabling different CPTs, including any that
	 * were dynamically registered through the factory.
	 */
	public function render_features_field() {
		// Get all CPTs to display - combine core and registered
		$all_cpts = array_merge(
		    array('staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes'), // Core CPTs
		    array_keys(self::$registered_cpt_map) // Dynamically registered CPTs
		);
		$all_cpts = array_unique($all_cpts); // Remove duplicates in case a core CPT was re-registered
		
		?>
		<fieldset>
			<?php foreach ($all_cpts as $cpt) : 
			    // Get proper display name
			    $display_name = ucfirst($cpt);
			    if (isset(self::$registered_cpt_map[$cpt]) && isset(self::$registered_cpt_map[$cpt]['plural'])) {
			        $display_name = self::$registered_cpt_map[$cpt]['plural'];
			    }
			?>
				<label>
					<input type="checkbox" name="hk_fs_enabled_cpts[<?php echo esc_attr($cpt); ?>]" value="1" 
						<?php checked($this->is_cpt_enabled($cpt)); ?>>
					<?php echo esc_html($display_name); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">Enable or disable different features in your funeral website.</p>
		<?php
	}
	
	/**
	 * Render the visibility field
	 * 
	 * Renders controls for the public visibility of each CPT, including
	 * dynamically registered ones.
	 */
	public function render_visibility_field() {
		// Get all CPTs to display - combine core and registered
		$all_cpts = array_merge(
		    array('staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes'), // Core CPTs
		    array_keys(self::$registered_cpt_map) // Dynamically registered CPTs
		);
		$all_cpts = array_unique($all_cpts); // Remove duplicates in case a core CPT was re-registered
		
		?>
		<fieldset>
			<?php foreach ($all_cpts as $cpt) : 
				$option_name = 'hk_fs_enable_public_' . $cpt;
				$is_public = get_option($option_name, false);
				$is_enabled = $this->is_cpt_enabled($cpt);
				$disabled = !$is_enabled ? 'disabled="disabled"' : '';
				
				// Get proper display name
			    $display_name = ucfirst($cpt);
			    if (isset(self::$registered_cpt_map[$cpt]) && isset(self::$registered_cpt_map[$cpt]['plural'])) {
			        $display_name = self::$registered_cpt_map[$cpt]['plural'];
			    }
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr($option_name); ?>" value="1" 
						<?php checked($is_public, true); ?> <?php echo $disabled; ?>>
					<?php echo esc_html($display_name); ?>
				</label>
				<?php if (!$is_enabled) : ?>
					<span class="description" style="color:#999; font-style:italic;">
						(Disabled - enable the <?php echo esc_html($display_name); ?> feature first)
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
	 * 
	 * Renders controls for Google Sheets price integration, including
	 * options for dynamically registered product CPTs.
	 */
	public function render_google_sheets_field() {
		// Get all product types to display from the product_types property
		$all_product_types = $this->product_types;
		?>
		<fieldset>
			<?php foreach ($all_product_types as $settings_key => $api_key) :
				$option_name = "hk_fs_{$api_key}_price_google_sheets";
				$is_managed = get_option($option_name, false);
				$is_enabled = $this->is_cpt_enabled($settings_key);
				$disabled = !$is_enabled ? 'disabled="disabled"' : '';
				
				// Get proper display name
				$display_name = ucfirst($settings_key);
				if (isset(self::$registered_cpt_map[$settings_key]) && isset(self::$registered_cpt_map[$settings_key]['plural'])) {
			        $display_name = self::$registered_cpt_map[$settings_key]['plural'];
			    }
				?>
				<div style="margin-bottom: 10px;">
					<label>
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>" value="1" 
							<?php checked($is_managed, true); ?> <?php echo $disabled; ?>>
						<?php echo esc_html($display_name); ?> pricing is currently managed via Google Sheets
					</label>
					<?php if (!$is_enabled) : ?>
						<span class="description" style="color:#999; font-style:italic; margin-left:10px;">
							(Disabled - enable the <?php echo esc_html($display_name); ?> feature first)
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
		<p class="description" style="color: #d63638;">
			<strong>Important:</strong> After changing these settings, please reload any open edit screens to apply your changes.
		</p>
		<p class="description">
			For information on how to link a Google Sheet to updating your pricing please contact 
			<a href="https://weave.co.nz" target="_blank">Weave Digital Studio / HumanKind Funeral Website</a>.
		</p>
		<?php
	}

	/**
	 * Sanitize CPT settings
	 *
	 * @param array $input Raw input from the form
	 * @return array Sanitized settings
	 */
	public function sanitize_cpt_settings($input) {
		// Get list of valid CPTs (core + dynamically registered)
		$valid_cpts = array_merge(
		    array('staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes'), // Core CPTs
		    array_keys(self::$registered_cpt_map) // Dynamically registered CPTs
		);
		$valid_cpts = array_unique($valid_cpts);
		
		// Start with all CPTs disabled
		$sanitized = array_fill_keys($valid_cpts, false);
		
		// Enable the ones that were checked
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
	 *
	 * @param string $key The CPT key
	 * @return string The full CPT slug
	 */
	private function get_cpt_slug($key) {
		$cpt_slugs = array(
			'staff' => 'hk_fs_staff',
			'caskets' => 'hk_fs_casket',
			'urns' => 'hk_fs_urn',
			'packages' => 'hk_fs_package',
			'monuments' => 'hk_fs_monument'
		);
		
		// Add dynamically registered CPTs
		foreach (self::$registered_cpt_map as $option_suffix => $data) {
			$cpt_slugs[$option_suffix] = $data['slug'];
		}
		
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
				var sheetsCheckbox = $('input[name^="hk_fs_"][name$="_price_google_sheets"]').filter(function() {
				    // Find the checkbox that has this CPT in its name
				    return this.name.indexOf(cptName) !== -1 || this.name.indexOf(cptName.slice(0, -1)) !== -1;
				});
				
				if (sheetsCheckbox.length && $(this).is(':checked')) {
					sheetsCheckbox.prop('disabled', false);
					sheetsCheckbox.closest('label').next('.description').hide();
				} else if (sheetsCheckbox.length) {
					sheetsCheckbox.prop('disabled', true);
					sheetsCheckbox.prop('checked', false);
					sheetsCheckbox.closest('div').find('.description').show();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Custom implementation of do_settings_sections to maintain the same output format
	 * but give us more control over the HTML structure
	 * 
	 * @param string $section_id The ID of the section to render
	 * @param string $page The page slug
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
	 * 
	 * @param mixed $old_value The old option value
	 * @param mixed $value The new option value
	 */
	public function maybe_flush_rules($old_value, $value) {
		if ($old_value !== $value) {
			// Schedule a flush of rewrite rules for the next request
			update_option('hk_fs_flush_rewrite_rules', 'yes');
		}
	}
}
