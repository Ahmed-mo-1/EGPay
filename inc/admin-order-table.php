<?php

add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') $new_columns['egpay_receipt'] = __('Receipts', 'woocommerce');
    }
    return $new_columns;
});


add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $columns['egpay_actions'] = __('EGPay Actions', 'woocommerce');
    return $columns;
});


add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {

    if ($column !== 'egpay_actions' || !is_a($order, 'WC_Order')) return;

    if (
        $order->get_payment_method() !== 'EGPay' ||
        $order->get_status() !== 'pending-review'
    ) {
        echo '—';
        return;
    }

    $nonce = wp_create_nonce('egpay_action_' . $order->get_id());

    echo '<button class="button button-primary egpay-approve"
            data-id="' . esc_attr($order->get_id()) . '"
            data-nonce="' . esc_attr($nonce) . '">
            Approve
          </button> ';

    echo '<button class="button egpay-reject"
            data-id="' . esc_attr($order->get_id()) . '"
            data-nonce="' . esc_attr($nonce) . '">
            Reject
          </button>';
}, 10, 2);


add_action('wp_ajax_egpay_order_action', function () {

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Permission denied');
    }

    $order_id = intval($_POST['order_id']);
    $action= sanitize_text_field($_POST['action_type']);
    $nonce= sanitize_text_field($_POST['nonce']);

    if (!wp_verify_nonce($nonce, 'egpay_action_' . $order_id)) {
        wp_send_json_error('Invalid nonce');
    }

    $order = wc_get_order($order_id);

    if (
        !$order ||
        $order->get_payment_method() !== 'EGPay' ||
        $order->get_status() !== 'pending-review'
    ) {
        wp_send_json_error('Invalid order');
    }

    if ($action === 'approve') {
        $order->update_status('processing', 'EGPay approved via AJAX');
    }

    if ($action === 'reject') {
        $order->update_status('cancelled', 'EGPay rejected via AJAX');
    }
    
    $order->save();

    wp_send_json_success([
        'new_status' => wc_get_order_status_name($order->get_status())
    ]);
});


add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column !== 'egpay_receipt' || !is_a($order, 'WC_Order')) return;

    $receipt = $order->get_meta('_egpay_receipt');
    if ($receipt) {
        echo '<img src="' . esc_url($receipt) . '" class="egpay-receipt-thumb" data-img="' . esc_url($receipt) . '" style="width:50px;cursor:pointer;border:1px solid #ccc;border-radius:4px;" />';
    } else {
        echo '—';
    }
}, 10, 2);

add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'woocommerce_page_wc-orders') return;
    ?>
    <style>
        .egpay-modal { display: none; position: fixed; z-index: 999999; inset: 0; background: rgba(0,0,0,.7); align-items: center; justify-content: center; }
        .egpay-modal img { max-width: 90%; max-height: 90%; background: #fff; padding: 10px; border-radius: 6px; }
        .egpay-modal .close { position: absolute; top: 20px; right: 30px; font-size: 30px; color: #fff; cursor: pointer; }
    </style>

    <div class="egpay-modal" id="egpay-modal">
        <span class="close">&times;</span>
        <img src="" id="egpay-modal-img">
    </div>

    <script>
    (function () {
        document.addEventListener('click', function (e) {
            const thumb = e.target.closest('.egpay-receipt-thumb');
            if (thumb) {
                e.preventDefault(); e.stopPropagation();
                const img = thumb.getAttribute('data-img');
                document.getElementById('egpay-modal-img').src = img;
                document.getElementById('egpay-modal').style.display = 'flex';
                return;
            }
            if (e.target.classList.contains('close') || e.target.id === 'egpay-modal') {
                document.getElementById('egpay-modal').style.display = 'none';
            }
        }, true);
    })();
    </script>



<script>
jQuery(function ($) {

    function egpayAction(button, action) {

        button.prop('disabled', true).text('Saving...');

        $.post(ajaxurl, {
            action: 'egpay_order_action',
            order_id: button.data('id'),
            action_type: action,
            nonce: button.data('nonce')
        }, function (res) {

            if (res.success) {
                button.closest('td').html(
                    '<strong>Status:</strong> ' + res.data.new_status
                );
            } else {
                alert(res.data || 'Error');
                button.prop('disabled', false);
            }
        });
    }

    $(document).on('click', '.egpay-approve', function () {
        egpayAction($(this), 'approve');
    });

    $(document).on('click', '.egpay-reject', function () {
        if (!confirm('Reject this payment?')) return;
        egpayAction($(this), 'reject');
    });

});
</script>

    <?php
});