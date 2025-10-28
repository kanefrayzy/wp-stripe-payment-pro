<?php

// פונקציה מותאמת להצפנה לפני שמירה בבסיס הנתונים
function custom_tools_sanitize_key($input) {
    // אם השדה לא ריק, הצפן את המפתח
    if (!empty($input)) {
        // דאג להצפנה רק אם הערך לא כבר base64
        if (!preg_match('/^[A-Za-z0-9+\/=]*$/', $input)) {
            return base64_encode($input); // הצפנת המפתח
        }
    }
    return $input; // אם כבר מוצפן, נשאיר אותו ככה
}

// סינון המפתח לפני השמירה בבסיס הנתונים
add_filter('pre_update_option_product_key_field_prod', 'custom_tools_sanitize_key');
add_filter('pre_update_option_product_key_field_dev', 'custom_tools_sanitize_key');
add_filter('pre_update_option_public_key_field_prod', 'custom_tools_sanitize_key');
add_filter('pre_update_option_public_key_field_dev', 'custom_tools_sanitize_key');

// פונקציה לפענוח המפתח לפני הצגתו
function custom_tools_unsanitize_key($value) {
    // אם הערך לא ריק ופענח אותו
    if (!empty($value)) {
        return base64_decode($value); // פענוח המפתח
    }
    return $value;
}

// פענוח המפתח כאשר הוא יוצג בשדות
add_filter('option_product_key_field_prod', 'custom_tools_unsanitize_key');
add_filter('option_product_key_field_dev', 'custom_tools_unsanitize_key');
add_filter('option_public_key_field_prod', 'custom_tools_unsanitize_key');
add_filter('option_public_key_field_dev', 'custom_tools_unsanitize_key');