// Assuming you have already included Stripe.js and initialized the Stripe object
const stripe = Stripe('pk_test_51QBuMWGlRmp3igmYgmJ9p5UInB6JI0PHolfljWjkVJWYWfteZfQXcvP8C1P9hVIvWv8sdqryxZMO0cr7rC7gv1TO00IS3qLon4');

// Function to handle the server response
function handlePaymentResponse(response) {
    if (response.requires_action) {
        // Handle additional authentication
        stripe.handleCardAction(response.payment_intent_client_secret)
            .then(function(result) {
                if (result.error) {
                    // Display error message to the user
                    console.error(result.error.message);
                } else {
                    // The payment has been authenticated, now confirm the payment on the server
                    confirmPayment(result.paymentIntent.id);
                }
            });
    } else if (response.success) {
        // Payment succeeded
        console.log('Payment succeeded:', response.message);
    } else {
        // Payment failed
        console.error('Payment failed:', response.message);
    }
}

// Function to confirm the payment on the server
/*function confirmPayment(paymentIntentId) {
    // Send the paymentIntentId to your server to confirm the payment
    fetch('https://auto.gsbot.in/webhook/3c9f3864-6268-41e1-ab3f-9917f4a8523c', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ payment_intent_id: paymentIntentId }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Payment confirmed:', data.message);
        } else {
            console.error('Payment confirmation failed:', data.message);
        }
    });
}*/