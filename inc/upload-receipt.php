<?php

add_action('template_redirect', function() {
    if (!isset($_POST['egpay_upload_btn'])) return;

    $order = wc_get_order(intval($_POST['egpay_order_id']));
    if (!$order || $order->get_order_key() !== $_POST['egpay_order_key']) return;

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $upload = wp_handle_upload($_FILES['egpay_receipt'], ['test_form' => false]);

    if (isset($upload['url'])) {
        $order->update_meta_data('_egpay_receipt', $upload['url']);
        $order->update_status('pending-review', 'Receipt uploaded by customer.');
        $order->save();
        
        if (WC()->session) {
            WC()->session->set('egpay_guest_order', null);
            WC()->session->set('egpay_upload_success', $order->get_id());
        }
        
        wp_redirect(home_url('/receipt-success')); // Ensure this page exists!
        exit;
    }
});
