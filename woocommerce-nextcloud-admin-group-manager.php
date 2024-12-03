<?php
/**
 * Woo Order Complete Messagec
 *
 * @package   wp-simple-smtp
 * @author    Vitor Mattos <vitor@php.rio>
 * @license   GPL-2.0+
 * @link      http://github.com/vitormattos
 * @copyright 2021 Vitor Mattos
 *
 * @wordpress-plugin
 * Plugin Name:       Woo Order Complete Messagec
 * Plugin URI:        https://github.com/LibreSign/WoocommerceAPITrigger
 * Description:       Woo Order Complete Messagec
 * Version:           1.0.1
 * Author:            Vitor Mattos
 * Author URI:        https://github.com/LibreSign
 * Text Domain:       wp-simple-smtp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/LibreSign/WoocommerceAPITrigger
 */

defined( 'ABSPATH' ) || exit;

include __DIR__ . '/includes/agm-order-confirmed.php';
include __DIR__ . '/includes/agm-add-to-cart-validation.php';

new AgmOrderConfirmed();
new AgmAddToCartValidation();







