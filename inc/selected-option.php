<?php

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $sub_option = $order->get_meta('_egpay_sub_option');
    if ($sub_option) echo '<p><strong>EGPay Option:</strong> ' . esc_html($sub_option) . '</p>';
});

// Always initialize WooCommerce Session if not already done, for guests to work
add_action('init', function () {
    if (!WC()->session && !headers_sent()) {
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
}, 1);