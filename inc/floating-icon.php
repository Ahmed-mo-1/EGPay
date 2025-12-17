<?php

add_action('wp_footer', function() {
    $pending_order_id = null;

    if (is_user_logged_in()) {
        $orders = wc_get_orders([
            'customer_id'    => get_current_user_id(),
            'status'         => 'pending',
            'payment_method' => 'EGPay',
            'limit'          => 1,
        ]);
        if ($orders) $pending_order_id = $orders[0]->get_id();
    } elseif (WC()->session) {
        $pending_order_id = WC()->session->get('egpay_guest_order');
    }

    if (!$pending_order_id) return;

    $order = wc_get_order($pending_order_id);
    if (!$order || $order->get_status() !== 'pending' || $order->get_payment_method() !== 'EGPay') return;

    ?>
    <style>
        #egpay-float-btn { position: fixed; bottom: 30px; left: 30px; width: 60px; height: 60px; background: red; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 99999; border: none; padding: 14px }
	#egpay-float-btn img {width: 100%; height: 100%; filter: brightness(0) invert(1)}
        #egpay-float-btn svg { width: 30px; height: 30px; fill: #fff; }
        #egpay-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 100000; align-items: center; justify-content: center; }
        .egpay-modal-box { background: #fff; width: 90%; max-width: 400px; border-radius: 10px; padding: 20px; position: relative; }
        .egpay-modal-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
    </style>

    <button id="egpay-float-btn" title="Upload Receipt">
		<img src="<?php echo plugin_dir_url(__FILE__); ?>../assets/svgs/receipt.svg" >
    </button>

    <div id="egpay-modal-overlay">
        <div class="egpay-modal-box">
            <span class="egpay-modal-close">&times;</span>
            <h3 style="margin-top:0">Upload Receipt (#<?php echo $pending_order_id; ?>)</h3>
            <?php echo do_shortcode('[egpay_upload_receipt]'); ?>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#egpay-float-btn').click(function() { $('#egpay-modal-overlay').css('display', 'flex'); });
            $('.egpay-modal-close, #egpay-modal-overlay').click(function(e) { if (e.target === this) $('#egpay-modal-overlay').hide(); });
        });
    </script>
    <?php
});
