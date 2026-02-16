<?php
/**
 * Performance and security hardening.
 *
 * - Disables all RSS/Atom/RDF feeds (not needed for e-commerce).
 * - Removes feed links from <head>.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Q1_Shop_Performance {

    public function __construct() {
        // Disable every feed type with a 301 redirect to home.
        add_action( 'do_feed',      array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rdf',  array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rss',  array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rss2', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_atom', array( $this, 'disable_feed' ), 1 );

        // Remove feed links from wp_head.
        remove_action( 'wp_head', 'feed_links',       2 );
        remove_action( 'wp_head', 'feed_links_extra',  3 );
    }

    /**
     * Redirect any feed request to the homepage.
     */
    public function disable_feed() {
        wp_redirect( home_url(), 301 );
        exit;
    }
}