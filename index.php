<?php 
/*
Plugin Name: EGPay
Version: 0.5
Description: Fixed EGPay gateway with working upload form and status checks.
Author: EGPay
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
            $this->init_settings();
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

public function payment_fields() {
            $phone = get_option('egpay_admin_phone', '01002720186');
            $instapay = get_option('egpay_admin_instapay', 'Not Set');

            $options = array(
                'Vodafone Cash' => array(
                    'label' => 'Vodafone Cash',
                    'value' => $phone,
                    'image' => plugin_dir_url(__FILE__) . 'vodafone.png',
                    'note'  => 'Send payment to: ',
                ),
                'InstaPay' => array(
                    'label' => 'InstaPay',
                    'value' => $instapay,
                    'image' => plugin_dir_url(__FILE__) . 'instapay.png',
                    'note'  => 'Transfer to account: ',
                ),
            );
            ?>
            <style>
                .egpay-container { margin: 15px 0; }
                .egpay-card {
                    position: relative;
                    display: flex;
                    align-items: center;
                    padding: 15px;
                    margin-bottom: 12px;
                    background: #fff;
                    border: 2px solid #e5e7eb;
                    border-radius: 12px;
                    cursor: pointer;
                    transition: all 0.2s ease-in-out;
                }
                /* Hide default radio */
                .egpay-card input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                /* Card hover effect */
                .egpay-card:hover { border-color: #6366f1; background: #f9fafb; }
                
                /* Selected state styling */
                .egpay-card.is-selected {
                    border-color: #4f46e5;
                    background: #f5f3ff;
                    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
                }
                
                .egpay-card-img {
                    width: 50px;
                    object-fit: contain;
                    margin: 0 !important;
                    background: none !important;
                    padding: 0px;
                    border: none !important;
                }

				.payment_box.payment_method_EGPay {
					border-radius: 14px !important
				}
                
#add_payment_method #payment div.payment_box::before, .woocommerce-cart #payment div.payment_box::before, .woocommerce-checkout #payment div.payment_box::before {
    top: -0.8em;
}
                .egpay-card-content { flex-grow: 1; }
                
                .egpay-method-title {
                    display: block;
                    font-weight: 700;
                    font-size: 16px;
                    color: #1f2937;
                    margin-bottom: 4px;
                }
                
                .egpay-copy-box {
                    font-size: 13px;
                    color: #4b5563;
                    background: #ffffff;
                    padding: 6px 10px;
                    border-radius: 6px;
                    border: 1px dashed #d1d5db;
                    display: inline-block;
                    margin-top: 5px;
                }

                .egpay-copyable {
                    color: #4f46e5;
                    font-weight: bold;
                    cursor: copy;
                    text-decoration: underline;
                }

                /* Custom Checkmark */
                .egpay-checkmark {
                    width: 20px;
                    height: 20px;
                    border: 2px solid #d1d5db;
                    border-radius: 50%;
                    margin-left: 10px;
                    position: relative;
                }
                .egpay-card.is-selected .egpay-checkmark {
                    background: #4f46e5;
                    border-color: #4f46e5;
                }
                .egpay-card.is-selected .egpay-checkmark::after {
                    content: '';
                    position: absolute;
                    left: 6px;
                    top: 2px;
                    width: 5px;
                    height: 10px;
                    border: solid white;
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg);
                }
            </style>

            <div class="egpay-container">
                <?php foreach ($options as $key => $opt) : ?>
                    <label class="egpay-card <?php echo ($key === 'Vodafone Cash') ? 'is-selected' : ''; ?>">
                        <input type="radio" name="egpay_option" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'Vodafone Cash'); ?>>
                        
                        <div class="egpay-card-content">
                        <img src="<?php echo esc_url($opt['image']); ?>" class="egpay-card-img">

                            <span class="egpay-method-title"><?php echo esc_html($opt['label']); ?></span>
                            <?php if (!empty($opt['value'])) : ?>
                                <div class="egpay-copy-box">
                                    <?php echo esc_html($opt['note']); ?> 
                                    <span class="egpay-copyable" title="Click to copy"><?php echo esc_html($opt['value']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="egpay-checkmark"></div>
                    </label>
                <?php endforeach; ?>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Handle Card Selection Styling
                $('.egpay-card').on('click', function() {
                    $('.egpay-card').removeClass('is-selected');
                    $(this).addClass('is-selected');
                });

                // Copy to Clipboard Logic
                $(document).on('click', '.egpay-copyable', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevents radio selection trigger
                    var text = $(this).text();
                    var $temp = $("<input>");
                    $("body").append($temp);
                    $temp.val(text).select();
                    document.execCommand("copy");
                    $temp.remove();

                    var $this = $(this);
                    var originalText = $this.text();
                    $this.text("Copied!").css("color", "#10b981");
                    setTimeout(function() {
                        $this.text(originalText).css("color", "#4f46e5");
                    }, 1000);
                });
            });
            </script>
            <?php
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            if (!empty($_POST['egpay_option'])) {
                $order->update_meta_data('_egpay_sub_option', sanitize_text_field($_POST['egpay_option']));
                $order->save();
            }
            $order->update_status('pending', __('Awaiting EGPay receipt upload', 'woocommerce'));
            if (!is_user_logged_in() && WC()->session) {
                WC()->session->set('egpay_guest_order', $order_id);
            }
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
    }
});

