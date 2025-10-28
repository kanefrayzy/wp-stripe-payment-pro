<?php
/*
Plugin Name: Simple Stripe Payment Form
Description: A simple Stripe payment form for WordPress.
Version: 2.1
Author: Your Name
*/
if (!defined('ABSPATH')) die();

/* PLUGIN UPDATER */
// Include the updater class
require_once plugin_dir_path(__FILE__) . 'self-updater/update.php';
define('PLUGIN_PATH',__FILE__);
// Initialize the updater with the main plugin file path
if (class_exists('SelfUpdatingPlugin')) {
    $gs_agent_updater = new SelfUpdatingPlugin(__FILE__);
}


include_once plugin_dir_path(__FILE__) . 'include/settings.php';
include_once(plugin_dir_path(__FILE__) . 'include/cpt_v2.php');
include_once(plugin_dir_path(__FILE__) . 'include/shortcodes.php');
include_once plugin_dir_path(__FILE__) . 'include/coupon_code.php';
include_once(plugin_dir_path(__FILE__) . 'include/elementor-widgets.php');

function my_custom_scripts() {
    $version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/stripe-subscription.css');    
    wp_enqueue_style('stripe-subscription-style',plugin_dir_url(__FILE__) . 'assets/css/stripe-subscription.css', array(),$version );
	wp_enqueue_script('stripe-subscription-script',plugin_dir_url(__FILE__) . 'assets/js/stripe-subscription.js', array('jquery'),4.0, true); 
    
}
add_action( 'wp_enqueue_scripts', 'my_custom_scripts' );


?>