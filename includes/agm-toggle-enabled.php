<?php
defined( 'ABSPATH' ) || exit;

use LibreSign\WooNextcloud\AdminGroup;

class Agm_ToggleEnabled
{
    public function disable($order_id)
    {
        $this->enable($order_id, 0);
    }

    protected function enable(int $order_id, int $enabled = 1)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $user = $order->get_user();
        $groupid = AdminGroup::id($user ? $user->user_login : null, $order->get_billing_email());
        if (!$groupid) {
            return;
        }
        wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled',
            [
                'body' => [
                    'groupid' => $groupid,
                    'enabled' => $enabled,
                ],
                'headers' => agm_nextcloud_request_headers(),
            ]
        );
    }
}
