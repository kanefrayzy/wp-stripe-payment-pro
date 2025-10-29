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
add_filter('pre_update_option_product_key_field_prod', 'gs_stripe_preserve_secret_key', 5);
add_filter('pre_update_option_product_key_field_dev', 'gs_stripe_preserve_secret_key', 5);
add_filter('pre_update_option_product_key_field_prod', 'custom_tools_sanitize_key', 10);
add_filter('pre_update_option_product_key_field_dev', 'custom_tools_sanitize_key', 10);
add_filter('pre_update_option_public_key_field_prod', 'custom_tools_sanitize_key');
add_filter('pre_update_option_public_key_field_dev', 'custom_tools_sanitize_key');

function gs_stripe_preserve_secret_key($value, $old_value = null, $option = null) {
    if ($option === 'product_key_field_prod' && empty($value) && empty($_POST['change_prod_secret'])) {
        return get_option('product_key_field_prod');
    }
    if ($option === 'product_key_field_dev' && empty($value) && empty($_POST['change_dev_secret'])) {
        return get_option('product_key_field_dev');
    }
    return $value;
}

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

add_action('admin_menu', 'gs_stripe_add_settings_page');
add_action('admin_init', 'gs_stripe_register_settings');

function gs_stripe_add_settings_page() {
    add_options_page(
        'Stripe Gateway Settings',
        'Stripe Gateway',
        'manage_options',
        'gs-stripe-settings',
        'gs_stripe_settings_page'
    );
}

function gs_stripe_register_settings() {
    register_setting('gs_stripe_settings', 'stripe_test_mode');
    register_setting('gs_stripe_settings', 'product_key_field_prod');
    register_setting('gs_stripe_settings', 'public_key_field_prod');
    register_setting('gs_stripe_settings', 'product_key_field_dev');
    register_setting('gs_stripe_settings', 'public_key_field_dev');
}

function gs_stripe_settings_page() {
    $prod_secret = get_option('product_key_field_prod');
    $prod_public = get_option('public_key_field_prod');
    $dev_secret = get_option('product_key_field_dev');
    $dev_public = get_option('public_key_field_dev');
    ?>
    <div class="wrap">
        <h1>Stripe Gateway Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('gs_stripe_settings'); ?>
            <?php do_settings_sections('gs_stripe_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stripe_test_mode">Test Mode</label>
                    </th>
                    <td>
                        <input type="checkbox" name="stripe_test_mode" id="stripe_test_mode" value="1" <?php checked(1, get_option('stripe_test_mode'), true); ?> />
                        <p class="description">Enable test mode to use test API keys</p>
                    </td>
                </tr>
                
                <tr>
                    <th colspan="2"><h2>Production Keys</h2></th>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="product_key_field_prod">Secret Key (Live)</label>
                    </th>
                    <td>
                        <?php if (!empty($prod_secret)): ?>
                            <input type="text" readonly class="regular-text" value="<?php echo str_repeat('•', 40) . substr($prod_secret, -4); ?>" style="background: #f0f0f0;" />
                            <br><label><input type="checkbox" name="change_prod_secret" id="change_prod_secret" value="1" /> Change key</label>
                            <input type="password" name="product_key_field_prod" id="product_key_field_prod_input" 
                                   value="" class="regular-text" placeholder="sk_live_..." style="display:none;margin-top:5px;" />
                        <?php else: ?>
                            <input type="password" name="product_key_field_prod" id="product_key_field_prod" 
                                   value="" class="regular-text" placeholder="sk_live_..." />
                        <?php endif; ?>
                        <p class="description">Your Stripe secret key for production</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="public_key_field_prod">Publishable Key (Live)</label>
                    </th>
                    <td>
                        <input type="text" name="public_key_field_prod" id="public_key_field_prod" 
                               value="<?php echo esc_attr($prod_public); ?>" 
                               class="regular-text" placeholder="pk_live_..." />
                        <p class="description">Your Stripe publishable key for production</p>
                    </td>
                </tr>
                
                <tr>
                    <th colspan="2"><h2>Test Keys</h2></th>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="product_key_field_dev">Secret Key (Test)</label>
                    </th>
                    <td>
                        <?php if (!empty($dev_secret)): ?>
                            <input type="text" readonly class="regular-text" value="<?php echo str_repeat('•', 40) . substr($dev_secret, -4); ?>" style="background: #f0f0f0;" />
                            <br><label><input type="checkbox" name="change_dev_secret" id="change_dev_secret" value="1" /> Change key</label>
                            <input type="password" name="product_key_field_dev" id="product_key_field_dev_input" 
                                   value="" class="regular-text" placeholder="sk_test_..." style="display:none;margin-top:5px;" />
                        <?php else: ?>
                            <input type="password" name="product_key_field_dev" id="product_key_field_dev" 
                                   value="" class="regular-text" placeholder="sk_test_..." />
                        <?php endif; ?>
                        <p class="description">Your Stripe secret key for testing</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="public_key_field_dev">Publishable Key (Test)</label>
                    </th>
                    <td>
                        <input type="text" name="public_key_field_dev" id="public_key_field_dev" 
                               value="<?php echo esc_attr($dev_public); ?>" 
                               class="regular-text" placeholder="pk_test_..." />
                        <p class="description">Your Stripe publishable key for testing</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <script>
        document.getElementById('change_prod_secret')?.addEventListener('change', function() {
            document.getElementById('product_key_field_prod_input').style.display = this.checked ? 'block' : 'none';
        });
        document.getElementById('change_dev_secret')?.addEventListener('change', function() {
            document.getElementById('product_key_field_dev_input').style.display = this.checked ? 'block' : 'none';
        });
        </script>
    </div>
    <?php
}