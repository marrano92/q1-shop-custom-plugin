<?php
/**
 * Plugin Name: Q1 Shop Stripe Alert
 * Plugin URI: https://github.com/yourusername/q1-shop-stripe-alert
 * Description: Adds a Stripe payment alert box above the credit card information in WooCommerce checkout
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Andrea Marrano
 * Author URI: mailto:andrea.marrano92@gmail.com
 * Text Domain: q1-shop-stripe-alert
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// [ai-generated-code]
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Q1_Shop_Stripe_Alert {
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('woocommerce_before_checkout_form', array($this, 'add_stripe_alert'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'q1-shop-stripe-alert',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    public function add_stripe_alert() {
        ?>
        <div class="stripe-payment-alert">
            <div class="alert-content">
                <h3><?php _e('Payment Information', 'q1-shop-stripe-alert'); ?></h3>
                <p><?php _e('All payments are securely processed through Stripe. Your payment information is encrypted and never stored on our servers.', 'q1-shop-stripe-alert'); ?></p>
            </div>
        </div>
        <?php
    }

    public function enqueue_styles() {
        if (is_checkout()) {
            wp_enqueue_style(
                'q1-shop-stripe-alert',
                plugins_url('assets/css/style.css', __FILE__),
                array(),
                '1.0.0'
            );
        }
    }
}

// Initialize the plugin
new Q1_Shop_Stripe_Alert();

add_filter('woocommerce_loop_add_to_cart_link', function($html, $product){
    if (! $product || ! $product->is_purchasable()) return $html;
    $text = esc_html($product->add_to_cart_text());
    $attrs = [
            'href' => '#',
            'data-quantity' => '1',
            'data-product_id' => $product->get_id(),
            'class' => 'button product_type_simple add_to_cart_button ajax_add_to_cart',
            'rel' => 'nofollow'
    ];
    $attr = '';
    foreach ($attrs as $k=>$v) $attr .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
    return sprintf('<a%s>%s</a>', $attr, $text);
}, 99, 2);
