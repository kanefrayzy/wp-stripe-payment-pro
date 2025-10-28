<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

include_once(plugin_dir_path(__DIR__) . 'vendor/autoload.php');
//require_once plugin_dir_path(__DIR__) . 'include/stripe_proccess_v3.php';
require_once plugin_dir_path(__DIR__) . 'include/payment-proccess/stripe_proccess_v4.php';

function register_custom_widgets($widgets_manager)
{
    
    require_once plugin_dir_path(__DIR__) . 'widgets/class-payment-form-widget.php';
    $widgets_manager->register(new \Custom_3d_payment_form_Widget());

    require_once plugin_dir_path(__DIR__) . 'widgets/class-payment-price-widget.php';
    $widgets_manager->register(new \Custom_payment_price_Widget());

}
add_action('elementor/widgets/register', 'register_custom_widgets');


function enqueue_stripe_sdk_for_elementor_widget() {
    
    // בדוק אם Elementor נטען
    if (did_action('elementor/loaded')) {
        // טען את Stripe SDK
        wp_enqueue_script(
            'stripe-widget-sdk',
            'https://js.stripe.com/v3/',
            array('jquery'),
            null,
            true // טוען בתחתית הדף
        );
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('3ds-script'     ,plugin_dir_url(__DIR__)  . 'widgets/assets/js/3ds.js', array('jquery'), '1.0.1', true);
    wp_enqueue_script('discount-script',plugin_dir_url(__DIR__)  . 'widgets/assets/js/discount.js', array('jquery'),'1.0.1', true);     
    wp_enqueue_script('stripe-custom'  ,plugin_dir_url(__DIR__)  . 'widgets/assets/js/stripe-form.js', array('jquery'), '1.0.1', true);
    

    global $post;
    $testmode = get_post_meta($post->ID, 'env_mode', true);
    $pbkey = $testmode ? get_option('public_key_field_dev') : get_option('public_key_field_prod');    
    wp_localize_script('stripe-custom', 'ajaxData', array(
        'ajaxurl' => admin_url('admin-ajax.php'), // כתובת AJAX
        'nonce1'   => wp_create_nonce('stripe_payment_nonce'), // יצירת nonce
        'nonce2'   => wp_create_nonce('stripe_coupon_nonce'), // יצירת nonce
        'pb_key'  => $pbkey, 
    ));
    

    }
    
}

add_action('elementor/frontend/after_enqueue_scripts', 'enqueue_stripe_sdk_for_elementor_widget');
add_action('elementor/editor/after_enqueue_scripts', 'enqueue_stripe_sdk_for_elementor_widget');