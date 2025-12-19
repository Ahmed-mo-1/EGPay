<?php

add_action('admin_menu', function() {
    add_menu_page(
        'EGPay Users',           
        'EGPay Settings',                 
        'manage_options',        
        'egpay-admin-settings',  
        'render_egpay_admin_tab',
        'dashicons-money-alt',   
        25                       
    );
});

function render_egpay_admin_tab() {
    // Access the shared styles from your previous code
    if (function_exists('mat_get_styles')) {
        mat_get_styles();
    }

    // Save settings logic
    if (isset($_POST['egpay_admin_save'])) {
        update_option('egpay_admin_phone', sanitize_text_field($_POST['egpay_admin_phone']));
        update_option('egpay_admin_instapay', sanitize_text_field($_POST['egpay_admin_instapay']));
        echo '<div class="notice notice-success" style="margin: 20px 0;"><p>EGPay settings saved successfully!</p></div>';
    }

    $phone = get_option('egpay_admin_phone', '01002720186');
    $instapay = get_option('egpay_admin_instapay', '');

    ?>
    <div class="admin-wrap">
        <div style="margin-bottom: 30px;">
            <h1>EGPay Global Settings</h1>
            <p style="color: #94a3b8;">Manage the primary account details that customers see during checkout.</p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px; align-items: start;">
            
            <div class="card-bg">
                <form method="post">
                    <label for="egpay_admin_phone">Vodafone Cash Phone</label>
                    <input name="egpay_admin_phone" type="text" id="egpay_admin_phone" 
                           value="<?php echo esc_attr($phone); ?>" class="input-dark" placeholder="e.g. 010XXXXXXXX">
                    
                    <label for="egpay_admin_instapay">InstaPay Username</label>
                    <input name="egpay_admin_instapay" type="text" id="egpay_admin_instapay" 
                           value="<?php echo esc_attr($instapay); ?>" class="input-dark" placeholder="username@instapay">

                    <div style="margin-top: 30px;">
                        <button type="submit" name="egpay_admin_save" class="btn-primary" style="width: 100%;">
                            Save EGPay Details
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-bg" style="border-color: #38bdf8;">
                <h3 style="margin-top: 0; color: #38bdf8;">Quick Instructions</h3>
                <ul style="color: #94a3b8; font-size: 13px; line-height: 1.6; padding-left: 20px;">
                    <li>This information is used across the <strong>EGPay gateway</strong>.</li>
                    <li>Ensure the phone number is active for <strong>Vodafone Cash</strong>.</li>
                    <li>Receipts can be verified in the <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>" style="color: #38bdf8; text-decoration: none;">WooCommerce > Orders</a> section.</li>
                </ul>
                <div style="background: rgba(56, 189, 248, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <small style="color: #f8fafc;"><strong>Security Tip:</strong> Never share your InstaPay PIN or OTP with anyone.</small>
                </div>
            </div>

        </div>
    </div>
    <?php
}