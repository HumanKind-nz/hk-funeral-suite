<?php
/**
 * CPT Registration using Factory
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.1
 * @since      1.3.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register all product CPTs using the factory
 */
function hk_fs_register_product_cpts() {
    $settings = HK_Funeral_Settings::get_instance();
    
    // Register only enabled CPTs
    if ($settings->is_cpt_enabled('caskets')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'casket', 
            'singular' => 'Casket',
            'plural' => 'Caskets',
            'slug' => 'caskets',
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="%23a7aaad" d="M406.5 115.2l-107.8-105.9C292.6 3.375 284.3 0 275.6 0H172.4C163.7 0 155.4 3.375 149.2 9.375L41.46 115.2c-8.002 7.875-11.25 19.38-8.502 30.38l87.14 342.1C123.7 502 136.7 512 151.7 512h144.7c14.88 0 27.88-9.1 31.51-24.25l87.14-342.1C417.8 134.6 414.5 123.1 406.5 115.2zM284.5 464H163.5l-81.64-321.1L178.5 48h91.02l96.64 94.88L284.5 464z"/></svg>'
        ]);
    }
    
    if ($settings->is_cpt_enabled('urns')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'urn', 
            'singular' => 'Urn',
            'plural' => 'Urns',
            'slug' => 'urns',
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23a7aaad" d="M73.744,19.624c-0.748-1.007-2.44-1.765-3.691-1.765H29.946c-1.251,0-2.943,0.758-3.692,1.765c-3.211,4.291-8.396,13.58-8.396,30.381c0,13.462,3.378,23.809,7.176,31.244c2.917,5.719,6.092,9.711,8.154,12.069C34.015,94.262,35.73,95,36.981,95h26.036c1.252,0,2.966-0.738,3.792-1.683c2.062-2.358,5.238-6.351,8.154-12.069c3.798-7.435,7.176-17.781,7.176-31.244C82.14,33.204,76.955,23.915,73.744,19.624z M62.857,88.573H37.141c0,0-12.855-12.854-12.855-38.569c0-17.903,6.427-25.718,6.427-25.718h38.573c0,0,6.427,7.815,6.427,25.718C75.713,75.72,62.857,88.573,62.857,88.573z"/></svg>'
        ]);
    }
    
    // Add new CPTs here when needed
    // For example:
    /*
    if ($settings->is_cpt_enabled('monuments')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'monument', 
            'singular' => 'Monument',
            'plural' => 'Monuments',
            'slug' => 'monuments'
        ]);
    }
    
    if ($settings->is_cpt_enabled('keepsakes')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'keepsake', 
            'singular' => 'Keepsake',
            'plural' => 'Keepsakes',
            'slug' => 'keepsakes'
        ]);
    }
    */
}

/**
 * Helper function to register rewrite rule change handlers
 * 
 * @param string $option_name The option name to monitor for changes
 */
function hk_fs_register_rewrite_handlers($option_name) {
    add_action("update_option_{$option_name}", 'hk_fs_handle_public_option_changes', 10, 2);
}

// Initialize the CPT registrations
add_action('init', 'hk_fs_register_product_cpts', 5); // Run early, before blocks
