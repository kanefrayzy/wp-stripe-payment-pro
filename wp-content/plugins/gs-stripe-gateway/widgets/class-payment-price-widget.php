<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Custom_payment_price_Widget extends Widget_Base
{

    public function get_name()
    {
        return 'payment_price';
    }

    public function get_title()
    {
        return 'Payment Price';
    }
    public function get_icon()
    {
        return 'eicon-stripe-button';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function _register_controls() {
        // טאב כותרת
        $this->start_controls_section(
            'title_section', // מזהה הטאב
            [
                'label' => __( 'Title Settings', 'plugin-name' ), // כותרת הטאב
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE, // סוג הטאב
            ]
        );
    
        // שדה טקסט עבור כותרת
        $this->add_control(
            'price_title', 
            [
                'label'     => __( 'Price Title', 'plugin-name' ),
                'type'      => \Elementor\Controls_Manager::TEXT,
                'default'   => __( 'סה"כ לתשלום​', 'plugin-name' ),
            ]
        );
    
        // עריכת פונט עבור כותרת
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'label'    => __( 'Title Typography', 'plugin-name' ),
                'selector' => '{{WRAPPER}} .elementor-widget-price-title',
            ]
        );
    
        // צבע עבור כותרת
        $this->add_control(
            'title_color', 
            [
                'label'     => __( 'Title Color', 'plugin-name' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'selectors' => [
                    '{{WRAPPER}} .elementor-widget-price-title' => 'color: {{VALUE}};',
                ],
            ]
        );
    
        $this->end_controls_section();
    
        // טאב מחיר
        $this->start_controls_section(
            'price_section', 
            [
                'label' => __( 'Price Settings', 'plugin-name' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
    
        // עריכת פונט עבור המחיר
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'price_typography',
                'label'    => __( 'Price Typography', 'plugin-name' ),
                'selector' => '{{WRAPPER}} #total_price',
            ]
        );
    
        // צבע עבור המחיר
        $this->add_control(
            'price_color', 
            [
                'label'     => __( 'Price Color', 'plugin-name' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'selectors' => [
                    '{{WRAPPER}} #total_price' => 'color: {{VALUE}};',
                ],
            ]
        );
    
        // יישור טקסט עבור המחיר
        $this->add_responsive_control(
            'price_alignment',
            [
                'label'        => __( 'Price Alignment', 'plugin-name' ),
                'type'         => \Elementor\Controls_Manager::CHOOSE,
                'options'      => [
                    'left'   => [
                        'title' => __( 'Left', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __( 'Center', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => __( 'Right', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'default'      => 'left',
                'selectors'    => [
                    '{{WRAPPER}} #total_price' => 'text-align: {{VALUE}};',
                ],
            ]
        );
    
        $this->end_controls_section();
    
        // טאב חישוב מחיר
        $this->start_controls_section(
            'price_calculation_section',
            [
                'label' => __( 'Price Calculation Settings', 'plugin-name' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
    
        // עריכת פונט עבור חישוב המחיר
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'calc_typography',
                'label'    => __( 'Calculation Typography', 'plugin-name' ),
                'selector' => '{{WRAPPER}} #payment_calculation',
            ]
        );
    
        // צבע עבור חישוב המחיר
        $this->add_control(
            'calc_color', 
            [
                'label'     => __( 'Calculation Color', 'plugin-name' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'selectors' => [
                    '{{WRAPPER}} #payment_calculation' => 'color: {{VALUE}};',
                ],
            ]
        );
    
        // יישור טקסט עבור חישוב המחיר
        $this->add_responsive_control(
            'calc_alignment',
            [
                'label'        => __( 'Calculation Alignment', 'plugin-name' ),
                'type'         => \Elementor\Controls_Manager::CHOOSE,
                'options'      => [
                    'left'   => [
                        'title' => __( 'Left', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __( 'Center', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => __( 'Right', 'plugin-name' ),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'default'      => 'left',
                'selectors'    => [
                    '{{WRAPPER}} #payment_calculation' => 'text-align: {{VALUE}};',
                ],
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
        $page_id = $post->ID ?? '';
        $product_id = get_post_meta($page_id, 'product', true);
        $testmode = get_post_meta($post->ID, 'env_mode', true);
        $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
    
        $stripe = new \Stripe\StripeClient($frkey);
        $product = $stripe->products->retrieve($product_id, []);
        $price = $stripe->prices->retrieve($product->default_price, []);

        $currency = $price->currency ?? '';
        if($currency == 'ils'){
            $currency = '₪';
        }
        
    
        // הגדרות כותרת
        $title_style = '';
        if (!empty($settings['title_typography_font_size'])) {
            $title_style .= "font-size: {$settings['title_typography_font_size']['size']}{$settings['title_typography_font_size']['unit']};";
        }
        if (!empty($settings['title_typography_font_family'])) {
            $title_style .= "font-family: {$settings['title_typography_font_family']};";
        }
        if (!empty($settings['title_color'])) {
            $title_style .= "color: {$settings['title_color']};";
        }
    
        // הגדרות מחיר
        $price_style = '';
        if (!empty($settings['price_typography_font_size'])) {
            $price_style .= "font-size: {$settings['price_typography_font_size']['size']}{$settings['price_typography_font_size']['unit']};";
        }
        if (!empty($settings['price_typography_font_family'])) {
            $price_style .= "font-family: {$settings['price_typography_font_family']};";
        }
        if (!empty($settings['price_color'])) {
            $price_style .= "color: {$settings['price_color']};";
        }
        if (!empty($settings['price_alignment'])) {
            $price_style .= "text-align: {$settings['price_alignment']};";
        }
    
        // הגדרות חישוב מחיר
        $calc_style = '';
        if (!empty($settings['calc_typography_font_size'])) {
            $calc_style .= "font-size: {$settings['calc_typography_font_size']['size']}{$settings['calc_typography_font_size']['unit']};";
        }
        if (!empty($settings['calc_typography_font_family'])) {
            $calc_style .= "font-family: {$settings['calc_typography_font_family']};";
        }
        if (!empty($settings['calc_color'])) {
            $calc_style .= "color: {$settings['calc_color']};";
        }
        if (!empty($settings['calc_alignment'])) {
            $calc_style .= "text-align: {$settings['calc_alignment']};";      
        }

     
    
        // HTML עבור כותרת
        $html .= "<div class='elementor-widget-price-title' style='{$title_style}'>";

        if (!empty($settings['price_title'])) {
            $html .= $settings['price_title'];
        }

        $html .= "</div>";
      
    
        // HTML עבור מחיר
        $html .= "<div class='elementor-widget-price' style='{$price_style}'>";

        if (isset($price->unit_amount)) {      
            $html .= "<div id='total_price'><span class='currency'>$currency</span><bdo>".($price->unit_amount / 100)."</bdo></div>"; 
        } else {
            $html .= "מחיר לא זמין.";
        }
    
        $html .= "<div id='discount'><span class='currency'>$currency</span><bdo></bdo></div>";    
        $html .= "<div id='payment_calculation' style='{$calc_style}'><div><span id='sum'></span><span id='divider'>X</span><span id='payments'></span></div><span>תשלומים</span></div>";  
        $html .= "<input id='discountmem' type='hidden' value=''>";
        $html .= "<input id='discounttype' type='hidden' value=''>";
        $html .= "<input id='discounvalue' type='hidden' value=''>";
        $html .= "</div>";
        
    
        
    
        echo $html;
    }
    
    protected function _content_template() {
        ?>
        <div class="elementor-element elementor-widget elementor-widget-price" 
             style="
                text-align: {{ settings.text_alignment || 'left' }};
                color: {{ settings.custom_text_color || '#000000' }};
                font-size: {{ (settings.custom_typography && settings.custom_typography.size && settings.custom_typography.size.SIZE) ? settings.custom_typography.size.SIZE + (settings.custom_typography.size.UNIT || 'px') : '16px' }};
                font-family: {{ settings.custom_typography && settings.custom_typography.typography || 'inherit' }};">
            <# if (settings.price_title) { #>
                <div class="elementor-widget-price-title">
                    {{{ settings.price_title }}}
                </div>
            <# } #>
            <span id="total_price">
                <# if (settings.price) { #>
                    {{{ settings.price }}}
                <# } else { #>
                    מחיר לא זמין.
                <# } #>
            </span>
        </div>
    
        <div class="elementor-element elementor-widget elementor-widget-price-calculation" 
             style="
                text-align: {{ settings.price_calculation_alignment || 'left' }};
                color: {{ settings.price_calculation_color || '#000000' }};
                font-size: {{ (settings.price_calculation_typography && settings.price_calculation_typography.size && settings.price_calculation_typography.size.SIZE) ? settings.price_calculation_typography.size.SIZE + (settings.price_calculation_typography.size.UNIT || 'px') : '16px' }};
                font-family: {{ settings.price_calculation_typography && settings.price_calculation_typography.typography || 'inherit' }};">
            <# if (settings.price_calculation_text) { #>
                <div class="elementor-widget-price-calculation-title">
                    {{{ settings.price_calculation_text }}}
                </div>
            <# } #>
            <div id="payment_calculation">
                <# if (settings.price) { #>
                    חישוב מחיר: {{{ settings.price * 1.17 }}}
                <# } else { #>
                    חישוב מחיר לא זמין.
                <# } #>
            </div>
        </div>
        <?php
    }

    
}