<?php
/**
 * GTM Tracking Class
 *
 * Handles Google Tag Manager Enhanced Ecommerce tracking for add to cart events
 *
 * @package Q1_Shop_Custom_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Q1_Shop_GTM_Tracking
 * 
 * Handles Google Tag Manager Enhanced Ecommerce tracking for add to cart events
 */
class Q1_Shop_GTM_Tracking {
    
    /**
     * Script version
     */
    const SCRIPT_VERSION = '1.0.0';

    /**
     * Script handle
     */
    const SCRIPT_HANDLE = 'q1-shop-gtm-add-to-cart';

    /**
     * JavaScript object name for localized data
     */
    const JS_OBJECT_NAME = 'q1ShopGTM';

    /**
     * Plugin file path
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Constructor
     *
     * @param string $plugin_file Main plugin file path
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        add_action('wp_enqueue_scripts', array($this, 'enqueue_script'));
        
        // Force version to be included even if other plugins remove it
        // Use priority PHP_INT_MAX + 1 to run after ASENHA's filter (PHP_INT_MAX)
        add_filter('script_loader_src', array($this, 'force_script_version'), PHP_INT_MAX + 1, 2);
    }

    /**
     * Check if tracking should be enabled
     * 
     * @return bool
     */
    private function should_enable_tracking() {
        // Enable tracking if WooCommerce is active
        // We need to check on all pages because menu items with add to cart buttons
        // can appear on any page, not just WooCommerce pages
        return function_exists('WC') && class_exists('WooCommerce');
    }

    /**
     * Enqueue GTM tracking script
     */
    public function enqueue_script() {
        if (!$this->should_enable_tracking()) {
            return;
        }

        $script_path = plugin_dir_path($this->plugin_file) . 'assets/js/gtm-add-to-cart.js';
        $script_url = plugins_url('assets/js/gtm-add-to-cart.js', $this->plugin_file);
        
        // Verify file exists
        if (!file_exists($script_path)) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $script_url,
            array('jquery'),
            self::SCRIPT_VERSION,
            true
        );

        $this->localize_script();
    }

    /**
     * Localize script with product and currency data
     */
    private function localize_script() {
        $gtm_data = $this->get_gtm_data();
        
        wp_localize_script(
            self::SCRIPT_HANDLE,
            self::JS_OBJECT_NAME,
            $gtm_data
        );
    }

    /**
     * Get GTM data to localize
     * 
     * @return array
     */
    private function get_gtm_data() {
        global $product;

        $data = array(
            'currency' => $this->get_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
        );

        // Only get product data if we're on a product page and $product is a valid WC_Product object
        if (is_product() && $product && is_a($product, 'WC_Product')) {
            $data['product'] = $this->get_product_data($product);
        }

        return $data;
    }

    /**
     * Get currency code
     * 
     * @return string
     */
    private function get_currency() {
        return get_woocommerce_currency();
    }

    /**
     * Get product data for GTM
     * 
     * @param WC_Product $product Product object
     * @return array
     */
    private function get_product_data($product) {
        // Verify $product is a valid WC_Product object
        if (!is_a($product, 'WC_Product')) {
            return array();
        }

        $product_data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'type' => $product->get_type(),
        );

        $category = $this->get_product_category($product);
        if ($category) {
            $product_data['category'] = $category;
        }

        if ($product->is_type('variable')) {
            $product_data['is_variable'] = true;
        }

        return $product_data;
    }

    /**
     * Get product category name
     * 
     * @param WC_Product $product Product object
     * @return string|null
     */
    private function get_product_category($product) {
        // Verify $product is a valid WC_Product object
        if (!is_a($product, 'WC_Product')) {
            return null;
        }

        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        
        if (empty($categories) || is_wp_error($categories)) {
            return null;
        }

        return $categories[0]->name;
    }

    /**
     * Force script version to be included even if other plugins remove it
     * 
     * @param string $src Script source URL
     * @param string $handle Script handle
     * @return string Modified script source URL
     */
    public function force_script_version($src, $handle) {
        // Only apply to our script
        if ($handle === self::SCRIPT_HANDLE) {
            // Remove existing version if any
            $src = remove_query_arg('ver', $src);
            // Add our version
            $src = add_query_arg('ver', self::SCRIPT_VERSION, $src);
        }
        return $src;
    }
}

