<?php
/*
Plugin Name: EGPay
Version: 0.1
*/

if (!defined('ABSPATH')) exit;

/*----------------------------------------------------
  1. ADD PAYMENT GATEWAY
----------------------------------------------------*/
add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = 'WC_Gateway_EGPay';
    return $gateways;
});

add_action('plugins_loaded', function () {

    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_EGPay extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'EGPay';
            $this->method_title = 'EGPay';
            $this->method_description = 'Pay using EGPay and upload receipt.';
            $this->has_fields = true;

            $this->title = 'EGPay';
            $this->description = 'Pay via EGPay and upload receipt.';

            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );
        }

        // Display payment options with images and notes
        public function payment_fields() {

            $options = array(
                'Vodafone Cash' => array(
                    'label' => 'Vodafone Cash',
/*                    'image' => 'https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_111x69.jpg',*/
                    'note'  => 'Pay easily with your Vodafone Cash account.'
                ),
                'InstaPay' => array(
                    'label' => 'InstaPay',
/*                    'image' => 'https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png',*/
                    'note'  => 'Pay securely with your InstaPay card.'
                ),
/*
                'phone' => array(
                    'label' => 'Phone Payment',
                    'image' => 'https://cdn-icons-png.flaticon.com/512/597/597177.png',
                    'note'  => 'Pay via phone using the given number.',
                    'phone' => '+1234567890'
                )*/
            );

            echo '<div class="egpay-options">';
            foreach ($options as $key => $opt) {
                $checked = checked(isset($_POST['egpay_option']) ? $_POST['egpay_option'] : '', $key, false);
                echo '<label style="display:block;margin-bottom:15px;cursor:pointer;">';
                echo '<input type="radio" name="egpay_option" value="' . esc_attr($key) . '" ' . $checked . ' required style="margin-right:10px;">';
                echo '<img src="' . esc_url($opt['image']) . '" style="height:40px;margin-right:10px;vertical-align:middle;">';
                echo esc_html($opt['label']);
                if (!empty($opt['phone'])) echo ' (' . esc_html($opt['phone']) . ')';
                if (!empty($opt['note'])) echo '<br><small style="margin-left:50px;color:#555;">' . esc_html($opt['note']) . '</small>';
                echo '</label>';
            }
            echo '</div>';
        }

        // Validate selection
        public function validate_fields() {
            if (empty($_POST['egpay_option'])) {
                wc_add_notice('Please select a payment option.', 'error');
                return false;
            }
            return true;
        }

        // Process payment
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // Save selected option
            if (!empty($_POST['egpay_option'])) {
                $order->update_meta_data('_egpay_sub_option', sanitize_text_field($_POST['egpay_option']));
            }

            // Set order status pending payment
            $order->update_status('pending', __('Awaiting EGPay receipt upload', 'woocommerce'));

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }
});

/*----------------------------------------------------
  2. CUSTOM ORDER STATUS: PENDING REVIEW
----------------------------------------------------*/
add_action('init', function () {
    register_post_status('wc-pending-review', array(
        'label'                     => 'Pending Review',
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'Pending Review <span class="count">(%s)</span>',
            'Pending Review <span class="count">(%s)</span>'
        ),
    ));
});

add_filter('wc_order_statuses', function ($statuses) {
    $statuses['wc-pending-review'] = 'Pending Review';
    return $statuses;
});

/*----------------------------------------------------
  3. SHORTCODE: UPLOAD RECEIPT + SHOW SELECTED OPTION
----------------------------------------------------*/
add_shortcode('egpay_upload_receipt', function () {

    if (!is_user_logged_in()) return '<p>Please login to upload EGPay receipt.</p>';

    $customer_id = get_current_user_id();

    $orders = wc_get_orders(array(
        'customer_id'    => $customer_id,
        'status'         => 'pending',
        'payment_method' => 'EGPay',
        'limit'          => -1,
    ));

    if (empty($orders)) return '<p>No pending EGPay orders found.</p>';

    ob_start(); ?>

    <h3>Pending EGPay Orders</h3>

    <?php foreach ($orders as $order): ?>
        <div style="border:1px solid #ddd;padding:15px;margin-bottom:15px;">
            <p><strong>Order #<?php echo $order->get_id(); ?></strong></p>
            <p>Total: <?php echo wc_price($order->get_total()); ?></p>

            <?php
            $sub_option = $order->get_meta('_egpay_sub_option');
            if ($sub_option) echo '<p><strong>Selected Payment Option:</strong> ' . esc_html($sub_option) . '</p>';
            ?>

            <form method="post" enctype="multipart/form-data">
                <input type="file" name="egpay_receipt" required accept="image/*">
                <input type="hidden" name="egpay_order_id" value="<?php echo esc_attr($order->get_id()); ?>">
                <button type="submit" name="egpay_upload_btn">
                    Upload Receipt
                </button>
            </form>
        </div>
    <?php endforeach;

    return ob_get_clean();
});

/*----------------------------------------------------
  4. HANDLE UPLOAD + CHANGE STATUS
----------------------------------------------------*/
add_action('init', function () {

    if (!isset($_POST['egpay_upload_btn'])) return;
    if (!is_user_logged_in()) return;

    $order_id = intval($_POST['egpay_order_id']);
    $order = wc_get_order($order_id);
    if (!$order || $order->get_user_id() !== get_current_user_id()) return;
    if ($order->get_payment_method() !== 'EGPay' || $order->get_status() !== 'pending') return;

    if (!function_exists('wp_handle_upload')) require_once ABSPATH . 'wp-admin/includes/file.php';

    $upload = wp_handle_upload($_FILES['egpay_receipt'], array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
        ),
    ));

    if (!empty($upload['url'])) {
        $order->update_meta_data('_egpay_receipt', esc_url($upload['url']));
        $order->update_status('pending-review', 'EGPay receipt uploaded');
        $order->save();
    }
});

/*----------------------------------------------------
  5. ADMIN ORDER LIST: RECEIPT COLUMN + POPUP
----------------------------------------------------*/
add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') $new_columns['egpay_receipt'] = __('Receipts', 'woocommerce');
    }
    return $new_columns;
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
    <?php
});

/*----------------------------------------------------
  6. SHOW SELECTED SUB-OPTION IN ORDER DETAILS
----------------------------------------------------*/
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $sub_option = $order->get_meta('_egpay_sub_option');
    if ($sub_option) echo '<p><strong>EGPay Option:</strong> ' . esc_html($sub_option) . '</p>';
});
