<?php
/*
Plugin Name: EGPay
Version: 0.4
Description: Adds an EGPay payment gateway with receipt upload functionality for both logged-in and guest users, using WooCommerce sessions for guests. Includes AJAX for order lookup.
Author: Gemini
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

            // Load settings
            $this->init_settings();

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
                    'note'=> 'Pay easily with your Vodafone Cash account.'
                ),
                'InstaPay' => array(
                    'label' => 'InstaPay',
                    'note'=> 'Pay securely with your InstaPay card.'
                ),
            );

            echo '<div class="egpay-options">';
            foreach ($options as $key => $opt) {
                // Remove the image part since you commented out the URL - keeping the structure simple
                $checked = checked(isset($_POST['egpay_option']) ? $_POST['egpay_option'] : '', $key, false);
                echo '<label style="display:block;margin-bottom:15px;cursor:pointer;">';
                echo '<input type="radio" name="egpay_option" value="' . esc_attr($key) . '" ' . $checked . ' required style="margin-right:10px;">';
                // You can uncomment and fix the image if needed: echo '<img src="' . esc_url($opt['image']) . '" style="height:40px;margin-right:10px;vertical-align:middle;">';
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

            if (!empty($_POST['egpay_option'])) {
                $order->update_meta_data('_egpay_sub_option', sanitize_text_field($_POST['egpay_option']));
                $order->save();
            }

            $order->update_status('pending', __('Awaiting EGPay receipt upload', 'woocommerce'));

            // ✅ SAVE ORDER ID IN SESSION FOR GUEST
            if (!is_user_logged_in() && WC()->session) {
                WC()->session->set('egpay_guest_order', $order_id);
            }

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result'=> 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

    }
});

/*----------------------------------------------------
2. CUSTOM ORDER STATUS: PENDING REVIEW
----------------------------------------------------*/
add_action('init', function () {
    register_post_status('wc-pending-review', array(
        'label'=> 'Pending Review',
        'public'=> true,
        'show_in_admin_all_list'=> true,
        'show_in_admin_status_list' => true,
        'label_count'=> _n_noop(
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
3. SHORTCODE: UPLOAD RECEIPT (AJAX ORDER SEARCH)
----------------------------------------------------*/
add_shortcode('egpay_upload_receipt', function () {

    // Ensure WC session is initialized if we are using it for guests
    if (!WC()->session && !headers_sent()) {
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
    
    ob_start();

    // ============================
    // LOGGED-IN USER
    // ============================
    if (is_user_logged_in()) {

        $orders = wc_get_orders([
            'customer_id'=> get_current_user_id(),
            'status'=> 'pending',
            'payment_method' => 'EGPay',
            'limit'=> -1,
        ]);

        if (!$orders) {
            echo '<p>No pending EGPay orders found for your account.</p>';
            return ob_get_clean();
        }

        echo '<h3>Pending EGPay Orders</h3>';

        foreach ($orders as $order) {
            egpay_upload_form($order);
        }

        return ob_get_clean();
    }

    // ============================
    // GUEST USER - FROM SESSION
    // ============================
    if (WC()->session) {

        $order_id = WC()->session->get('egpay_guest_order');

        if ($order_id) {

            $order = wc_get_order($order_id);

            if (
                $order &&
                $order->get_payment_method() === 'EGPay' &&
                $order->get_status() === 'pending'
            ) {

                echo '<h3>Your Pending EGPay Order from Last Checkout</h3>';
                egpay_upload_form($order, true); // Pass true for guest order
                
                // Don't return, allow the form below to show for others or if they need to search a different order
                echo '<hr>';
            }
            
            // If the order exists but is not 'pending' (e.g. processed), clear the session to show the search form.
            if ($order && $order->get_payment_method() === 'EGPay' && $order->get_status() !== 'pending') {
                 WC()->session->set('egpay_guest_order', null);
            }
        }
    }

    // ============================
    // GUEST USER - SEARCH FORM (AJAX TARGET)
    // ============================
    ?>
    <div id="egpay-lookup-wrapper">
        <h3>Find Your Pending Order</h3>

        <form id="egpay-find-order-form" method="post">
            <p>
                <input type="number" name="egpay_order_id" id="egpay_order_id" placeholder="Order ID" required>
            </p>
            <p>
                <input type="email" name="egpay_email" id="egpay_email" placeholder="Billing Email" required>
            </p>
            <p>
                <button type="submit" id="egpay-find-order-btn">Find Order</button>
            </p>
        </form>
        <div id="egpay-lookup-result">
            </div>
    </div>
    
    <?php
    
    // Add AJAX script to the footer
    add_action('wp_footer', function() {
        if (!is_page()) return; // Only run on relevant pages (adjust as needed)
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#egpay-find-order-form').on('submit', function(e) {
                    e.preventDefault();

                    var orderId = $('#egpay_order_id').val();
                    var email = $('#egpay_email').val();
                    var button = $('#egpay-find-order-btn');
                    
                    button.prop('disabled', true).text('Searching...');

                    $.ajax({
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'egpay_find_order_ajax',
                            order_id: orderId,
                            email: email,
                            security: '<?php echo wp_create_nonce('egpay_find_order_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#egpay-lookup-result').html(response.data.form_html);
                                // Optional: Hide the lookup form after success
                                $('#egpay-find-order-form').hide();
                            } else {
                                $('#egpay-lookup-result').html('<p style="color:red;">' + response.data + '</p>');
                                $('#egpay-find-order-form').show();
                            }
                            button.prop('disabled', false).text('Find Order');
                        },
                        error: function() {
                            $('#egpay-lookup-result').html('<p style="color:red;">An error occurred while communicating with the server.</p>');
                            button.prop('disabled', false).text('Find Order');
                        }
                    });
                });
            });
        </script>
        <?php
    });

    return ob_get_clean();
});


