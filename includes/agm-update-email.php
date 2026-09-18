<?php
defined( 'ABSPATH' ) || exit;

class Agm_UpdateEmail
{
    /** @var array<int, string> */
    private array $queued_passwords = [];

    public function __construct()
    {
        add_action( 'profile_update', [ $this, 'sync_nextcloud_email' ] );
        add_action( 'wp_set_password', [ $this, 'queue_nextcloud_password' ], 10, 3 );
        add_action( 'shutdown', [ $this, 'sync_queued_passwords' ] );
    }

    public function sync_nextcloud_email( $user_id ): void
    {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        wp_remote_post(
            get_option( 'nextcloud_api_host' ) . '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email',
            [
                'body' => [
                    'userId' => $user->user_login,
                    'email' => $user->user_email,
                ],
                'headers' => agm_nextcloud_request_headers(),
            ]
        );
    }

    public function queue_nextcloud_password( $password, $user_id, $old_user_data = null ): void
    {
        $password = (string) $password;
        if ( '' === $password ) {
            return;
        }

        if ( $old_user_data instanceof WP_User && wp_check_password( $password, $old_user_data->user_pass ) ) {
            return;
        }

        $this->queued_passwords[ (int) $user_id ] = $password;
    }

    public function sync_queued_passwords(): void
    {
        $queued = $this->queued_passwords;
        $this->queued_passwords = [];

        foreach ( $queued as $user_id => $password ) {
            $user = get_userdata( $user_id );
            if ( ! $user || ! wp_check_password( $password, $user->user_pass ) ) {
                continue;
            }

            wp_remote_request(
                $this->build_nextcloud_user_url( $user->user_login ),
                [
                    'method'  => 'PUT',
                    'body'    => [
                        'key'   => 'password',
                        'value' => $password,
                    ],
                    'headers' => agm_nextcloud_request_headers(),
                ]
            );
        }
    }

    private function build_nextcloud_user_url( string $user_login ): string
    {
        return rtrim( (string) get_option( 'nextcloud_api_host' ), '/' ) . '/ocs/v1.php/cloud/users/' . rawurlencode( $user_login );
    }
}
