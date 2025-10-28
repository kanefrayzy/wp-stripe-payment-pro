jQuery(document).ready(function ($, s) {
    

    const style = {
        base: {
            color: "#32325d",
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: "antialiased",
            fontSize: "16px",
            "::placeholder": {
                color: "#aab7c4"
            }
        },
        invalid: {
            color: "#fa755a",
            iconColor: "#fa755a"
        }
    };

    const stripe = Stripe(ajaxData.pb_key);
    const card_number = document.getElementById('card-number-element');
    const card_expiry = document.getElementById('card-expiry-element');
    const card_cvv = document.getElementById('card-cvv-element');
    const elements = stripe.elements();

    let cardNumber, cardExpiry, cardCvc, card;

    // Refactored Card Elements Initialization
    if (card_number && card_expiry && card_cvv) {
        cardNumber = elements.create('cardNumber', { style: style, showIcon: true });
        cardNumber.mount('#card-number-element');
        cardNumber.on('change', handleCardElementChange);

        cardExpiry = elements.create('cardExpiry', { style: style });
        cardExpiry.mount('#card-expiry-element');
        cardExpiry.on('change', handleCardElementChange);

        cardCvc = elements.create('cardCvc', { style: style });
        cardCvc.mount('#card-cvv-element');
        cardCvc.on('change', handleCardElementChange);
    } else {
        card = elements.create('card', {
            style: style,
            hidePostalCode: true,
        });
        card.mount('#card-number-element');

        card.on('change', handleCardElementChange);
    }

    // Function to handle changes in card elements and show errors
    function handleCardElementChange(event) {
        const errorElement = document.getElementById('card-errors');
        if (event.error) {
            errorElement.textContent = event.error.message;
            errorElement.style.display = 'block';
        } else {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    }


    // Payment selection handler
    $('#payment_selection').on('change', function () {
        const $this = $(this);
        const payments = $this.prop('selectedIndex') + 1;
        const priceId = $this.find(':selected').attr('data');

        $('#total_payments').val(payments);
        $('#price_id').val(priceId);

        calculatePayments();
    });

    function sendPost(data) {
        console.log(data);
        const form = document.createElement("form");
        form.method = "POST";
        
        if (data.thank_you_type === true) {
            form.action = location.origin + "/thank-you/";
        } else if (data.data && data.data.thank_you_page) {
            form.action = data.data.thank_you_page;
        } else {
            console.error('Thank you page URL is not provided.');
            return;
        }
    
        // Logging the form action to verify URL correctness
        console.log('Form action URL:', form.action);
    
        for (const key in data.data) {
            if (data.data.hasOwnProperty(key) && key !== 'payment_intent_client_secret' && key !== 'requires_action') {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = key;
                input.value = data.data[key];
                form.appendChild(input);
            }

            
        }
        console.log(form);
        document.body.appendChild(form);
        console.log("Form connected to DOM, submitting...");
    
        setTimeout(() => {
            form.submit();
            console.log(form);
        }, 1000);
    }

    $('#payment-form').submit(async function (e) {
        e.preventDefault();
        
        // Блокировка кнопки для предотвращения повторной отправки
        const $submitBtn = $('#submit');
        if ($submitBtn.prop('disabled')) {
            return false;
        }
        $submitBtn.prop('disabled', true);
        
        $('#error-message').text(''); // נקה הודעות שגיאה קודמות
        $('#loader').fadeIn();
    
        let paymentMethodData = null;
        let error = null;
    
        try {
            if (cardNumber && cardExpiry && cardCvc) {
                ({ paymentMethod: paymentMethodData, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: cardNumber,
                    billing_details: {
                        name: $('#firstName').val() + ' ' + $('#lastName').val(),
                        email: $('#email').val(),
                        phone: $('#phone').val()
                    },
                }));
            } else if (card) {
                ({ paymentMethod: paymentMethodData, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: card,
                    billing_details: {
                        name: $('#firstName').val() + ' ' + $('#lastName').val(),
                        email: $('#email').val(),
                        phone: $('#phone').val()
                    },
                }));
            }
    
            if (error) {
                throw error;
            }
    
            var paymentFormData = {
                action: 'stripe_process_payment',
                nonce1: ajaxData.nonce1,
                payment_method_id: paymentMethodData.id,
            };
    
            $('#payment-form').serializeArray().forEach(function (field) {
                paymentFormData[field.name] = field.value;
            });
    
            $.ajax({
                url: ajaxData.ajaxurl,
                type: 'POST',
                data: paymentFormData,
                success: function (response) {
                    console.log('Server Response:', response);
    
                    if (!response.success) {
                        $submitBtn.prop('disabled', false); // Разблокировка кнопки при ошибке
                        $('#loader').fadeOut();
                        $('#error-message').text(response.data.message || 'Unknown error occurred');
                        return;
                    }
    
                    if (response.data.requires_action) {
                        var clientSecret = response.data.setup_intent_client_secret || response.data.payment_intent_client_secret;
    
                        // Check if it's a SetupIntent or a PaymentIntent
                        if (response.data.setup_intent_client_secret) {
                            stripe.confirmCardSetup(clientSecret).then(function (result) {
                                if (result.error) {
                                    $submitBtn.prop('disabled', false); // Разблокировка при ошибке
                                    $('#loader').fadeOut();
                                    console.error('SetupIntent Error:', result.error.message);
                                    $('#error-message').text(result.error.message);
                                } else {
                                    console.log('SetupIntent confirmed successfully');
                                    handleSuccess(response);
                                }
                            });
                        } else if (response.data.payment_intent_client_secret) {
                            stripe.confirmCardPayment(clientSecret).then(function (result) {
                                if (result.error) {
                                    $submitBtn.prop('disabled', false); // Разблокировка при ошибке
                                    $('#loader').fadeOut();
                                    console.error('3D Secure Error:', result.error.message);
                                    $('#error-message').text(result.error.message);
                                } else if (result.paymentIntent.status === 'succeeded') {
                                    console.log('PaymentIntent confirmed successfully');
                                    
                                    // ✅ НОВЫЙ запрос на сервер после успешного 3D Secure
                                    $.ajax({
                                        url: ajaxData.ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'stripe_confirm_payment',
                                            payment_intent_id: result.paymentIntent.id,
                                            page_id: paymentFormData.page_id,
                                            nonce1: ajaxData.nonce1,
                                        },
                                        success: function(finalResponse) {
                                            if (finalResponse.success) {
                                                handleSuccess(finalResponse);
                                            } else {
                                                $submitBtn.prop('disabled', false);
                                                $('#loader').fadeOut();
                                                $('#error-message').text(finalResponse.data.message || 'Payment confirmation failed');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            $submitBtn.prop('disabled', false);
                                            $('#loader').fadeOut();
                                            console.error('Confirmation Error:', xhr.responseText);
                                            $('#error-message').text('Failed to confirm payment. Please contact support.');
                                        }
                                    });
                                } else {
                                    $submitBtn.prop('disabled', false);
                                    $('#loader').fadeOut();
                                    $('#error-message').text('Payment could not be completed');
                                }
                            });
                        }
                    } else {
                        handleSuccess(response);
                    }
                },
                error: function (xhr, status, error) {
                    $submitBtn.prop('disabled', false); // Разблокировка при ошибке AJAX
                    $('#loader').fadeOut();
                    console.error('AJAX Error:', xhr.responseText);
                    $('#error-message').text('Failed to process payment. Please try again.');
                }
            });
        } catch (err) {
            $submitBtn.prop('disabled', false); // Разблокировка при исключении
            $('#loader').fadeOut();
            $('#error-message').text(err.message || 'An unexpected error occurred');
        }
    });
    
    function handleSuccess(response) {
        $('#payment-form')[0].reset();
        if (cardNumber) {
            cardNumber.clear();
            cardExpiry.clear();
            cardCvc.clear();
        } else if (card) {
            card.clear();
        }
        sendPost(response);
        $('#loader').fadeOut();
    }

    function handleCardError(error) {
        $('#success-message').text(error);  
    }

    function validateForm() {
        let isValid = true;
    
        function setError(field, errorText) {
            // הוספת קלאס לשדה עם שגיאה
            field.classList.add('error-active');
    
            // מציאת אלמנטים לתצוגת השגיאה
            const alertBox = field.nextElementSibling; // האלמנט הבא אחרי השדה
            const alertLabel = alertBox ? alertBox.nextElementSibling : null;
    
            if (alertBox) {
                alertBox.style.display = 'block'; // הצגת תיבת השגיאה
            }
            if (alertLabel) {
                alertLabel.textContent = errorText; // עדכון הטקסט של השגיאה
                alertLabel.style.display = 'block';
            }
    
            isValid = false;
        }
    
        function clearError(field) {
            // הסרת קלאס של שגיאה מהשדה
            field.classList.remove('error-active');
    
            // הסתרת אלמנטים של הודעות שגיאה
            const alertBox = field.nextElementSibling;
            const alertLabel = alertBox ? alertBox.nextElementSibling : null;
            
            if (alertBox) {
                if(alertBox.id != 'cupon_button'){
                    alertBox.style.display = 'none';
                }
                
            }
            if (alertLabel) {
                alertLabel.style.display = 'none';
            }
        }
    
        // ניקוי שגיאות קיימות לפני הבדיקה
        document.querySelectorAll('.error-active').forEach(el => el.classList.remove('error-active'));
        document.querySelectorAll('.validation-alert').forEach(el => (el.style.display = 'none'));
        document.querySelectorAll('.validation-alert-label').forEach(el => (el.style.display = 'none'));
    
        function validateField(field) {
            // לבדוק אם השדה לא מסומן כ-required ולדלג עליו
            if (!field.required) {
                clearError(field);
                return true;
            }
    
            const fieldId = field.id;
            const fieldValue = field.value || '';
    
            const lengthValidation = {
                firstName: { min: 2, max: 20 },
                lastName: { min: 2, max: 20 },
                phone: { min: 9, max: 15 }
            };
    
            // בדיקת אורך שדות
            if (fieldId in lengthValidation) {
                const { min, max } = lengthValidation[fieldId];
                if (fieldValue.length < min || fieldValue.length > max) {
                    const errorText = `השדה חייב להכיל בין ${min} ל-${max} תווים`;
                    setError(field, errorText);
                    return false;
                }
            }
    
            // בדיקת שדות חובה
            if (field.required && fieldValue === '') {
                setError(field, 'שדה חובה');
                return false;
            }
    
            // בדיקת אימייל
            if (fieldId === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(fieldValue)) {
                    setError(field, 'כתובת האימייל אינה תקינה');
                    return false;
                }
            }
    
            // בדיקת מספר טלפון
            if (fieldId === 'phone') {
                const phoneRegex = /^(?:\+972|0)(?:5[0-9]|7[2-9])[0-9]{7}$/;
                if (!phoneRegex.test(fieldValue)) {
                    if (/^[1-9]\d{1,14}$/.test(fieldValue)) {
                        field.value = '+' + fieldValue; // הוספת "+" למספר
                    } else {
                        setError(field, 'מספר הטלפון חייב להתחיל בסימן "+" ואחריו קוד מדינה');
                        return false;
                    }
                }
            }
    
            // בדיקת שדות טקסט בלבד
            const textOnlyFields = ['firstName', 'lastName'];
            if (textOnlyFields.includes(fieldId)) {
                const textOnlyRegex = /^[A-Za-z\u0590-\u05FF\s]+$/; // אותיות בעברית, אנגלית ורווחים
                if (!textOnlyRegex.test(fieldValue)) {
                    setError(field, 'השדה יכול להכיל רק אותיות');
                    return false;
                }
            }
    
            clearError(field);
            return true;
        }
    
        // מעבר על כל השדות בטופס
        document.querySelectorAll('#payment-form .elementor-field-group input, #payment-form .elementor-field-group textarea, #payment-form .elementor-field-group select').forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
    
        if (!isValid) {
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.textContent = "יש לתקן את השדות המסומנים לפני המשך";
            }
        }
    
        return isValid;
    }    
    
    // מניעת שליחה אם הוולידציה נכשלת
    document.getElementById("submit").addEventListener("click", function (e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
});