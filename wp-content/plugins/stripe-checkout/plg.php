
<?php
/*
Plugin Name: Stripe Checkout
Description: A simple Stripe payment form for WordPress.
Version: 1.2
Author: Your Name
*/


include_once(plugin_dir_path(__FILE__) . 'html.php');

function my_custom_scripts() {
    //$version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/stripe-subscription.css');    
    //wp_enqueue_style('stripe-subscription-style',plugin_dir_url(__FILE__) . 'assets/css/stripe-subscription.css', array(),$version );
    wp_enqueue_script('stripe-subscription-script',plugin_dir_url(__FILE__) . 'index.js', array('jquery'),4.0, true); 
  }
  add_action( 'wp_enqueue_scripts', 'my_custom_scripts' );

