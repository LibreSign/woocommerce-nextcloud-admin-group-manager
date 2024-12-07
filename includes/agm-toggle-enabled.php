<?php
defined( 'ABSPATH' ) || exit;

class AgmToggleEnabled
{
    public function disable($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $userId = $order->get_user()->user_login;
        $this->enable($userId, 0);
    }

    protected function enable(string $userId, int $enabled)
    {
        wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled',
            [
                'body' => [
                    'groupid' => $userId,
                    'enabled' => $enabled,
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( get_option('nextcloud_api_login') . ':' . get_option('nextcloud_api_password') )
                ]
            ]
        );
    }
}