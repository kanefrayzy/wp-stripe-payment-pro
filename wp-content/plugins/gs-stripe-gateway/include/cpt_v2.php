<?php
if (!defined('ABSPATH')) die();

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);



function create_custom_cpt($cpt_name, $labels, $args) {
    $args['labels'] = $labels;
    register_post_type($cpt_name, $args);
}

add_action('init', function() {

    $payment_labels = [
        'name'                  => 'עמודי תשלום',
        'singular_name'         => 'עמוד תשלום',
        'menu_name'             => 'עמודי תשלום',
        'name_admin_bar'        => 'עמוד תשלום',
        'add_new'               => 'הוסף חדש',
        'add_new_item'          => 'הוסף עמוד תשלום חדש',
        'new_item'              => 'עמוד תשלום חדש',
        'edit_item'             => 'ערוך עמוד תשלום',
        'view_item'             => 'צפה בעמוד תשלום',
        'all_items'             => 'כל עמודי התשלום',
        'search_items'          => 'חפש עמודי תשלום',
        'not_found'             => 'לא נמצאו עמודי תשלום',
        'not_found_in_trash'    => 'לא נמצאו עמודי תשלום באשפה',
    ];

    $payment_args = [
        'public'                => true,
        'has_archive'           => true,
        'rewrite'               => ['slug' => 'payment-pages'], // Change slug as needed
        'show_in_rest'          => true, // For Gutenberg and REST API support
        'supports'              => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-money-alt',
    ];

    create_custom_cpt('payment_pages', $payment_labels, $payment_args);
    
    $order_labels = [
        'name'                  => 'הזמנות',
        'singular_name'         => 'הזמנה',
        'menu_name'             => 'הזמנות',
        'name_admin_bar'        => 'הזמנה',
        'add_new'               => 'הוסף חדש',
        'add_new_item'          => 'הוסף הזמנה חדשה',
        'new_item'              => 'הזמנה חדשה',
        'edit_item'             => 'ערוך הזמנה',
        'view_item'             => 'צפה בהזמנה',
        'all_items'             => 'כל ההזמנות',
        'search_items'          => 'חפש הזמנה',
        'not_found'             => 'לא נמצאו הזמנות',
        'not_found_in_trash'    => 'לא נמצאו הזמנות באשפה',
    ];

    $order_args = [
        'public'                => true,
        'has_archive'           => true,
        'rewrite'               => ['slug' => 'order'],
        'show_in_rest'          => true,
        'supports'              => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-money-alt',
    ];

    create_custom_cpt('order', $order_labels, $order_args);

    $subscription_labels = [
        'name'                  => 'הוראות קבע',
        'singular_name'         => 'הוראת קבע',
        'menu_name'             => 'הוראות קבע',
        'name_admin_bar'        => 'הוראת קבע',
        'add_new'               => 'הוסף חדש',
        'add_new_item'          => 'הוסף הוראת קבע חדשה',
        'new_item'              => 'הוראת קבע חדשה',
        'edit_item'             => 'ערוך הוראת קבע',
        'view_item'             => 'צפה בהוראת קבע',
        'all_items'             => 'כל הוראות הקבע',
        'search_items'          => 'חפש הוראת קבע',
        'not_found'             => 'לא נמצאו הוראות קבע',
        'not_found_in_trash'    => 'לא נמצאו הוראות קבע באשפה',
    ];

    $subscription_args = [
        'public'                => true,
        'has_archive'           => true,
        'rewrite'               => ['slug' => 'subscription'],
        'show_in_rest'          => true,
        'supports'              => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-money-alt',
    ];

    create_custom_cpt('subscription', $subscription_labels, $subscription_args);
});



function add_custom_fields_meta_box($cpt_name, $fields) {
    add_meta_box(
        'custom_fields_meta_box',        // Unique ID
        'Custom Fields',                 // Box title
        function($post) use ($fields) { display_custom_fields_meta_box($post, $fields); }, // Content callback
        $cpt_name,                       // Post type
        'side',                          // Context (e.g., side, normal)
        'default'                        // Priority
    );
}

