<?php

// Elementor Widget Class
class Elementor_Stripe_Payment_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'stripe_payment_widget';
    }

    public function get_title() {
        return __( 'Stripe Payment Form', 'elementor' );
    }

    public function get_icon() {
        return 'fa fa-credit-card';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        // Here you can add any controls that you would like to add for flexibility, such as field labels, colors, etc.
    }

    protected function render() {
        ?>
        <div id="stripe-payment-form">
            <form id="payment-form">
                <div>
                    <input type="text" id="first_name" placeholder="<?php esc_attr_e( 'שם פרטי', 'elementor' ); ?>" required />
                    <input type="text" id="last_name" placeholder="<?php esc_attr_e( 'שם משפחה', 'elementor' ); ?>" required />
                </div>
                <div>
                    <input type="email" id="email" placeholder="<?php esc_attr_e( 'אימייל', 'elementor' ); ?>" required />
                    <input type="tel" id="phone" placeholder="<?php esc_attr_e( 'טלפון', 'elementor' ); ?>" required />
                </div>
                <div id="card-element"></div>
                <div>
                    <label for="installments"><?php esc_html_e( 'תשלום', 'elementor' ); ?>:</label>
                    <select id="installments">
                        <option value="one">1</option>
                        <option value="subscription">תשלומים</option>
                    </select>
                </div>
                <div>
                    <label for="coupon_code"><?php esc_html_e( 'קוד קופון?', 'elementor' ); ?></label>
                    <input type="text" id="coupon_code" placeholder="<?php esc_attr_e( 'קוד קופון', 'elementor' ); ?>" />
                </div>
                <button id="submit-button" type="submit"><?php esc_html_e( 'לתשלום', 'elementor' ); ?></button>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var stripe = Stripe('pk_test_51QBuMWGlRmp3igmYgmJ9p5UInB6JI0PHolfljWjkVJWYWfteZfQXcvP8C1P9hVIvWv8sdqryxZMO0cr7rC7gv1TO00IS3qLon4');
                var elements = stripe.elements();
                var style = {
                    base: {
                        color: '#32325d',
                        lineHeight: '24px',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        fontSmoothing: 'antialiased',
                        fontSize: '16px',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                };

                var card = elements.create('card', { style: style });
                card.mount('#card-element');

                var form = document.getElementById('payment-form');
                form.addEventListener('submit', function(ev) {
                    ev.preventDefault();

                    // Handle coupon application here
                    var couponCode = document.getElementById('coupon_code').value;

                    stripe.createPaymentMethod({
                        type: 'card',
                        card: card,
                        billing_details: {
                            name: document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value,
                            email: document.getElementById('email').value,
                            phone: document.getElementById('phone').value,
                        },
                    }).then(function(result) {
                        if (result.error) {
                            // Show error to your customer (e.g., insufficient funds)
                            console.error(result.error.message);
                        } else {
                            // Get installment option
                            var installmentOption = document.getElementById('installments').value;
                            if (installmentOption === 'subscription') {
                                createSubscription(result.paymentMethod.id, couponCode);
                            } else {
                                createPayment(result.paymentMethod.id, couponCode);
                            }
                        }
                    });
                });

                function createPayment(paymentMethodId, couponCode) {
                    fetch('https://wordpress-517778-5035659.cloudwaysapps.com/wp-content/plugins/plugintest/create-payment-intent.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            payment_method: paymentMethodId,
                            coupon: couponCode,
                            automatic_payment_methods: {
                                enabled: true,
                                allow_redirects:never
                            }
                        })
                    }).then(function(response) {
                        return response.json();
                    }).then(function(paymentIntent) {
                        return stripe.confirmCardPayment(paymentIntent.client_secret);
                    }).then(function(result) {
                        if (result.error) {
                            // Show error to your customer
                            console.error(result.error.message);
                        } else {
                            if (result.paymentIntent.status === 'succeeded') {
                                console.log('Payment successful!');
                            }
                        }
                    });
                }

                function createSubscription(paymentMethodId, couponCode) {
                    fetch('https://wordpress-517778-5035659.cloudwaysapps.com/wp-content/plugins/plugintest/create-subscription.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            payment_method: paymentMethodId,
                            coupon: couponCode,
                            automatic_payment_methods: {
                                enabled: true,
                                allow_redirects:never
                            }
                        })
                    }).then(function(response) {
                        return response.json();
                    }).then(function(subscription) {
                        console.log('Subscription created successfully!');
                    });
                }
            });
        </script>
        <?php
    }
}