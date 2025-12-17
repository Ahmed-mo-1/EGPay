<?php

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