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
    const SCRIPT_VERSION = '1.0.9';

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
        // Use script_loader_tag to modify the final HTML tag after all filters
        add_filter('script_loader_tag', array($this, 'force_script_version_in_tag'), PHP_INT_MAX, 3);
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

        // Get cart data if we're on the cart page
        if (is_cart() && function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            $data['cart'] = $this->get_cart_data();
        }

        // Get checkout data if we're on the checkout page
        if (is_checkout() && function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            $data['checkout'] = $this->get_checkout_data();
        }

        // Get order data if we're on the order received page
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            $order_id = absint(get_query_var('order-received'));
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order && is_a($order, 'WC_Order')) {
                    $data['order'] = $this->get_order_data($order);
                }
            }
        } elseif (is_checkout() && isset($_GET['order-received'])) {
            // Fallback: check if we're on checkout page with order-received parameter
            $order_id = absint($_GET['order-received']);
            if ($order_id > 0) {
                $order_key = isset($_GET['key']) ? wc_clean($_GET['key']) : '';
                $order = wc_get_order($order_id);
                if ($order && is_a($order, 'WC_Order') && ($order_key === '' || hash_equals($order->get_order_key(), $order_key))) {
                    $data['order'] = $this->get_order_data($order);
                }
            }
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
     * Get cart data for GTM
     * 
     * @return array
     */
    private function get_cart_data() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return array();
        }

        $cart_data = array(
            'items' => array(),
            'total' => WC()->cart->get_total(''),
            'total_float' => (float) WC()->cart->get_total('')
        );

        $index = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            if (!is_a($product, 'WC_Product')) {
                continue;
            }

            $item_data = array(
                'item_id' => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
                'item_name' => $product->get_name(),
                'price' => (float) wc_get_price_to_display($product),
                'quantity' => (int) $cart_item['quantity'],
                'index' => $index
            );

            // Get product category
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (!empty($categories) && !is_wp_error($categories)) {
                $item_data['item_category'] = $categories[0]->name;
                
                // Add additional categories
                if (count($categories) > 1) {
                    foreach ($categories as $cat_index => $category) {
                        if ($cat_index > 0) {
                            $item_data['item_category' . ($cat_index + 1)] = $category->name;
                        }
                    }
                }
            }

            // Handle variations
            if (!empty($cart_item['variation_id'])) {
                $variation = wc_get_product($cart_item['variation_id']);
                if ($variation && is_a($variation, 'WC_Product_Variation')) {
                    $variation_attributes = $variation->get_variation_attributes();
                    if (!empty($variation_attributes)) {
                        $item_data['item_variant'] = implode(' / ', array_values($variation_attributes));
                    }
                }
            }

            $cart_data['items'][] = $item_data;
            $index++;
        }

        return $cart_data;
    }

    /**
     * Get checkout data for GTM
     * 
     * @return array
     */
    private function get_checkout_data() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return array();
        }

        $checkout_data = array(
            'items' => array(),
            'total' => WC()->cart->get_total(''),
            'total_float' => (float) WC()->cart->get_total(''),
            'coupon' => ''
        );

        // Get applied coupons
        $applied_coupons = WC()->cart->get_applied_coupons();
        if (!empty($applied_coupons)) {
            $checkout_data['coupon'] = implode(', ', $applied_coupons);
            $checkout_data['coupons'] = $applied_coupons;
        }

        $index = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            if (!is_a($product, 'WC_Product')) {
                continue;
            }

            $item_data = array(
                'item_id' => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
                'item_name' => $product->get_name(),
                'price' => (float) wc_get_price_to_display($product),
                'quantity' => (int) $cart_item['quantity'],
                'index' => $index
            );

            // Add coupon to item if available
            if (!empty($applied_coupons)) {
                $item_data['coupon'] = $checkout_data['coupon'];
            }

            // Get product category
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (!empty($categories) && !is_wp_error($categories)) {
                $item_data['item_category'] = $categories[0]->name;
                
                // Add additional categories
                if (count($categories) > 1) {
                    foreach ($categories as $cat_index => $category) {
                        if ($cat_index > 0) {
                            $item_data['item_category' . ($cat_index + 1)] = $category->name;
                        }
                    }
                }
            }

            // Handle variations
            if (!empty($cart_item['variation_id'])) {
                $variation = wc_get_product($cart_item['variation_id']);
                if ($variation && is_a($variation, 'WC_Product_Variation')) {
                    $variation_attributes = $variation->get_variation_attributes();
                    if (!empty($variation_attributes)) {
                        $item_data['item_variant'] = implode(' / ', array_values($variation_attributes));
                    }
                }
            }

            // Calculate discount for this item if coupon is applied
            if (!empty($applied_coupons)) {
                $item_subtotal = (float) wc_get_price_to_display($product) * (int) $cart_item['quantity'];
                $item_total = (float) $cart_item['line_total'];
                $item_discount = $item_subtotal - $item_total;
                if ($item_discount > 0) {
                    $item_data['discount'] = round($item_discount, 2);
                }
            }

            $checkout_data['items'][] = $item_data;
            $index++;
        }

        return $checkout_data;
    }

    /**
     * Get order data for GTM purchase event
     * 
     * @param WC_Order $order Order object
     * @return array
     */
    private function get_order_data($order) {
        if (!is_a($order, 'WC_Order')) {
            return array();
        }

        $order_data = array(
            'transaction_id' => (string) $order->get_order_number(),
            'items' => array(),
            'value' => (float) $order->get_total(''),
            'tax' => (float) $order->get_total_tax(''),
            'shipping' => (float) $order->get_shipping_total(''),
            'coupon' => '',
            'customer_type' => ''
        );

        // Get applied coupons
        $applied_coupons = $order->get_coupon_codes();
        if (!empty($applied_coupons)) {
            $order_data['coupon'] = implode(', ', $applied_coupons);
            $order_data['coupons'] = $applied_coupons;
        }

        // Determine customer type (new or returning)
        $customer_id = $order->get_customer_id();
        if ($customer_id > 0) {
            $order_count = wc_get_customer_order_count($customer_id);
            $order_data['customer_type'] = ($order_count <= 1) ? 'new' : 'returning';
        } else {
            $order_data['customer_type'] = 'new';
        }

        $index = 0;
        foreach ($order->get_items() as $item_id => $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $product = $item->get_product();
            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            $item_data = array(
                'item_id' => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
                'item_name' => $item->get_name(),
                'price' => (float) $order->get_item_subtotal($item, true, true),
                'quantity' => (int) $item->get_quantity(),
                'index' => $index
            );

            // Add coupon to item if available
            if (!empty($applied_coupons)) {
                $item_data['coupon'] = $order_data['coupon'];
            }

            // Calculate discount for this item
            $item_subtotal = (float) $order->get_item_subtotal($item, false, true);
            $item_total = (float) $order->get_item_total($item, false, true);
            $item_discount = $item_subtotal - $item_total;
            if ($item_discount > 0) {
                $item_data['discount'] = round($item_discount, 2);
            }

            // Get product category
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $categories = wp_get_post_terms($variation_id > 0 ? $variation_id : $product_id, 'product_cat');
            if (!empty($categories) && !is_wp_error($categories)) {
                $item_data['item_category'] = $categories[0]->name;
                
                // Add additional categories
                if (count($categories) > 1) {
                    foreach ($categories as $cat_index => $category) {
                        if ($cat_index > 0) {
                            $item_data['item_category' . ($cat_index + 1)] = $category->name;
                        }
                    }
                }
            }

            // Handle variations
            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if ($variation && is_a($variation, 'WC_Product_Variation')) {
                    $variation_attributes = $variation->get_variation_attributes();
                    if (!empty($variation_attributes)) {
                        $item_data['item_variant'] = implode(' / ', array_values($variation_attributes));
                    }
                }
            }

            $order_data['items'][] = $item_data;
            $index++;
        }

        return $order_data;
    }

    /**
     * Force script version to be included even if other plugins remove it
     * This modifies the final HTML tag after all script_loader_src filters
     * 
     * @param string $tag The complete script tag HTML
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return string Modified script tag HTML
     */
    public function force_script_version_in_tag($tag, $handle, $src) {
        // Only apply to our script
        if ($handle === self::SCRIPT_HANDLE) {
            // Extract current src from tag using regex
            if (preg_match('/src=["\']([^"\']+)["\']/', $tag, $matches)) {
                $current_src = $matches[1];
                
                // Parse URL to handle multiple query parameters correctly
                $url_parts = parse_url($current_src);
                $query_params = array();
                
                if (isset($url_parts['query'])) {
                    parse_str($url_parts['query'], $query_params);
                }
                
                // Remove all existing 'ver' parameters (handle both ?ver= and &ver=)
                unset($query_params['ver']);
                
                // Rebuild query string
                $new_query = http_build_query($query_params);
                
                // Rebuild URL
                $new_src = $url_parts['scheme'] . '://' . $url_parts['host'];
                if (isset($url_parts['port'])) {
                    $new_src .= ':' . $url_parts['port'];
                }
                if (isset($url_parts['path'])) {
                    $new_src .= $url_parts['path'];
                }
                
                // Add our version
                if ($new_query) {
                    $new_src .= '?' . $new_query . '&ver=' . self::SCRIPT_VERSION;
                } else {
                    $new_src .= '?ver=' . self::SCRIPT_VERSION;
                }
                
                if (isset($url_parts['fragment'])) {
                    $new_src .= '#' . $url_parts['fragment'];
                }
                
                // Replace src in tag
                $tag = str_replace($current_src, $new_src, $tag);
            }
        }
        return $tag;
    }
}

