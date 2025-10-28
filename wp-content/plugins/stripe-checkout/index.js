// Initialize Stripe.js
const stripe = Stripe('pk_test_51QBuMWGlRmp3igmYgmJ9p5UInB6JI0PHolfljWjkVJWYWfteZfQXcvP8C1P9hVIvWv8sdqryxZMO0cr7rC7gv1TO00IS3qLon4');

initialize();

// Fetch Checkout Session and retrieve the client secret
async function initialize() {
  const fetchClientSecret = async () => {
    const response = await fetch("https://wordpress-517778-5035659.cloudwaysapps.com/wp-content/plugins/stripe-checkout/create-checkout-session.php", {
      method: "POST",
    });
    const { clientSecret } = await response.json();
    return clientSecret;
  };

  // Initialize Checkout
  const checkout = await stripe.initEmbeddedCheckout({
    fetchClientSecret,
  });

  // Mount Checkout
  checkout.mount('#checkout');
}


jQuery('label[for="email"] span').text('אמייל');