function egpay_upload_form($order, $is_guest_checkout = false) {
    // You must set the URL for the receipt handler page here
    //$receipt_handler_url = home_url('/receipt-success');
 // ⚠️ IMPORTANT: CHANGE THIS TO THE PAGE CONTAINING [egpay_order_info]
    
    ob_start();
    ?>
    <div style="border:1px solid #ddd;padding:15px;margin-bottom:15px;">
        <p><strong>Order #<?php echo esc_html($order->get_id()); ?></strong></p>
        <p>Total: <?php echo wc_price($order->get_total()); ?></p>

        <?php
        $sub = $order->get_meta('_egpay_sub_option');
        if ($sub) echo '<p><strong>Payment Option:</strong> ' . esc_html($sub) . '</p>';
        ?>

        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($receipt_handler_url); ?>">
            <input type="file" name="egpay_receipt" required accept="image/*" style="margin-bottom:10px; display:block;">
            <input type="hidden" name="egpay_order_id" value="<?php echo esc_attr($order->get_id()); ?>">
            <input type="hidden" name="egpay_order_key" value="<?php echo esc_attr($order->get_order_key()); ?>">
            <?php if ($is_guest_checkout): ?>
                <input type="hidden" name="egpay_guest_upload" value="1">
            <?php endif; ?>
            <button type="submit" name="egpay_upload_btn">Upload Receipt</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/*----------------------------------------------------
4. AJAX HANDLER: FIND PENDING ORDER
----------------------------------------------------*/
add_action('wp_ajax_egpay_find_order_ajax', 'egpay_find_order_ajax_handler');
add_action('wp_ajax_nopriv_egpay_find_order_ajax', 'egpay_find_order_ajax_handler');

function egpay_find_order_ajax_handler() {
    
    check_ajax_referer('egpay_find_order_nonce', 'security');

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $email= isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (empty($order_id) || empty($email)) {
        wp_send_json_error('Please provide both Order ID and Billing Email.');
    }

    $order = wc_get_order($order_id);

    if (
        $order &&
        $order->get_billing_email() === $email &&
        $order->get_payment_method() === 'EGPay' &&
        $order->get_status() === 'pending'
    ) {
        $form_html = egpay_upload_form($order, true); // Treat found order as a guest/non-logged-in submission
        
        wp_send_json_success([
            'order_id' => $order_id,
            'form_html' => $form_html
        ]);
    } else {
        wp_send_json_error('Order not found, invalid, or already processed. Only "Pending" EGPay orders can be updated.');
    }
}


/*----------------------------------------------------
5. HANDLE UPLOAD + CHANGE STATUS
----------------------------------------------------*/
add_action('init', function () {
    
    // Ensure WC session is initialized if not already done
    if (!WC()->session && !headers_sent()) {
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
    
    if (!isset($_POST['egpay_upload_btn'])) return;

    $order_id= intval($_POST['egpay_order_id']);
    $order_key = sanitize_text_field($_POST['egpay_order_key']);
    $is_guest_upload = isset($_POST['egpay_guest_upload']);
    
    // ⚠️ IMPORTANT: Define the URL to redirect to on success
    $success_redirect = home_url('/receipt-success'); 

    $order = wc_get_order($order_id);

    if (
        !$order ||
        $order->get_order_key() !== $order_key ||
        $order->get_payment_method() !== 'EGPay' ||
        $order->get_status() !== 'pending'
    ) {
        // Redirect on invalid order
        wp_safe_redirect(home_url()); // Redirect to home or another safe URL
        exit;
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $upload = wp_handle_upload($_FILES['egpay_receipt'], [
        'test_form' => false,
        'mimes'=> [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'=> 'image/png',
            'gif'=> 'image/gif',
        ],
    ]);

    if (!empty($upload['url'])) {
        $order->update_meta_data('_egpay_receipt', esc_url($upload['url']));
        $order->add_order_note('EGPay receipt uploaded: ' . esc_url($upload['url']));
        $order->update_status('pending-review', 'EGPay receipt uploaded');
        $order->save();

        // ✅ HANDLE SESSION CLEAR & SUCCESS REDIRECT
        if (WC()->session) {
            // Clear the general guest order session if it was set
            if (WC()->session->get('egpay_guest_order') == $order_id) {
                WC()->session->set('egpay_guest_order', null);
            }
            
            // Set success message and order details for the redirect page
            WC()->session->set('egpay_upload_success', [
                'order_id' => $order_id,
                'is_guest' => $is_guest_upload,
            ]);
        }
        
        // Redirect to a success page to display order info and prevent resubmission
        wp_safe_redirect(esc_url($success_redirect));
        exit;

    } else {
        // Redirect back to the upload page with an error or handle it gracefully
        wc_add_notice('Error uploading receipt: ' . (isset($upload['error']) ? $upload['error'] : 'Unknown error'), 'error');
        wp_safe_redirect($_SERVER['HTTP_REFERER'] ?: home_url('/upload-receipt')); // Fallback redirect
        exit;
    }
});

/*----------------------------------------------------
6. SHORTCODE: DISPLAY ORDER INFO AFTER UPLOAD
----------------------------------------------------*/
add_shortcode('egpay_order_info', function () {
    
    // Ensure WC session is initialized
    if (!WC()->session && !headers_sent()) {
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
    
    if (!WC()->session || !WC()->session->get('egpay_upload_success')) {
        return '<p>No recent receipt upload found or session expired. Please check your email for order confirmation.</p>';
    }

    $data = WC()->session->get('egpay_upload_success');
    $order_id = intval($data['order_id']);
    $is_guest = $data['is_guest'];
    $order = wc_get_order($order_id);
    
    // Clear the success message session immediately after reading it
    WC()->session->set('egpay_upload_success', null);
    
    ob_start();

    if (!$order) {
        echo '<p>Error: Order not found.</p>';
        return ob_get_clean();
    }
    
    $sub = $order->get_meta('_egpay_sub_option');

    ?>
    <div class="woocommerce-message woocommerce-message--success">
        <p>✅ **Receipt Uploaded Successfully!**</p>
    </div>

    <div style="border: 1px solid #c3c4c7; padding: 20px; background: #f8f8f8;">
        <h2>Order Details (Ref: #<?php echo esc_html($order->get_id()); ?>)</h2>
        
        <p><strong>Order Total:</strong> <?php echo wc_price($order->get_total()); ?></p>
        <p><strong>Payment Method:</strong> <?php echo esc_html($order->get_payment_method_title()); ?></p>
        <?php if ($sub): ?>
            <p><strong>Payment Option:</strong> <?php echo esc_html($sub); ?></p>
        <?php endif; ?>
        <p><strong>Current Status:</strong> <span style="font-weight: bold; color: #1e85be;"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span></p>
        
        <p>Thank you for submitting your payment receipt. Your order is now **Pending Review**. We will verify the payment shortly and update your order status. You will receive an email confirmation.</p>
        
        <?php if ($is_guest): ?>
            <div style="border-top: 1px dashed #ccc; margin-top: 15px; padding-top: 15px;">
                <p>⚠️ **Please note:** Since you checked out as a guest, you will need your **Order ID (<?php echo esc_html($order->get_id()); ?>)** and **Billing Email** to look up this order later.</p>
            </div>
        <?php endif; ?>
        
        <p style="text-align: right; margin-top: 20px;">
            <a href="<?php echo esc_url(home_url()); ?>" class="button">Continue Shopping</a>
        </p>
    </div>
    <?php
    
    return ob_get_clean();
});

/*----------------------------------------------------
7. ADMIN ORDER LIST: RECEIPT COLUMN + POPUP (Unmodified Admin Code)
----------------------------------------------------*/
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

/*----------------------------------------------------
8. SHOW SELECTED SUB-OPTION IN ORDER DETAILS
----------------------------------------------------*/
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