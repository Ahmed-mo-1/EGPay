<?php 
/*
Plugin Name: EGPay
Version: 0.5
Description: Fixed EGPay gateway with working upload form and status checks.
Author: EGPay
*/

if (!defined('ABSPATH')) exit;

$plugin_dir = plugin_dir_path( __FILE__ );

require_once $plugin_dir . 'inc/payment-class.php';
require_once $plugin_dir . 'inc/order-status.php';
require_once $plugin_dir . 'inc/form-functions.php';
require_once $plugin_dir . 'inc/shortcode.php';
require_once $plugin_dir . 'inc/ajax-upload.php';
require_once $plugin_dir . 'inc/ajax-handler.php';
require_once $plugin_dir . 'inc/upload-receipt.php';
require_once $plugin_dir . 'inc/admin-order-table.php';
require_once $plugin_dir . 'inc/selected-option.php';
require_once $plugin_dir . 'inc/admin-menu.php';
require_once $plugin_dir . 'inc/floating-icon.php';
