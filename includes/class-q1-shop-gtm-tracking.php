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

        $script_url = plugins_url('assets/js/gtm-add-to-cart.js', $this->plugin_file);
        
        // Debug: log script URL (only in development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Q1 Shop GTM: Enqueuing script: ' . $script_url);
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

        if (is_product() && $product) {
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
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        
        if (empty($categories) || is_wp_error($categories)) {
            return null;
        }

        return $categories[0]->name;
    }
}