function display_custom_fields_meta_box($post, $fields) {
    foreach ($fields as $field) {
        // קבל את הערך מהמטא או השתמש בערך דיפולטיבי אם הוא קיים
        $value = get_post_meta($post->ID, $field['id'], true);
        
        // אם זה פוסט חדש או שהערך ריק, השתמש בברירת מחדל אם היא קיימת
        if (($post->post_status === 'auto-draft' || $value === '') && isset($field['default'])) {
            $value = $field['default'];
        }
        
        $type = isset($field['type']) ? $field['type'] : 'text'; // Default to text if type not specified
        
        echo '<p>';
        echo '<label style="display:block;" for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . ':</label>';
        
        switch ($type) {
            case 'text':
                echo '<input type="text" style="width: 100%;" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" value="' . esc_attr($value) . '" />';
                break;

            case 'number':
                echo '<input type="text" style="width: 20%;" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" value="' . esc_attr($value) . '" />';
            break;
                
            case 'textarea':
                echo '<textarea style="width: 100%;" rows="4" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '">' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" value="1" ' . checked('1', $value, false) . ' />';
                break;
                
            case 'radio':
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $option_value => $option_label) {
                        echo '<div>';
                        echo '<input type="radio" id="' . esc_attr($field['id'] . '_' . $option_value) . '" name="' . esc_attr($field['id']) . '" value="' . esc_attr($option_value) . '" ' . checked($option_value, $value, false) . ' />';
                        echo '<label for="' . esc_attr($field['id'] . '_' . $option_value) . '">' . esc_html($option_label) . '</label>';
                        echo '</div>';
                    }
                }
                break;
                
            case 'dropdown':
                if (isset($field['options']) && is_array($field['options'])) {
                    echo '<select style="width: 100%;" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '">';
                    foreach ($field['options'] as $option_value => $option_label) {
                        echo '<option value="' . esc_attr($option_value) . '" ' . selected($option_value, $value, false) . '>' . esc_html($option_label) . '</option>';
                    }
                    echo '</select>';
                }
                break;
        }
        
        echo '</p>';
    }
}

function save_custom_fields_meta_box_data($post_id, $fields) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach ($fields as $field) {
        
        $type = isset($field['type']) ? $field['type'] : 'text';
        
        // For checkboxes, if not set, we want to clear the value
        if ($type === 'checkbox') {
            if (isset($_POST[$field['id']])) {
                update_post_meta($post_id, $field['id'], '1');
            } else {
                update_post_meta($post_id, $field['id'], '');
            }
        }
        // For all other field types
        elseif (isset($_POST[$field['id']])) {
            $value = $_POST[$field['id']];
            
            // Apply appropriate sanitization based on field type
            switch ($type) {
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                case 'text':
                case 'radio':
                case 'dropdown':
                default:
                    $value = sanitize_text_field($value);
                    break;
            }
            
            update_post_meta($post_id, $field['id'], $value);
        }
    }
}

function add_custom_columns($columns, $fields) {
    foreach ($fields as $field) {
        $columns[$field['id']] = $field['label'];
    }
    return $columns;
}

function populate_custom_columns($column, $post_id, $fields) {
    
    foreach ($fields as $field) {
        if ($column === $field['id']) {
            $value = get_post_meta($post_id, $field['id'], true);
            $type = isset($field['type']) ? $field['type'] : 'text';
            
            
            switch ($type) {
                case 'text':
                    if($field['id'] === 'customer_name'){
                        echo $value === '' ? "<span style='color:red'>✗</span>" : "<span style='color:green'>$value</span>";
                    }
                    
                    if($field['id'] === 'amount'){
                        echo $value === '' ? "<span style='color:red'>✗</span>" : "₪<span style='color:red'>$value</span>";
                    } 

                    if($field['id'] === 'subscription_id'){
                        echo $value === '' ? "<span style='color:red'>✗</span>" : "<span style='color:green'>$value</span>";
                    }
                    
                    if($field['id'] === 'payment_intent_id'){
                        echo $value === '' ? "<span style='color:red'>✗</span>" : "<span style='color:green'>$value</span>";
                    } 

                    // PAYMENT PAGES
                    if($field['id'] === 'crm_product_id'){
                        if(empty($crm_product_id)){
                            echo '<div style="background:red;float:right;padding: 6px 10px;color:#fff;">שדה מזהה מוצר פיירברי ריק!</div>';
                        }
                    }
                    if($field['id'] === 'thankyou'){ 
                        echo $value === '' ? '<span style="color:red">✗</span>' : '<span style="color:green">✓</span>';
                    } 
                    if($field['id'] === 'product'){ 
                        echo $value === '' ? "<span style='color:red'>✗</span>" : "<span style='color:green'>$value</span>";
                    }
                    if($field['id'] === 'page_mode'){
                        echo $value === '1' ? '<span style="color:red">הו"ק</span>' : '<span style="color:green">תשלומים</span>';
                    } 
                    break;
                case 'checkbox':
                        echo $value === '1' ? '<span style="color:green">✓</span>' : '<span style="color:red">✗</span>';
                    break;                  
                case 'radio':  
                    if($field['id'] === 'env_mode'){
                        if($value === '1'){
                            echo "<div style='background:yellow;float:right;padding: 6px 10px;'>טסט</div>";
                        } else {
                            echo "<div style='background: green;float:right;padding: 6px 10px;color: #fff;'>פרוד</div>";
                        }
                    }  
                    if($field['id'] === 'page_mode'){
                        echo $value === '1' ? '<span style="color:red">הו"ק</span>' : '<span style="color:green">תשלומים</span>';
                    }  
                    break;
                case 'dropdown':
                    if (isset($field['options'][$value])) {
                        echo esc_html($field['options'][$value]);
                    } else {
                        echo esc_html($value);
                    }
                    // SUBSCRIPTION
                    /*if($field['id'] === 'status'){
                        
                        if($value === 'requires_action'){
                            echo "<div style='background:yellow;float:right;padding: 6px 10px;'>$value</div>";
                        } else {
                            echo "<div style='background: green;float:right;padding: 6px 10px;color: #fff;'>$value</div>";
                        }
                    } */
                    break;
                    
                case 'textarea':
                    echo wp_trim_words(esc_html($value), 10, '...');
                    break;
                    
                default:
                    echo esc_html($value);
            }
        }
    }
}

