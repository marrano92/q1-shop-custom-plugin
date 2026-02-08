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

// SEO Assistant constants
define( 'Q1_SHOP_SEO_VERSION', '1.0.0' );
define( 'Q1_SHOP_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'Q1_SHOP_SEO_URL', plugin_dir_url( __FILE__ ) );

// Load GTM Tracking class
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-gtm-tracking.php';

// Load Post Template class
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-post-template.php';

// Load SEO Settings class (before SEO Assistant — dependency order)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-seo-settings.php';

// Load SEO Logger (static service, before n8n client)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-seo-logger.php';

// Load N8n Client class (after settings + logger, before assistant)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-n8n-client.php';

// Load WooCommerce Context extractor (static service, no hooks)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-wc-context.php';

// Load Keyword Research service (after n8n client + wc context)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-keyword-research.php';

// Load Content Collector (static service, no hooks)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-content-collector.php';

// Load SEO Audit service (after n8n client + content collector)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-seo-audit.php';

// Load Content Ideas service (after n8n client + wc context)
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-content-ideas.php';

// Load SEO Assistant class
require_once plugin_dir_path(__FILE__) . 'includes/class-q1-shop-seo-assistant.php';

/**
 * Class Q1_Shop_Stripe_Alert
 * 
 * Handles Stripe payment alert display on checkout page
 */
class Q1_Shop_Stripe_Alert {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('woocommerce_before_checkout_form', array($this, 'add_stripe_alert'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'q1-shop-stripe-alert',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Add Stripe alert box on checkout page
     */
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

    /**
     * Enqueue styles for checkout page
     */
    public function enqueue_styles() {
        if (is_checkout()) {
            wp_enqueue_style(
                'q1-shop-stripe-alert',
                plugins_url('assets/css/style.css', __FILE__),
                array(),
                self::VERSION
            );
        }
    }
}

// Initialize plugins
new Q1_Shop_Stripe_Alert();
new Q1_Shop_GTM_Tracking(__FILE__);
new Q1_Shop_Post_Template(__FILE__);
new Q1_Shop_SEO_Settings();
new Q1_Shop_Keyword_Research();
new Q1_Shop_SEO_Audit();
new Q1_Shop_Content_Ideas();
new Q1_Shop_SEO_Assistant(__FILE__);

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

//Custom Snippets
/**
 * Change "Out Of Stock" text.
 *
 */
function your_prefix_change_out_stock_string() {
	return __( 'Esaurito', 'your-text-domain' );
}
add_filter( 'shoptimizer_shop_out_of_stock_string', 'your_prefix_change_out_stock_string' );

add_filter( 'woocommerce_cart_item_price', 'cg_cart_table_price_display', 30, 3 );
  
function cg_cart_table_price_display( $price, $values, $cart_item_key ) {
   $slashed_price = $values['data']->get_price_html();
   $is_on_sale = $values['data']->is_on_sale();
   if ( $is_on_sale ) {
      $price = $slashed_price;
   }
   return $price;
}

if ( ! function_exists( 'shoptimizer_mini_cart_total_discounts' ) ) {
    function shoptimizer_mini_cart_total_discounts()  {
        global $woocommerce;
        $discount_total = 0;
 
        foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
 
            $_product = $values['data'];
 
            if ($_product->is_on_sale()) {
                $regular_price = $_product->get_regular_price();
                $sale_price = $_product->get_sale_price();
 
                if (empty($regular_price)){ //then this is a variable product
                    $available_variations = $_product->get_available_variations();
                    $variation_id=$available_variations[0]['variation_id'];
                    $variation= new WC_Product_Variation( $variation_id );
                    $regular_price = $variation ->regular_price;
                    $sale_price = $variation ->sale_price;
                }
 
                $discount = ceil(($regular_price - $sale_price) * $values['quantity'] );
                $discount_total += $discount;
            }
 
        }
        if ($discount_total > 0) { ?>
            <p class="woocommerce-mini-cart__total total discounts-total">
                <strong><?php echo esc_html__( 'You save', 'shoptimizer' ); ?></strong>
                <span class="woocommerce-Price-amount amount">-<?php echo wc_price($discount_total + $woocommerce->cart->discount_cart); ?></span>
            </p>

			<style>
				.widget_shopping_cart p.total.discounts-total {
					color: green;
					font-size: 13px;
					margin-bottom: -1.2em;
					order: 1;
					border-top: 1px solid #e2e2e2;

				}
				.widget_shopping_cart p.total.discounts-total strong {
					font-weight: normal;
				}
				.shoptimizer-mini-cart-wrap .widget_shopping_cart .discounts-total .amount {
					margin: 0;
					font-weight: normal;
					color: green;
				}
				.shoptimizer-mini-cart-wrap .widget_shopping_cart .discounts-total .amount bdi {
					color: green;
				}
				.widget_shopping_cart p.total {
					order: 2;
					border: none;
				}
				.widget_shopping_cart p.buttons {
					order: 3;
				}
				.shoptimizer-mini-cart-wrap .cart-drawer-below {
					order: 4;
				}
			</style>
        <?php }
    }
}

add_action( 'woocommerce_widget_shopping_cart_before_buttons',  'shoptimizer_mini_cart_total_discounts', 10);