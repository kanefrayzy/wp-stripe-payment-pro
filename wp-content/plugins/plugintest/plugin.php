<?php
/*
Plugin Name: Elementor Stripe Payment Form Widget
Description: Custom Stripe payment form integrated with Elementor with coupon support, one-time and subscription payments, and 3D Secure.
Version: 1.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Enqueue Stripe JS
function enqueue_stripe_js() {
    if ( ! is_admin() ) {
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
    }
}
add_action( 'wp_enqueue_scripts', 'enqueue_stripe_js' );

// Register Elementor Widget
function register_stripe_payment_widget( $widgets_manager ) {
    require_once( __DIR__ . '/stripe-payment-widget.php' );
    $widgets_manager->register( new \Elementor_Stripe_Payment_Widget() );
}
add_action( 'elementor/widgets/register', 'register_stripe_payment_widget' );

