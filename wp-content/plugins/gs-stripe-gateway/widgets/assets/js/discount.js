// Refactored Payment Calculation Function
function calculatePayments() {
    const paymentSelection = document.getElementById('payment_selection');
    const priceInput = document.getElementById('price');
    const discountMemInput = document.getElementById('discountmem');
    const discountTypeInput = document.getElementById('discounttype');
    const discountValueInput = document.getElementById('discounvalue');
    const paymentCalculation = document.getElementById('payment_calculation');
    const totalPriceDisplay = document.querySelector('#total_price bdo');

    const payments = paymentSelection.selectedIndex + 1;
    const totalPrice = parseFloat(priceInput.value) / 100;
    const discount = parseFloat(discountMemInput.value);
    const discountType = discountTypeInput.value;
    const discountValue = parseFloat(discountValueInput.value);

    // Reset payment calculation display
    paymentCalculation.classList.remove('active');
    paymentCalculation.style.display = 'none';

    let discountedPrice = totalPrice;

    // With discount calculation
    if (discount >= 1) {
        if (discountType === 'percent_off') {
            const discountPercentage = discountValue / 100;
            discountedPrice = totalPrice * (1 - discountPercentage);
        } else if (discountType === 'original_amount') {
            const discountAmount = discountValue / 100;
            discountedPrice = totalPrice - discountAmount;
        }
    }

    const paymentAmount = discountedPrice / payments;

    paymentCalculation.classList.add('active');
    paymentCalculation.style.display = 'block';
    paymentCalculation.querySelector('#sum').textContent = paymentAmount.toFixed(2);
    paymentCalculation.querySelector('#payments').textContent = payments;

    // Update total price for single payment or multiple payments
    totalPriceDisplay.textContent = discountedPrice.toFixed(2);

    // Single payment scenario (specific case handling)
    if (payments === 1) {
        paymentCalculation.style.display = 'none'; // Hide payment breakdown
    }

    // Attach event listener to reset coupon button
    document.querySelectorAll('.active-coupon').forEach(button => {
        button.addEventListener('click', resetCoupon);
    });
}

// Refactored Reset Coupon Function
function resetCoupon() {
    const priceInput = document.getElementById('price');
    const discountMemInput = document.getElementById('discountmem');
    const discountValueInput = document.getElementById('discounvalue');
    const discountTypeInput = document.getElementById('discounttype');
    const discountElement = document.getElementById('discount');
    const successMessage = document.getElementById('success-message');
    const couponButton = document.getElementById('cupon_button');
    const couponInput = document.getElementById('coupon');
    const totalPriceDisplay = document.querySelector('#total_price bdo');

    const totalPrice = parseFloat(priceInput.value) / 100;

    // Reset form inputs
    discountMemInput.value = '';
    discountValueInput.value = '';
    discountTypeInput.value = '';

    // Remove classes and reset UI elements
    discountElement.classList.remove('active');
    document.querySelectorAll('.overline').forEach(element => element.classList.remove('overline'));
    successMessage.textContent = '';

    // Reset coupon button
    couponInput.disabled = false;
    couponInput.value = '';
    couponButton.textContent = 'בדיקת קופון';
    couponButton.classList.remove('active-coupon');
    couponButton.setAttribute('data-status', 'true');

    // Reset total price display
    totalPriceDisplay.textContent = totalPrice.toFixed(2);

    // Recalculate payments
    calculatePayments();
}

// Coupon Handling
document.getElementById('cupon_button').addEventListener('click', function (e) {
    e.preventDefault();

    const alertElement = document.getElementById('success-message');
    alertElement.textContent = '';

    const couponCode = document.getElementById('coupon').value;
    const fieldStatus = this.getAttribute('data-status');
    const price = document.getElementById('price_id').value;
    const pageId = document.getElementById('page_id').value;

    if (couponCode && fieldStatus === 'true') {
        document.getElementById('loader').style.display = 'block';

        fetch(ajaxData.ajaxurl, {
            method: 'POST',
            dataType: 'json',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'check_code_coupon',
                coupon_code: couponCode,
                price: price,
                page_id: pageId,
                nonce2: ajaxData.nonce2
            })
        })
        .then(response => response.json())
        .then(response => {
            if (response.success && response.data.status === 'success') {
                handleCouponSuccess(response);
            } else {
                handleCouponError(response);
            }
        })
        .catch(error => {
            handleAjaxError(error);
        })
        .finally(() => {
            document.getElementById('loader').style.display = 'none';
        });
    }
});

function handleCouponSuccess(response) {
    const successMessage = document.getElementById('success-message');
    const couponCode = response.data.coupon_code;

    const discountDetails = response.data.discount_details;
    let discountText = '';
    if (discountDetails.percent_off && discountDetails.percent_off > 0) {
        discountText = `${discountDetails.percent_off}%`;
    } else if (discountDetails.amount_off && discountDetails.amount_off > 0) {
        discountText = `${(discountDetails.amount_off / 100).toFixed(2)}₪`;
    }

    successMessage.textContent = `קוד קופון הוחל! גובה ההנחה: ${discountText}`;
    successMessage.style.display = 'block';

    const couponButton = document.getElementById('cupon_button');
    couponButton.classList.add('active-coupon');
    document.getElementById('coupon').value = couponCode;
    couponButton.setAttribute('data-status', 'false');
    couponButton.textContent = 'ביטול קופון';

    const discountType = discountDetails.percent_off > 0 ? 'percent_off' : 'original_amount';
    const discountValue = discountType === 'percent_off' 
        ? discountDetails.percent_off 
        : discountDetails.amount_off;

    document.getElementById('discountmem').value  = response.data.discounted_amount;
    document.getElementById('discounttype').value = discountType;
    document.getElementById('discounvalue').value = discountValue;

    calculatePayments();
}

function handleCouponError(response) {
    const alertElement = document.getElementById('success-message');
    alertElement.textContent = `שגיאה: ${response.data.message}`;
    alertElement.style.display = 'block';
}

function handleAjaxError(error) {
    console.error('AJAX Error:', error);
    const errorMessage = document.getElementById('error-message');
    errorMessage.textContent = 'An error occurred. Please try again.';
    errorMessage.style.display = 'block';
}