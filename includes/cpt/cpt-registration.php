<?php
/**
 * CPT Registration using Factory
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.1.1
 * @since      1.3.0
 * @changelog
 *   1.1.1 - Monuments cpt added
 *   1.1.0 - Updated to use hook system for dynamic CPT registration
 *   1.0.2 - Initial factory implementation
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register all product CPTs using the factory
 * 
 * This function demonstrates how to use the CPT factory to register product-type
 * custom post types. The factory system will automatically:
 * 
 * 1. Register the CPT with WordPress
 * 2. Set up all required taxonomies, meta fields, and UI elements
 * 3. Notify other components in the system about the new CPT
 * 4. Add the CPT to settings pages, admin columns, etc.
 */
function hk_fs_register_product_cpts() {
    $settings = HK_Funeral_Settings::get_instance();
    
    // Register only enabled CPTs
    if ($settings->is_cpt_enabled('caskets')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'casket', 
            'singular' => __('Casket', 'hk-funeral-cpt'),
            'plural' => __('Caskets', 'hk-funeral-cpt'),
            'slug' => 'caskets',
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="%23a7aaad" d="M406.5 115.2l-107.8-105.9C292.6 3.375 284.3 0 275.6 0H172.4C163.7 0 155.4 3.375 149.2 9.375L41.46 115.2c-8.002 7.875-11.25 19.38-8.502 30.38l87.14 342.1C123.7 502 136.7 512 151.7 512h144.7c14.88 0 27.88-9.1 31.51-24.25l87.14-342.1C417.8 134.6 414.5 123.1 406.5 115.2zM284.5 464H163.5l-81.64-321.1L178.5 48h91.02l96.64 94.88L284.5 464z"/></svg>'
        ]);
    }
    
    if ($settings->is_cpt_enabled('urns')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'urn', 
            'singular' => __('Urn', 'hk-funeral-cpt'),
            'plural' => __('Urns', 'hk-funeral-cpt'),
            'slug' => 'urns',
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23a7aaad" d="M73.744,19.624c-0.748-1.007-2.44-1.765-3.691-1.765H29.946c-1.251,0-2.943,0.758-3.692,1.765c-3.211,4.291-8.396,13.58-8.396,30.381c0,13.462,3.378,23.809,7.176,31.244c2.917,5.719,6.092,9.711,8.154,12.069C34.015,94.262,35.73,95,36.981,95h26.036c1.252,0,2.966-0.738,3.792-1.683c2.062-2.358,5.238-6.351,8.154-12.069c3.798-7.435,7.176-17.781,7.176-31.244C82.14,33.204,76.955,23.915,73.744,19.624z M62.857,88.573H37.141c0,0-12.855-12.854-12.855-38.569c0-17.903,6.427-25.718,6.427-25.718h38.573c0,0,6.427,7.815,6.427,25.718C75.713,75.72,62.857,88.573,62.857,88.573z"/></svg>'
        ]);
    }
    
    if ($settings->is_cpt_enabled('monuments')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'monument', 
            'singular' => __('Monument', 'hk-funeral-cpt'),
            'plural' => __('Monuments', 'hk-funeral-cpt'),
            'slug' => 'monuments',
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="%23a7aaad" d="M240 0C266.5 0 288 21.49 288 48V96H224V48C224 21.49 245.5 0 272 0zM192 352V480C192 497.7 177.7 512 160 512C142.3 512 128 497.7 128 480V352H192zM320 480C320 497.7 305.7 512 288 512C270.3 512 256 497.7 256 480V352H320V480zM80 32C106.5 32 128 53.49 128 80V96H32V80C32 53.49 53.49 32 80 32zM32 128H128V224H32V128zM32 256H128V320H32V256zM320 128H416V224H320V128zM320 256H416V320H320V256zM384 32C410.5 32 432 53.49 432 80V96H336V80C336 53.49 357.5 32 384 32zM464 32C490.5 32 512 53.49 512 80V160C512 213 469 256 416 256H448C465.7 256 480 270.3 480 288C480 305.7 465.7 320 448 320H64C46.33 320 32 305.7 32 288C32 270.3 46.33 256 64 256H96C42.98 256 0 213 0 160V80C0 53.49 21.49 32 48 32H464zM224 128V224H160V128H224zM256 128H288V224H256V128z"/></svg>'
        ]);
    }
    // Example of a new CPT - uncomment to use
    /*
    if ($settings->is_cpt_enabled('keepsakes')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'keepsake', 
            'singular' => __('Keepsake', 'hk-funeral-cpt'),
            'plural' => __('Keepsakes', 'hk-funeral-cpt'),
            'slug' => 'keepsakes'
        ]);
    }
    */
}

/**
 * Initialize the CPT registrations
 * 
 * This runs after the settings system is initialized, so we can check
 * which CPTs are enabled.
 */
add_action('init', 'hk_fs_register_product_cpts', 5); // Run early, before blocks