function make_custom_columns_sortable($columns, $fields) {
    foreach ($fields as $field) {
        $columns[$field['id']] = $field['id'];
    }
    return $columns;
}

// עדכון הגדרות השדות עבור הזמנות ומנויים עם תמיכה בסוגי שדות חדשים
$order_subscription_fields = [
    ['id' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text'],
    ['id' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'text'],
    ['id' => 'payment_intent_id', 'label' => 'Payment Intent ID', 'type' => 'text'],
    ['id' => 'amount', 'label' => 'Deal Amount', 'type' => 'text'],
    ['id' => 'wp_order_id', 'label' => 'WP Order ID', 'type' => 'text'],
    [
        'id' => 'status', 
        'label' => 'Status', 
        'type' => 'dropdown',
        'options' => [
            'active' => 'Active',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed'
        ]
    ],
    ['id' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ['id' => 'is_priority', 'label' => 'Priority Order', 'type' => 'checkbox'],
];

// עדכון הגדרות שדות עמודי תשלום עם תמיכה בסוגי שדות חדשים
$payment_pages_fields = [
    
    ['id' => 'page_mode', 'label' => 'Page Mode','type' => 'radio','options' => ['0' => 'Payment Limited','1' => 'Subscription'],'default' => '1'],
    ['id' => 'crm_product_id', 'label' => 'Crm Product ID', 'type' => 'text'],
    ['id' => 'max_payment_limit', 'label' => 'Limit Payments', 'type' => 'number'],
    ['id' => 'product', 'label' => 'Product ID', 'type' => 'text'],
    ['id' => 'use_invoice', 'label' => 'Create Invoice (for one-time payments)', 'type' => 'checkbox', 'default' => '0'],
    ['id' => 'crm_send', 'label' => 'Send To Crm', 'type' => 'checkbox'],
    ['id' => 'env_mode', 'label' => 'ENV MODE','type' => 'radio','options' => ['0' => 'Production','1' => 'Test'],'default' => '1'],
    ['id' => 'thankyou', 'label' => 'Thank You Page URL', 'type' => 'text'],
    ['id' => 'comment', 'label' => 'Comment', 'type' => 'textarea'],
];

add_action('add_meta_boxes', function() use ($order_subscription_fields) {
    add_custom_fields_meta_box(['order', 'subscription'], $order_subscription_fields);
});

add_action('save_post', function($post_id) use ($order_subscription_fields) {
    save_custom_fields_meta_box_data($post_id, $order_subscription_fields);
});

add_action('add_meta_boxes', function() use ($payment_pages_fields) {
    add_custom_fields_meta_box(['payment_pages'], $payment_pages_fields);
});

add_action('save_post', function($post_id) use ($payment_pages_fields) {
    save_custom_fields_meta_box_data($post_id, $payment_pages_fields);
});

add_filter('manage_subscription_posts_columns', function($columns) use ($order_subscription_fields) {
    return add_custom_columns($columns, $order_subscription_fields);
});
add_action('manage_subscription_posts_custom_column', function($column, $post_id) use ($order_subscription_fields) {
    populate_custom_columns($column, $post_id, $order_subscription_fields);
}, 10, 2);
add_filter('manage_edit-subscription_sortable_columns', function($columns) use ($order_subscription_fields) {
    return make_custom_columns_sortable($columns, $order_subscription_fields);
});

add_filter('manage_order_posts_columns', function($columns) use ($order_subscription_fields) {
    return add_custom_columns($columns, $order_subscription_fields);
});
add_action('manage_order_posts_custom_column', function($column, $post_id) use ($order_subscription_fields) {
    populate_custom_columns($column, $post_id, $order_subscription_fields);
}, 10, 2);

add_filter('manage_edit-order_sortable_columns', function($columns) use ($order_subscription_fields) {
    return make_custom_columns_sortable($columns, $order_subscription_fields);
});

add_filter('manage_payment_pages_posts_columns', function($columns) use ($payment_pages_fields) {
    return add_custom_columns($columns, $payment_pages_fields);
});
add_action('manage_payment_pages_posts_custom_column', function($column, $post_id) use ($payment_pages_fields) {
    populate_custom_columns($column, $post_id, $payment_pages_fields);
}, 10, 2);
add_filter('manage_edit-payment_pages_sortable_columns', function($columns) use ($payment_pages_fields) {
    return make_custom_columns_sortable($columns, $payment_pages_fields);
});

/* הערה: הקוד הישן למטה הוחלף עם הפונקציות המורחבות למעלה */
// הקוד הישן הוחלף בגרסה משופרת שתומכת בסוגי שדות מרובים

