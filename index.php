<?php

/*
Plugin Name: EGPay
*/


if (!defined('ABSPATH')) {
    exit; 
}

// Hook to add the gateway
add_filter('woocommerce_payment_gateways', 'EGPay_add_gateway_class');
function EGPay_add_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_EGPay';
    return $gateways;
}

// Define the payment gateway class
add_action('plugins_loaded', 'EGPay_init_gateway_class');
function EGPay_init_gateway_class() {
    class WC_Gateway_EGPay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'EGPay';
            $this->icon = ''; // Add custom icon if needed
            $this->has_fields = false;
            $this->method_title = __('EGPay', 'woocommerce');
            $this->method_description = __('Pay upon delivery using EGPay.', 'woocommerce');

            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');

            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Define settings fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable EGPay Payment', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => __('EGPay', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __('Pay using EGPay upon delivery.', 'woocommerce'),
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __('Please pay upon delivery.', 'woocommerce'),
                ),
            );
        }

        // Process the payment (simply marks order as on-hold)
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Awaiting EGPay payment', 'woocommerce'));
            $order->reduce_order_stock();
            WC()->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }
}