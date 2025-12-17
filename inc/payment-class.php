<?php

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
                    'image' => plugin_dir_url(__FILE__) . '../assets/imgs/vodafone.png',
                    'note'  => 'Send payment to: ',
                ),
                'InstaPay' => array(
                    'label' => 'InstaPay',
                    'value' => $instapay,
                    'image' => plugin_dir_url(__FILE__) . '../assets/imgs/instapay.png',
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