<?php

add_shortcode('egpay_upload_receipt', function () {
    if (!WC()->session) return "WooCommerce Session not active.";
    
    ob_start();

    // 1. LOGGED IN USERS
    if (is_user_logged_in()) {
        $orders = wc_get_orders(['customer_id' => get_current_user_id(), 'status' => 'pending', 'payment_method' => 'EGPay', 'limit' => -1]);
        if ($orders) {
            foreach ($orders as $order) echo egpay_upload_form($order);
            return ob_get_clean();
        }
    }

    // 2. GUEST FROM SESSION
    $session_order_id = WC()->session->get('egpay_guest_order');
    if ($session_order_id) {
        $order = wc_get_order($session_order_id);
        if ($order && $order->get_status() === 'pending') {
            echo "<h3>Current Order</h3>";
            echo egpay_upload_form($order, true);
        }
    }

    // 3. SEARCH FORM
    ?>
    <div id="egpay-lookup-wrapper" style="margin-top:20px;">
        <h3>Find Your Order to Upload Receipt</h3>
        <form id="egpay-find-order-form">
            <input type="number" id="egpay_order_id" placeholder="Order ID" required style="width:100%;margin-bottom:10px;">
            <input type="email" id="egpay_email" placeholder="Billing Email" required style="width:100%;margin-bottom:10px;">
            <button type="submit" class="button">Find My Order</button>
        </form>
        <div id="egpay-lookup-result" style="margin-top:15px;"></div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#egpay-find-order-form').on('submit', function(e) {
            e.preventDefault();
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'egpay_find_order_ajax',
                order_id: $('#egpay_order_id').val(),
                email: $('#egpay_email').val(),
                security: '<?php echo wp_create_nonce('egpay_find_order_nonce'); ?>'
            }, function(res) {
                if(res.success) { $('#egpay-lookup-result').html(res.data.form_html); $('#egpay-find-order-form').hide(); }
                else { alert(res.data); }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});