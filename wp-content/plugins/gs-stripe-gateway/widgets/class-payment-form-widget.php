<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class Custom_3d_payment_form_Widget extends Widget_Base
{

    public function get_name()
    {
        return '3d_payment_form';
    }

    public function get_title()
    {
        return 'Stripe 3D Payment Form';
    }
    public function get_icon()
    {
        return 'eicon-stripe-button';
    }

    public function get_categories()
    {
        return ['general'];
    }

    public function get_script_depends() {
        return [ 'stripe-widget-sdk' ]; // מחזיר את תלות הסקריפט
    }

    protected function _register_controls()
    {
       

    $repeater = new Repeater();

    // יצירת טאב "תוכן" בתוך הריפיטר
    $repeater->start_controls_tabs('repeater_tabs');

    // טאב "תוכן"
    $repeater->start_controls_tab(
        'repeater_tab_content',
        [
            'label' => 'תוכן',
        ]
    );
    

    $repeater->add_control(
        'field_type',
        [
            'label' => __('Field Type'),
            'type' => Controls_Manager::SELECT,
            'options'       => [
                'text'      => 'Text',
                'email'     => 'Email',
                'tel'       => 'Phone',
                'number'    => 'Number',
                'html'      => 'HTML',
                'textarea'  => 'Textarea',
                'select'    => 'Select',
                'checkbox'  => 'Checkbox',
                'radio'     => 'Radio',
                'hidden'    => 'Hidden',
                'password'  => 'Password',
                'file'      => 'File',
                'date'      => 'Date',
                'url'       => 'URL',
                'card'      => 'Card',
                'expirity'  => 'Expiry',
                'cvv'       => 'CVV',
                'coupon'    => 'Coupon',
                'payments'  => 'Payments',
            ],
            'default' => 'text',
            'label_block' => false,
        ]
    );


    $repeater->add_control(
        'select_options',
        [
            'label' => __('Label'),
            'type' => Controls_Manager::TEXTAREA,
            'placeholder' => 'options',
            'label_block' => true,
            'condition' => [
                'field_type' => ['select','html'],
            ],
        ]
    );        

    $repeater->add_control(
        'title',
        [
            'label' => __('Label'),
            'type' => Controls_Manager::TEXT,
            'default' => 'Form Field',
            'label_block' => false,
        ]
    );

    $repeater->add_control(
        'placeholder',
        [
            'label' => __('Placeholder'),
            'type' => Controls_Manager::TEXT,
            'default' => 'Enter your text',
            'label_block' => false,
        ]
    );

    $repeater->add_control(
        'required',
        [
            'label' => 'Required Field',
            'type' => Controls_Manager::SWITCHER,
            'label_on' => 'Yes',
            'label_off' => 'No',
            'return_value' => 'yes',
            'default' => 'no',
        ]
    );

    $repeater->add_control(
        'field_width',
        [
            'label' => __('Field Width'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                '10' => '10%',
                '20' => '20%',
                '25' => '25%',
                '30' => '30%',
                '33' => '33%',
                '40' => '40%',
                '50' => '50%',
                '60' => '60%',
                '66' => '66%',
                '70' => '70%',
                '75' => '75%',
                '80' => '80%',
                '100'=> '100%',                
            ],
            'default' => '100',
            'label_block' => false,
        ]
    );

    $repeater->end_controls_tab();

    // טאב "מתקדם"
    $repeater->start_controls_tab(
        'repeater_tab_advanced',
        [
            'label' => 'מתקדם',
        ]
    );

    $repeater->add_control(
        'field_status',
        [
            'label' => 'Field Status',
            'type' => Controls_Manager::SWITCHER,
            'label_on' => 'On',
            'label_off' => 'Off',
            'return_value' => 'On',
            'default' => 'On',
        ]
    );

    $repeater->add_control(
        'field_default_value',
        [
            'label' => __('Default Value'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
            'label_block' => false,
        ]
    );

    $repeater->add_control(
        'field_id',
        [
            'label' => __('ID'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
            'label_block' => false,
        ]
    );

    $repeater->add_control(
        'custom_css_class',
        [
            'label' => 'CSS Class',
            'type' => Controls_Manager::TEXT,
            'default' => '',
            'label_block' => true,
        ]
    );

    $repeater->end_controls_tab();

    $repeater->end_controls_tabs();

    // הגדרת הקבוצה החוזרת
    $this->start_controls_section(
        'section_content',
        [
            'label' => 'Form Fields',
        ]
    );

    $this->add_control(
        'form_id',
        [
            'label' => __('Form ID'),
            'type' => Controls_Manager::TEXT,
            'default' => 'Form Field',
            'label_block' => false,
        ]
    ); 

    $this->add_control(
        'form_validation',
        [
            'label' => 'Form Validation',
            'type' => Controls_Manager::SWITCHER,
            'label_on' => 'yes',
            'label_off' => 'no',
            'return_value' => true,
            'default' => 'yes',
        ]
    );

    $this->add_control(
        'form_fields',
        [
            'label' => 'Fields',
            'type' => Controls_Manager::REPEATER,
            'fields' => $repeater->get_controls(),
            'title_field' => '{{{ title }}}',
        ]
    );  

    $this->end_controls_section();
}

protected function render() {
    $settings = $this->get_settings_for_display();

    if (empty($settings)) {
        echo 'Settings are not defined.';
        return false;
    }
    $html = '';
    
    global $post;
    $form = $settings['form_id'] ?? '';
    $fields = $settings['form_fields'] ?? '';
    $page_id = $post->ID ?? '';
    $product_id = get_post_meta($page_id, 'product', true);
    $page_mode  = get_post_meta($page_id,'page_mode', true);

    
    $html .= "<form class='elementor-form' id='payment-form' name='$form' novalidate='true'>";
    $html .= "<div class='elementor-form-fields-wrapper elementor-labels-above'>";

    foreach ($fields as $field) {
        $field_type = isset($field['field_type']) ? sanitize_text_field($field['field_type']) : '';       
        $field_id = isset($field['field_id']) ? sanitize_text_field($field['field_id']) : '';
        $field_e_id = isset($field['_id']) ? sanitize_text_field($field['_id']) : '';
        $field_required = isset($field['required']) ? sanitize_text_field($field['required']) : '';
        $field_width = isset($field['field_width']) ? intval($field['field_width']) : '';
        $field_placeholder = isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '';  
        $custom_css_class = isset($field['custom_css_class']) ? sanitize_html_class($field['custom_css_class']) : '';    
        $field_default_value = isset($field['field_default_value']) ? sanitize_text_field($field['field_default_value']) : '';
        $field_status = isset($field['field_status']) ? sanitize_text_field($field['field_status']) : '';

        if ($field_id) {
            $field_id = $field_id;
        } else {
            $field_id = $field_e_id;
        }

        if ($custom_css_class) {
            $custom_css_class = $custom_css_class;
        } else {
            $custom_css_class = $field_e_id;
        }

        if ($field_required == 'yes') {
            $field_required = 'required';
        } else {
            $field_required = false;
        }

        if ($field_status == 'On') {
            $testmode = get_post_meta($post->ID, 'env_mode', true);
            $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
            $stripe = new \Stripe\StripeClient($frkey);

            $html .= "<div class='elementor-field-type-$field_type elementor-field-group elementor-column elementor-field-group-name elementor-col-$field_width'>";   
            if ($field_type == 'text') {
                $html .= "<input id='$field_id' name='$field_id' type='text' placeholder='$field_placeholder' $field_required class='$custom_css_class'>";
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'select') {
                $select_options = isset($field['select_options']) ? sanitize_textarea_field($field['select_options']) : '';
                $select_options = explode("\n", $select_options);  
                $html .= "<select id='$field_id' name='$field_id' $field_required class='$custom_css_class'>";
                foreach ($select_options as $option) {
                    $html .= "<option val='$option'>$option</option>";
                } 
                $html .= "</select>"; 
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'number') {
                $html .= "<input id='$field_id' name='$field_id' type='text' placeholder='$field_placeholder' $field_required class='$custom_css_class'>";
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'html') {
                $select_options = isset($field['select_options']) ? sanitize_textarea_field($field['select_options']) : '';
                $html .= "<div id='$field_id' class='$custom_css_class $field_id'>$select_options</div>"; 
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'card' || $field_type == 'expirity' || $field_type == 'cvv') {
                $html .= "<div id='$field_id' class='$custom_css_class $field_id'></div>";
                $html .= "<span id='card-errors' role='alert class='validation-alert-label></span>";
            } elseif ($field_type == 'email') {
                $html .= "<input id='$field_id' name='$field_id' type='$field_type' placeholder='$field_placeholder' $field_required class='$custom_css_class'>";
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";            
            } elseif ($field_type == 'tel') {
                $html .= "<input id='$field_id' name='$field_id' type='$field_type' placeholder='$field_placeholder' $field_required class='$custom_css_class'>";
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'hidden') {
                $html .= "<input id='$field_id' name='$field_id' type='hidden'>";   
                $html .= "<span class='validation-alert'>&#9888;</span>";
                $html .= "<span class='validation-alert-label'></span>";
            } elseif ($field_type == 'coupon') { 
                $html .= "<input id='$field_type' name='$field_type' $field_required placeholder='$field_placeholder' value='' class='$custom_css_class'>"; 
                $html .= "<button id='cupon_button' data-status='true'>$field_default_value</button>";  
                $html .= "<span id='success-message'></span>";
                $html .= "<span id='error-message'></span>"; 
            } elseif ($field_type == 'payments') { 
                $html .= "<select id='$field_id' name='$field_type' $field_required class='$custom_css_class'>";    
                //$html .= "<span class='validation-alert'>&#9888;</span>";
                //$html .= "<span class='validation-alert-label'></span>";
                try {
                    
                    $product = $stripe->products->retrieve($product_id, []);
                    $prices = $stripe->prices->all(['limit' => 36, 'product' => $product, 'active' => true, 'product' => $product_id]);

                    if (is_object($prices) && isset($prices->data) && is_array($prices->data)) {
                        $pricesArray = $prices->data;
                        usort($pricesArray, function ($a, $b) {
                            return $b->unit_amount - $a->unit_amount;
                        });
                        $p = 1;
                        foreach ($pricesArray as $payment_price) {
                            if ($p == 1) {
                                $html .= "<option data='$payment_price->id' value='$p'>תשלום $p</option>";
                            } else {
                                $html .= "<option data='$payment_price->id' value='$p'>תשלומים $p</option>";
                            }
                            $p++;
                        }
                    } else {
                        error_log('Failed to retrieve prices: ' . print_r($prices, true));
                        echo 'No prices found or the response format is incorrect.';
                    }
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    error_log('Stripe API error: ' . $e->getMessage());
                    echo 'Error fetching prices from Stripe: ' . $e->getMessage();
                } catch (Exception $e) {
                    error_log('General error: ' . $e->getMessage());
                    echo 'An error occurred: ' . $e->getMessage();
                }
                $html .= "</select>"; 
                
                $product = $stripe->products->retrieve($product_id, []);
                $price = $stripe->prices->retrieve($product->default_price, []);
                $html .= "<input type='hidden' id='price_id' name='price_id' value='$price->id'>";             
                $html .= "<input id='price' type='hidden' value='$price->unit_amount'>";
                
            }
            
            
            $html .= "<input id='page_id' name='page_id' value='$page_id' type='hidden'>";
            $html .= "</div>";
        }

    }
    $product_id = get_post_meta($page_id, 'product', true);
    

    //$this->crm_product_id = get_field('crm_product_id',$this->args['page_id']);

        if($page_mode == 1){
            $product = $stripe->products->retrieve($product_id, []);
            $price = $stripe->prices->retrieve($product->default_price, []);
            $html .= "<input type='hidden' id='price_id' name='price_id' value='$price->id'>"; 
        }

    $html .= "<button type='submit' id='submit'>לתשלום הזמנה</button>";
    $html .= "</div>";
    $html .= "</form>";
    echo $html;
}
    

    

    /*protected function _content_template()
    {
        ?>
        <div class="accordion-widget">
            <# if (settings.accordion_items.length) { #>
            <# _.each(settings.accordion_items, function(item, index) { #>
            <div class="accordion-item<# if (index === 0) { #> active<# } #>">
                <div class="accordion-header">
                    <# if (item.image.url) { #>
                    <div class="accordion-image"><img src="{{ item.image.url }}" alt=""></div>
                    <# } #>
                    <h3 class="accordion-title">{{ item.title }}</h3>
                </div>
                <div class="accordion-content">{{{ item.content }}}</div>
                <# if (item.link.url) { #>
                <a href="{{ item.link.url }}" class="accordion-link">Read More</a>
                <# } #>
            </div>
            <# }); #>
            <# } #>
        </div>
        <?php
    }*/
}