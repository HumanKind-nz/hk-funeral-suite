<?php
/**
 * Keepsake CPT Extensions
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.0
 * @since      1.3.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register custom meta fields specifically for Keepsakes
 */
function hk_fs_register_keepsake_meta() {
    register_post_meta('hk_fs_keepsake', '_hk_fs_keepsake_product_code', array(
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));

    register_post_meta('hk_fs_keepsake', '_hk_fs_keepsake_metal', array(
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));

    register_post_meta('hk_fs_keepsake', '_hk_fs_keepsake_stones', array(
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    // Register REST API meta processing workaround for Google Sheets integration
    hk_fs_register_rest_meta_processing('keepsake');
}
add_action('init', 'hk_fs_register_keepsake_meta');

/**
 * Add meta box for keepsake specific fields
 */
function hk_fs_add_keepsake_meta_box() {
    add_meta_box(
        'hk_fs_keepsake_fields',
        __('Keepsake Details', 'hk-funeral-suite'),
        'hk_fs_render_keepsake_meta_box',
        'hk_fs_keepsake',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'hk_fs_add_keepsake_meta_box');

/**
 * Render the meta box for keepsake specific fields
 */
function hk_fs_render_keepsake_meta_box($post) {
    // Add a nonce field for security
    wp_nonce_field('hk_fs_keepsake_meta_box', 'hk_fs_keepsake_meta_box_nonce');

    // Get existing values
    $product_code = get_post_meta($post->ID, '_hk_fs_keepsake_product_code', true);
    $metal = get_post_meta($post->ID, '_hk_fs_keepsake_metal', true);
    $stones = get_post_meta($post->ID, '_hk_fs_keepsake_stones', true);

    // Output the fields
    ?>
    <style>
        .hk-meta-field {
            margin-bottom: 15px;
        }
        .hk-meta-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .hk-meta-field input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
        .hk-meta-field select {
            width: 100%;
            max-width: 400px;
        }
    </style>

    <div class="hk-meta-field">
        <label for="hk_fs_keepsake_product_code"><?php _e('Product Code', 'hk-funeral-suite'); ?></label>
        <input type="text" id="hk_fs_keepsake_product_code" name="hk_fs_keepsake_product_code" value="<?php echo esc_attr($product_code); ?>">
        <p class="description"><?php _e('Enter the product code for this keepsake', 'hk-funeral-suite'); ?></p>
    </div>

    <div class="hk-meta-field">
        <label for="hk_fs_keepsake_metal"><?php _e('Metal Type', 'hk-funeral-suite'); ?></label>
        <select id="hk_fs_keepsake_metal" name="hk_fs_keepsake_metal">
            <option value="" <?php selected($metal, ''); ?>>Select Metal</option>
            <option value="gold" <?php selected($metal, 'gold'); ?>>Gold</option>
            <option value="silver" <?php selected($metal, 'silver'); ?>>Silver</option>
            <option value="other" <?php selected($metal, 'other'); ?>>Other</option>
        </select>
        <p class="description"><?php _e('Select the metal type (if applicable)', 'hk-funeral-suite'); ?></p>
    </div>

    <div class="hk-meta-field">
        <label for="hk_fs_keepsake_stones"><?php _e('Stones', 'hk-funeral-suite'); ?></label>
        <select id="hk_fs_keepsake_stones" name="hk_fs_keepsake_stones">
            <option value="" <?php selected($stones, ''); ?>>Select Stone Type</option>
            <option value="diamond" <?php selected($stones, 'diamond'); ?>>Diamond</option>
            <option value="cubic_zirconia" <?php selected($stones, 'cubic_zirconia'); ?>>Cubic Zirconia</option>
            <option value="none" <?php selected($stones, 'none'); ?>>None</option>
            <option value="other" <?php selected($stones, 'other'); ?>>Other</option>
        </select>
        <p class="description"><?php _e('Select the stone type (if applicable)', 'hk-funeral-suite'); ?></p>
    </div>
    <?php
}

/**
 * Save the meta box data
 */
function hk_fs_save_keepsake_meta($post_id) {
    // Skip if this is a REST API request (Google Sheets integration)
    // The REST API handles meta field updates directly
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    
    // Check if nonce is set
    if (!isset($_POST['hk_fs_keepsake_meta_box_nonce'])) {
        return;
    }

    // Verify the nonce
    if (!wp_verify_nonce($_POST['hk_fs_keepsake_meta_box_nonce'], 'hk_fs_keepsake_meta_box')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the product code
    if (isset($_POST['hk_fs_keepsake_product_code'])) {
        update_post_meta(
            $post_id,
            '_hk_fs_keepsake_product_code',
            sanitize_text_field($_POST['hk_fs_keepsake_product_code'])
        );
    }

    // Save the metal type
    if (isset($_POST['hk_fs_keepsake_metal'])) {
        update_post_meta(
            $post_id,
            '_hk_fs_keepsake_metal',
            sanitize_text_field($_POST['hk_fs_keepsake_metal'])
        );
    }

    // Save the stones
    if (isset($_POST['hk_fs_keepsake_stones'])) {
        update_post_meta(
            $post_id,
            '_hk_fs_keepsake_stones',
            sanitize_text_field($_POST['hk_fs_keepsake_stones'])
        );
    }
}
add_action('save_post', 'hk_fs_save_keepsake_meta');

/**
 * Add the custom fields to the block data
 */
function hk_fs_extend_keepsake_block_data($meta_values, $post_id) {
    // Only modify for keepsake post type
    if (get_post_type($post_id) !== 'hk_fs_keepsake') {
        return $meta_values;
    }
    
    // Add custom fields to the data object
    $meta_values['material'] = get_post_meta($post_id, '_hk_fs_keepsake_material', true);
    $meta_values['dimensions'] = get_post_meta($post_id, '_hk_fs_keepsake_dimensions', true);
    $meta_values['weight'] = get_post_meta($post_id, '_hk_fs_keepsake_weight', true);
    
    return $meta_values;
}
add_filter('hk_fs_block_data', 'hk_fs_extend_keepsake_block_data', 10, 2);

/**
 * Load the keepsake extensions file when the CPT is enabled
 */
function hk_fs_load_keepsake_extensions() {
    $settings = HK_Funeral_Settings::get_instance();
    
    if ($settings->is_cpt_enabled('keepsakes')) {
        // This file is already being loaded, but in the future,
        // you might have additional files to load here
    }
}
add_action('init', 'hk_fs_load_keepsake_extensions', 20); // After CPT registration

/**
 * Hook into post save to ensure cache purging for the keepsake CPT
 */
function hk_fs_keepsake_post_save($post_id, $post) {
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Only process our post type
    if ($post->post_type !== 'hk_fs_keepsake') {
        return;
    }
    
    // Use the shared cache purging function
    if (function_exists('hk_fs_optimized_cache_purge')) {
        hk_fs_optimized_cache_purge($post_id, 'keepsake_post_save');
    }
}
add_action('save_post', 'hk_fs_keepsake_post_save', 99, 2);  // Run after all other save handlers
