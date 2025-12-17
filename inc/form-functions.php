<?php

function egpay_upload_form($order, $is_guest_checkout = false) {
    if (!$order) return '<p>Order not found.</p>';
    
    $phone = get_option('egpay_admin_phone', '01002720186');
    $instapay = get_option('egpay_admin_instapay', '');
    $selected_method = $order->get_meta('_egpay_sub_option');
    
    ob_start();
    ?>
    <div class="egpay-upload-container" id="egpay-container-<?php echo $order->get_id(); ?>" style="border:1px solid #e5e7eb; padding:20px; border-radius:12px; background:#fff; max-width:450px; margin:20px auto;">
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

        <form id="egpay-ajax-form-<?php echo $order->get_id(); ?>" class="receipt-form" style="margin:0;">
            <div id="egpay-msg-<?php echo $order->get_id(); ?>" style="margin-bottom: 10px; font-weight: bold; text-align: center;"></div>
            
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

            <input type="hidden" name="action" value="egpay_submit_receipt_ajax">
            <input type="hidden" name="egpay_order_id" value="<?php echo $order->get_id(); ?>">
            <input type="hidden" name="egpay_order_key" value="<?php echo $order->get_order_key(); ?>">
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('egpay_upload_nonce'); ?>">

            <button type="submit" class="upload-btn" style="width:100%; padding:14px; background:#4f46e5; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">
                Submit Receipt for Review
            </button>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Handle Ajax Submission
        $('#egpay-ajax-form-<?php echo $order->get_id(); ?>').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var $msg = $('#egpay-msg-<?php echo $order->get_id(); ?>');
            var $btn = $(this).find('button');

            $btn.prop('disabled', true).text('Uploading...');
            $msg.html('');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if(response.success) {
                        $msg.css('color', '#10b981').html('✓ ' + response.data);
                        $('#egpay-ajax-form-<?php echo $order->get_id(); ?>').find('label, .upload-btn, #egpay-preview-wrap-<?php echo $order->get_id(); ?>').hide();
                        // Optional: close modal if in floating popup
                        setTimeout(function() { $('#egpay-modal-overlay').fadeOut(); }, 3000);
                    } else {
                        $msg.css('color', '#ef4444').html('✕ ' + response.data);
                        $btn.prop('disabled', false).text('Submit Receipt for Review');
                    }
                }
            });
        });

        // Image Preview Logic
        $('.egpay-file-input').on('change', function() {
            var orderId = $(this).data('orderid');
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#egpay-preview-img-' + orderId).attr('src', e.target.result);
                    $('#egpay-preview-wrap-' + orderId).show();
                    $('#egpay-label-' + orderId).hide();
                }
                reader.readAsDataURL(file);
            }
        });

        $('.egpay-remove-file').on('click', function() {
            var orderId = $(this).data('orderid');
            $('input[data-orderid="' + orderId + '"]').val('');
            $('#egpay-preview-wrap-' + orderId).hide();
            $('#egpay-label-' + orderId).show();
        });
    });
    </script>
    <?php
    return ob_get_clean();
}