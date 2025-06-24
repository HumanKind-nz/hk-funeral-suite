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
            'svg_icon' => '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.50001 9.5C7.50001 9.08579 7.83579 8.75 8.25001 8.75H15.75C16.1642 8.75 16.5 9.08579 16.5 9.5C16.5 9.91421 16.1642 10.25 15.75 10.25H8.25001C7.83579 10.25 7.50001 9.91421 7.50001 9.5Z" fill="%23a7aaad"/><path d="M9.75001 11.75C9.33579 11.75 9.00001 12.0858 9.00001 12.5C9.00001 12.9142 9.33579 13.25 9.75001 13.25H14.25C14.6642 13.25 15 12.9142 15 12.5C15 12.0858 14.6642 11.75 14.25 11.75H9.75001Z" fill="%23a7aaad"/><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C7.85787 2 4.50001 5.35786 4.50001 9.5V18.8676C3.88244 19.2626 3.29812 19.7049 2.7522 20.1893C2.44239 20.4643 2.41411 20.9383 2.68904 21.2481C2.96397 21.5579 3.438 21.5862 3.74782 21.3113C5.94402 19.3624 8.83299 18.1797 12 18.1797C15.167 18.1797 18.056 19.3624 20.2522 21.3113C20.562 21.5862 21.036 21.5579 21.311 21.2481C21.5859 20.9383 21.5576 20.4643 21.2478 20.1893C20.7019 19.7049 20.1176 19.2626 19.5 18.8676V9.5C19.5 5.35786 16.1421 2 12 2ZM18 18.0338L18 9.5C18 6.18629 15.3137 3.5 12 3.5C8.6863 3.5 6.00001 6.18629 6.00001 9.5V18.0338C7.81735 17.1658 9.85224 16.6797 12 16.6797C14.1478 16.6797 16.1827 17.1658 18 18.0338Z" fill="%23a7aaad"/></svg>'
        ]);
    }
    
    if ($settings->is_cpt_enabled('keepsakes')) {
        HK_Funeral_Product_CPT_Factory::register_product_cpt([
            'post_type' => 'keepsake', 
            'singular' => __('Keepsake', 'hk-funeral-cpt'),
            'plural' => __('Keepsakes', 'hk-funeral-cpt'),
            'slug' => 'keepsakes',
            'svg_icon' => '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M20.4907 3.87809C20.5607 3.46809 20.2807 3.08809 19.8707 3.01809C19.4707 2.94809 19.0807 3.22809 19.0107 3.63809C18.4507 7.00809 16.0507 15.7581 11.7507 15.7581C7.45073 15.7581 5.05073 7.00809 4.49073 3.63809C4.42073 3.22809 4.03073 2.94809 3.63073 3.00809C3.22073 3.08809 2.94073 3.46809 3.01073 3.87809C3.09073 4.38809 5.02073 15.5581 10.4907 17.0781C10.4407 17.1581 10.4007 17.2281 10.3507 17.3181C10.1607 17.6481 9.95073 18.0281 9.80073 18.3881C9.66073 18.7181 9.50073 19.1281 9.50073 19.5081C9.50073 20.7481 10.5107 21.7581 11.7507 21.7581C12.9907 21.7581 14.0007 20.7481 14.0007 19.5081C14.0007 19.1281 13.8507 18.7181 13.7007 18.3881C13.5407 18.0281 13.3407 17.6581 13.1507 17.3181C13.1007 17.2381 13.0507 17.1581 13.0107 17.0781C18.4807 15.5581 20.4107 4.38809 20.4907 3.87809Z" fill="%23a7aaad"/></svg>'
        ]);
    }
}

/**
 * Initialize the CPT registrations
 * 
 * This runs after the settings system is initialized, so we can check
 * which CPTs are enabled.
 */
add_action('init', 'hk_fs_register_product_cpts', 5); // Run early, before blocks
