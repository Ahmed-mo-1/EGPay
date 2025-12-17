<?php

add_action('wp_ajax_egpay_submit_receipt_ajax', 'egpay_handle_ajax_upload');
add_action('wp_ajax_nopriv_egpay_submit_receipt_ajax', 'egpay_handle_ajax_upload');

function egpay_handle_ajax_upload() {
    check_ajax_referer('egpay_upload_nonce', 'security');

    if (empty($_FILES['egpay_receipt'])) {
        wp_send_json_error('Please select a file.');
    }

    $order_id = intval($_POST['egpay_order_id']);
    $order = wc_get_order($order_id);

    if (!$order || $order->get_order_key() !== $_POST['egpay_order_key']) {
        wp_send_json_error('Invalid order details.');
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    // Set up upload overrides to bypass security check on 'test_form'
    $upload = wp_handle_upload($_FILES['egpay_receipt'], ['test_form' => false]);

    if (isset($upload['url'])) {
        $order->update_meta_data('_egpay_receipt', $upload['url']);
        $order->update_status('pending-review', 'Receipt uploaded by customer (AJAX).');
        $order->save();
        
        // Clear guest session since they finished
        if (WC()->session) {
            WC()->session->set('egpay_guest_order', null);
        }
        
        wp_send_json_success('Receipt uploaded successfully! We will review your payment shortly.');
    } else {
        wp_send_json_error('Upload failed: ' . ($upload['error'] ?? 'Unknown error'));
    }
}