<?php
defined( 'ABSPATH' ) || exit;

class AgmUpdateEmail
{
    public function __construct()
    {
        add_action('woocommerce_save_account_details', [$this, 'new_customer_username']);
    }

    function new_customer_username($userId)
    {
        $user = get_userdata($userId);
        wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email',
            [
                'body' => [
                    'userId' => $user->user_login,
                    'email' => $user->user_email,
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( get_option('nextcloud_api_login') . ':' . get_option('nextcloud_api_password') )
                ]
            ]
        );
    }
}