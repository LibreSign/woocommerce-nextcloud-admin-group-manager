<?php
defined( 'ABSPATH' ) || exit;

class AgmUpdateEmail
{
    public function __construct()
    {
        add_action( 'profile_update', [ $this, 'sync_account_details' ], 10, 3 );
    }

    public function sync_account_details( $user_id )
    {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $this->sync_nextcloud_email( $user );
        $this->sync_nextcloud_password( $user );
    }

    private function sync_nextcloud_email( WP_User $user ): void
    {
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

    private function sync_nextcloud_password( WP_User $user ): void
    {
        $password = $this->get_password_from_request();
        if ( '' === $password ) {
            return;
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

    private function get_password_from_request(): string
    {
        $password_1 = '';
        $password_2 = '';

        if ( isset( $_POST['pass1'] ) || isset( $_POST['pass2'] ) ) {
            $password_1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
            $password_2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';
        } elseif ( isset( $_POST['password_1'] ) || isset( $_POST['password_2'] ) ) {
            $password_1 = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
            $password_2 = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';
        }

        if ( '' === $password_1 || $password_1 !== $password_2 ) {
            return '';
        }

        return $password_1;
    }

    private function build_nextcloud_user_url( string $user_login ): string
    {
        return rtrim( (string) get_option( 'nextcloud_api_host' ), '/' ) . '/ocs/v1.php/cloud/users/' . rawurlencode( $user_login );
    }
}
