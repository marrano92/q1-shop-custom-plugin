<?php
/**
 * Post Template Class
 *
 * Handles custom post template modifications for better SEO and UX
 *
 * @package Q1_Shop_Custom_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Q1_Shop_Post_Template
 * 
 * Handles custom post template modifications including featured image optimization
 */
class Q1_Shop_Post_Template {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Remove original featured image hook (priority 5, before title)
        // Use after_setup_theme with high priority to ensure theme hooks are registered
        add_action('after_setup_theme', array($this, 'remove_original_thumbnail_hook'), 999);
        
        // Add custom featured image hook after title (priority 15, title is at 10)
        add_action('shoptimizer_single_post', array($this, 'custom_post_thumbnail'), 15);
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Remove original thumbnail hook
     * Must run after theme hooks are registered
     */
    public function remove_original_thumbnail_hook() {
        if (function_exists('shoptimizer_post_thumbnail_no_link')) {
            remove_action('shoptimizer_single_post', 'shoptimizer_post_thumbnail_no_link', 5);
        }
    }

    /**
     * Custom post thumbnail display with SEO optimization
     */
    public function custom_post_thumbnail() {
        // Only run on single post pages
        if (!is_single() || !is_singular('post')) {
            return;
        }

        // Check if featured image should be displayed (respect theme option)
        // Use function_exists check for safety
        if (function_exists('shoptimizer_get_option')) {
            $shoptimizer_post_featured_image = shoptimizer_get_option('shoptimizer_post_featured_image');
            
            if (true !== $shoptimizer_post_featured_image) {
                return;
            }
        }

        // Check if post has thumbnail
        if (!has_post_thumbnail()) {
            return;
        }

        // Get post data for SEO attributes
        $post = get_post();
        $title = get_the_title();
        $excerpt = get_the_excerpt();
        
        // Use title for alt text, fallback to excerpt if available
        $alt_text = !empty($title) ? $title : (!empty($excerpt) ? wp_trim_words($excerpt, 10) : '');
        
        // Get featured image ID
        $thumbnail_id = get_post_thumbnail_id();
        
        // Get image metadata for dimensions
        $image_meta = wp_get_attachment_metadata($thumbnail_id);
        
        // Determine appropriate image size
        // Use large size for better quality, but not full to optimize performance
        $image_size = 'large';
        
        // Get image attributes
        $image_attributes = array(
            'class' => 'q1-shop-featured-image',
            'alt' => esc_attr($alt_text),
            'loading' => 'lazy',
            'decoding' => 'async',
        );
        
        // Add width and height if available for better performance
        if (!empty($image_meta['width']) && !empty($image_meta['height'])) {
            $image_attributes['width'] = $image_meta['width'];
            $image_attributes['height'] = $image_meta['height'];
        }
        
        // Output featured image with wrapper
        echo '<div class="q1-shop-featured-image-wrapper">';
        echo get_the_post_thumbnail($post->ID, $image_size, $image_attributes);
        echo '</div>';
    }

    /**
     * Enqueue styles for post template
     */
    public function enqueue_styles() {
        if (!is_single() || !is_singular('post')) {
            return;
        }

        wp_enqueue_style(
            'q1-shop-post-template',
            plugins_url('assets/css/post-template.css', $this->plugin_file),
            array(),
            self::VERSION
        );
    }
}

