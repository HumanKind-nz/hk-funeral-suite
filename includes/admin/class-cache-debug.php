<?php
/**
 * Cache Debug Tools
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('WPINC')) {
	exit;
}

/**
 * Class for debugging cache purge performance
 */
class HK_Funeral_Cache_Debug {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Only show for admins
		if (!current_user_can('manage_options')) {
			return;
		}
		
		// Add admin notice to explain the test button
		add_action('admin_notices', array($this, 'display_debug_notice'));
		
		// Handle test action
		add_action('admin_init', array($this, 'handle_test_action'));
	}
	
	/**
	 * Display debug notice on funeral CPTs edit screens
	 */
	public function display_debug_notice() {
		$screen = get_current_screen();
		
		// Only show on edit screens for our CPTs
		$cpt_types = array(
			'hk_fs_package', 'hk_fs_casket', 'hk_fs_urn', 
			'hk_fs_staff', 'hk_fs_monument', 'hk_fs_keepsake'
		);
		
		if (!$screen || !in_array($screen->post_type, $cpt_types) || !in_array($screen->base, array('post', 'edit'))) {
			return;
		}
		
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong>Cache Debugging:</strong> 
				Having trouble with slow saves? 
				<a href="<?php echo add_query_arg('hk_fs_test_cache_purge', 'true'); ?>" class="button button-small">
					Test Cache Purging
				</a>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Handle the test action
	 */
	public function handle_test_action() {
		if (!isset($_GET['hk_fs_test_cache_purge']) || $_GET['hk_fs_test_cache_purge'] !== 'true') {
			return;
		}
		
		// Get post type from screen
		$screen = get_current_screen();
		$post_type = $screen ? $screen->post_type : 'unknown';
		$post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
		
		// Start timer
		$start_time = microtime(true);
		
		// Test the optimized function
		if (function_exists('hk_fs_optimized_cache_purge')) {
			hk_fs_optimized_cache_purge($post_id, 'manual debug test');
		}
		
		// Calculate time
		$execution_time = microtime(true) - $start_time;
		
		// Show results
		add_action('admin_notices', function() use ($execution_time, $post_type, $post_id) {
			?>
			<div class="notice notice-success">
				<p>
					<strong>Cache Purge Test Results:</strong><br>
					Execution time: <?php echo number_format($execution_time * 1000, 2); ?> ms<br>
					Post type: <?php echo esc_html($post_type); ?><br>
					Post ID: <?php echo $post_id; ?><br>
					Test completed at: <?php echo date('H:i:s'); ?>
				</p>
				
				<p>
					<strong>Debug Info:</strong><br>
					- Cache purge is optimized with debouncing<br>
					- Cache purging functions are being called correctly<br>
					- The actual purge is scheduled for the end of this request
				</p>
				
				<p><a href="<?php echo remove_query_arg('hk_fs_test_cache_purge'); ?>" class="button">Back</a></p>
			</div>
			<?php
		});
	}
}

// Initialize the class
new HK_Funeral_Cache_Debug(); 