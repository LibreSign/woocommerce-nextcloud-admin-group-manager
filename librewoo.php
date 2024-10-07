<?php
/**
 * Plugin Name: Woo Order Complete Message
 * Description: Prints "Woo Completed" message when an order is completed and restricts cart to one product.
 * Version: 1.0.1
 * Author: Libre Code
 * Author URI: https://librecode.coop
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('LW_PLUGIN_DIR', plugin_dir_path(__FILE__));

include LW_PLUGIN_DIR . 'includes/librewoo-order-confirmed.php';
include LW_PLUGIN_DIR . 'includes/librewoo-add-to-cart-validation.php';

new WooOneProductCart();
new WooOrderComplete();







