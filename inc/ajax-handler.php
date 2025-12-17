<?php

add_action('wp_ajax_egpay_find_order_ajax', 'egpay_find_order_ajax_handler');
add_action('wp_ajax_nopriv_egpay_find_order_ajax', 'egpay_find_order_ajax_handler');
function egpay_find_order_ajax_handler() {
    check_ajax_referer('egpay_find_order_nonce', 'security');
    $order = wc_get_order(intval($_POST['order_id']));
    if ($order && $order->get_billing_email() === sanitize_email($_POST['email']) && $order->get_status() === 'pending') {
        wp_send_json_success(['form_html' => egpay_upload_form($order, true)]);
    } else {
        wp_send_json_error('Order not found or not in Pending status.');
    }
}