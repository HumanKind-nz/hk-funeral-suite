<?php
/**
 * Settings page for HumanKind Funeral Suite
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
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
		add_settings_section(
			'hk_fs_features_section',
			'Settings',
			array($this, 'render_features_section'),
			'hk-funeral-suite-settings'
		);
		
		add_settings_section(
			'hk_fs_visibility_section',
			'Public Visibility Settings (Developer)',
			array($this, 'render_visibility_section'),
			'hk-funeral-suite-settings'
		);
		
		// New section for integrations
		add_settings_section(
			'hk_fs_integrations_section',
			'External Integrations',
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
		echo '<p>Enable publicly accessible single pages and archives for each:</p>';
		echo '<p class="description">Enabling public pages will make "View" buttons appear in the editor and allow visitors to access individual pages for these items.</p>';
		echo '<p class="description"><strong>Note:</strong> After changing these settings, please visit the <a href="' . admin_url('options-permalink.php') . '">Permalinks page</a> to refresh URL structures.</p>';
	}
	
	/**
	 * Render the integrations section description
	 */
	public function render_integrations_section() {
		echo '<p>Configure external integrations with the funeral suite:</p>';
		echo '<p class="description">These settings allow your funeral website to integrate with external systems and services.</p>';
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
						<?php echo esc_html($label); ?> pricing managed via Google Sheets
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
			<strong>INTERNAL USE ONLY</strong>
			When enabled, price fields for these product types will be managed via Google Sheets integration 
			and will be disabled in the WordPress admin interface. This prevents accidental edits that would be 
			overwritten by the next Google Sheets sync. 
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
			<h1>Human Kind Funeral Suite</h1>
			<p class="description">Welcome to the HumanKind Funeral Suite. Manage your funeral website settings and enabled features below.</p>
			
			<form method="post" action="options.php">
				<?php
				settings_fields('hk_fs_settings');
				do_settings_sections('hk-funeral-suite-settings');
				submit_button();
				?>
			</form>
			<div class="hk-banner">
				<img src="<?php echo esc_url(HK_FS_PLUGIN_URL . 'assets/images/icon-256x256.png'); ?>" alt="HumanKind Funeral Suite">
			</div>
			<h2>Other Funeral Website Plugins</h2>
			<p>Check out other useful plugins we've developed for funeral websites:</p>
			<ul>
				<li><a href="https://github.com/yourgithub/funeral-plugin-1" target="_blank">Funeral Plugin 1</a></li>
				<li><a href="https://github.com/yourgithub/funeral-plugin-2" target="_blank">Funeral Plugin 2</a></li>
				<li><a href="https://github.com/yourgithub/funeral-plugin-3" target="_blank">Funeral Plugin 3</a></li>
			</ul>        
		</div>
		
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
	 * Check if rewrite rules need flushing after settings change
	 */
	public function maybe_flush_rules($old_value, $value) {
		if ($old_value !== $value) {
			// Schedule a flush of rewrite rules for the next request
			update_option('hk_fs_flush_rewrite_rules', 'yes');
		}
	}
}
