<script>
    const stripe = Stripe("{{ $publishableKey }}");

    const {error, paymentIntent} = await stripe.confirmCardPayment(
        clientSecret, // from backend
        {
            payment_method: {
                card: elements.getElement(CardElement), // Stripe.js card element
            },
        }
    );
    if (error) {
        console.error(error.message);
    } else if (paymentIntent.status === "succeeded") {
        console.log("✅ Payment succeeded!");
    } else if (paymentIntent.status === "requires_action") {
        console.log("⚠️ Needs 3D Secure auth");
    }
</script>