/*----------------------------------------------------
2. CUSTOM ORDER STATUS
----------------------------------------------------*/
add_action('init', function () {
    register_post_status('wc-pending-review', array(
        'label' => 'Pending Review',
        'public' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Review <span class="count">(%s)</span>', 'Review <span class="count">(%s)</span>'),
    ));
});
add_filter('wc_order_statuses', function ($statuses) {
    $statuses['wc-pending-review'] = 'Pending Review';
    return $statuses;
});

/*----------------------------------------------------
3. SHARED FORM FUNCTION (FIXED)
----------------------------------------------------*/
/*----------------------------------------------------
3. SHARED FORM FUNCTION (UPDATED WITH PREVIEW)
----------------------------------------------------*/
function egpay_upload_form($order, $is_guest_checkout = false) {
    if (!$order) return '<p>Order not found.</p>';
    
    $phone = get_option('egpay_admin_phone', '01002720186');
    $instapay = get_option('egpay_admin_instapay', '');
    $selected_method = $order->get_meta('_egpay_sub_option');
    
    ob_start();
    ?>
    <div class="egpay-upload-container" style="border:1px solid #e5e7eb; padding:20px; border-radius:12px; background:#fff; max-width:450px; margin:20px auto;">
        <div style="text-align:center; margin-bottom:20px;">
            <p style="margin:0; color:#6b7280; font-size:14px;">Order #<?php echo $order->get_id(); ?></p>
            <h2 style="margin:5px 0; font-size:24px;"><?php echo $order->get_formatted_order_total(); ?></h2>
        </div>

        <?php if ($selected_method === 'Vodafone Cash') : ?>
            <div style="background:#fef2f2; border:1px solid #fee2e2; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;">
                <span style="display:block; color:#dc2626; font-weight:bold; font-size:12px; text-transform:uppercase;">Send Vodafone Cash to:</span>
                <div class="egpay-copyable" style="font-size:20px; font-weight:800; color:#b91c1c; cursor:pointer; margin:5px 0;" title="Click to copy">
                    <?php echo esc_html($phone); ?>
                </div>
            </div>
        <?php elseif ($selected_method === 'InstaPay') : ?>
            <div style="background:#f0fdf4; border:1px solid #dcfce7; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;">
                <span style="display:block; color:#16a34a; font-weight:bold; font-size:12px; text-transform:uppercase;">Transfer via InstaPay to:</span>
                <div class="egpay-copyable" style="font-size:18px; font-weight:800; color:#15803d; cursor:pointer; margin:5px 0;" title="Click to copy">
                    <?php echo esc_html($instapay); ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="" class="receipt-form" style="margin:0;">
            
            <label id="egpay-label-<?php echo $order->get_id(); ?>" class="file-upload" style="height:100px; margin-bottom:15px; border: 2px dashed #d1d5db; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 8px;">
                <input type="file" name="egpay_receipt" class="egpay-file-input" required accept="image/*" style="display:none;" data-orderid="<?php echo $order->get_id(); ?>">
                <span style="color:#4f46e5; font-weight:600;">📎 Select Receipt Image</span>
            </label>

            <div id="egpay-preview-wrap-<?php echo $order->get_id(); ?>" style="display:none; text-align:center; margin-bottom:15px;">
                <img id="egpay-preview-img-<?php echo $order->get_id(); ?>" src="#" style="max-width:100%; border-radius:8px; border:1px solid #ddd; margin-bottom:10px;">
                <button type="button" class="egpay-remove-file" data-orderid="<?php echo $order->get_id(); ?>" style="display:block; width:100%; background:#ef4444; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; font-size:12px;">
                    ✕ Remove and Reselect
                </button>
            </div>

            <input type="hidden" name="egpay_order_id" value="<?php echo $order->get_id(); ?>">
            <input type="hidden" name="egpay_order_key" value="<?php echo $order->get_order_key(); ?>">

            <button type="submit" name="egpay_upload_btn" class="upload-btn" style="width:100%; padding:14px; background:#4f46e5; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">
                Submit Receipt for Review
            </button>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Image Preview Logic
        $('.egpay-file-input').on('change', function() {
            var orderId = $(this).data('orderid');
            var file = this.files[0];
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#egpay-preview-img-' + orderId).attr('src', e.target.result);
                    $('#egpay-preview-wrap-' + orderId).show();
                    $('#egpay-label-' + orderId).hide(); // Hide the upload box
                }
                reader.readAsDataURL(file);
            }
        });

        // Remove/Reselect Logic
        $('.egpay-remove-file').on('click', function() {
            var orderId = $(this).data('orderid');
            var $input = $('input[data-orderid="' + orderId + '"]');
            
            $input.val(''); // Clear file
            $('#egpay-preview-wrap-' + orderId).hide(); // Hide preview
            $('#egpay-label-' + orderId).show(); // Show upload box again
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/*----------------------------------------------------
4. SHORTCODE: UPLOAD RECEIPT
----------------------------------------------------*/
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

/*----------------------------------------------------
5. AJAX HANDLER
----------------------------------------------------*/
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

/*----------------------------------------------------
6. HANDLE UPLOAD
----------------------------------------------------*/
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


/*----------------------------------------------------
9. FLOATING ICON & POPUP IN FOOTER
----------------------------------------------------*/
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
        #egpay-float-btn { position: fixed; bottom: 30px; left: 30px; width: 60px; height: 60px; background: red; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 99999; border: none; }
        #egpay-float-btn svg { width: 30px; height: 30px; fill: #fff; }
        #egpay-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 100000; align-items: center; justify-content: center; }
        .egpay-modal-box { background: #fff; width: 90%; max-width: 400px; border-radius: 10px; padding: 20px; position: relative; }
        .egpay-modal-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
    </style>

    <button id="egpay-float-btn" title="Upload Receipt">
        <svg viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
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

/*----------------------------------------------------
10. WP ADMIN SIDEBAR TAB (Like Posts/Media)
----------------------------------------------------*/

// 1. Create the Menu Item
add_action('admin_menu', function() {
    add_menu_page(
        'EGPay Users',           // Page Title
        'EGPay',                 // Menu Title
        'manage_options',        // Capability
        'egpay-admin-settings',  // Menu Slug
        'render_egpay_admin_tab',// Function
        'dashicons-money-alt',   // Icon
        25                       // Position (near Media/Pages)
    );
});

// 2. Render the Admin Page
function render_egpay_admin_tab() {
    // Save settings if form submitted
    if (isset($_POST['egpay_admin_save'])) {
        update_option('egpay_admin_phone', sanitize_text_field($_POST['egpay_admin_phone']));
        update_option('egpay_admin_instapay', sanitize_text_field($_POST['egpay_admin_instapay']));
        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    $phone = get_option('egpay_admin_phone', '01002720186');
    $instapay = get_option('egpay_admin_instapay', '');

    ?>
    <div class="wrap">
        <h1>EGPay Global Settings</h1>
        <p>Manage the primary account details that customers see during checkout.</p>
        
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="egpay_admin_phone">Vodafone Cash Phone</label></th>
                    <td><input name="egpay_admin_phone" type="text" id="egpay_admin_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="egpay_admin_instapay">InstaPay Username</label></th>
                    <td><input name="egpay_admin_instapay" type="text" id="egpay_admin_instapay" value="<?php echo esc_attr($instapay); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Save EGPay Details', 'primary', 'egpay_admin_save'); ?>
        </form>

        <hr>
        <h2>Admin Quick Instructions</h2>
        <ul>
            <li>This information is used across the EGPay gateway.</li>
            <li>Receipts can be verified in the <strong>WooCommerce > Orders</strong> section.</li>
        </ul>
    </div>
    <?php
}