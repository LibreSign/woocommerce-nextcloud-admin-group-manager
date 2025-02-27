<?php
/**
 * WooCommerce Nextcloud Admin Group Manager
 *
 * @package   wp-simple-smtp
 * @author    LibreCode <contact@librecode.coop>
 * @license   GPL-2.0+
 * @link      http://github.com/vitormattos
 * @copyright 2021 LibreCode
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Nextcloud Admin Group Manager
 * Plugin URI:        https://github.com/LibreSign/woocommerce-nextcloud-admin-group-manager
 * Description:       Integrate WooCommerce with LibreSign SaaS using the Nextcloud app Admin Group Manager
 * Version:           1.0.1
 * Author:            LibreCode
 * Author URI:        https://github.com/LibreSign
 * Text Domain:       wp-simple-smtp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/LibreSign/woocommerce-nextcloud-admin-group-manager
 */

defined( 'ABSPATH' ) || exit;

include __DIR__ . '/includes/agm-toggle-enabled.php';
include __DIR__ . '/includes/agm-status-canceled.php';
include __DIR__ . '/includes/agm-status-failed.php';
include __DIR__ . '/includes/agm-status-processing.php';
include __DIR__ . '/includes/agm-subscription-updated.php';
include __DIR__ . '/includes/agm-add-to-cart-validation.php';
include __DIR__ . '/includes/agm-user-id-equal-to-email.php';
include __DIR__ . '/includes/agm-update-email.php';
include __DIR__ . '/includes/agm-settings.php';

new AgmStatusCanceled();
new AgmStatusFailed();
new AgmStatusProcessing();
new AgmSubscriptionUpdated();
new AgmAddToCartValidation();
new AgmUserIdEqualToEmail();
new AgmUpdateEmail();
