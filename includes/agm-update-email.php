<?php
defined( 'ABSPATH' ) || exit;

class Agm_UpdateEmail
{
    /** @var array<int, string> */
    private array $pending_passwords = [];

    /** @codeCoverageIgnore */
    public function __construct()
    {
        add_action( 'profile_update', [ $this, 'sync_nextcloud_email' ], 10, 2 );
        add_action( 'wp_set_password', [ $this, 'queue_nextcloud_password' ], 10, 3 );
        add_action( 'shutdown', [ $this, 'sync_pending_passwords' ] );
    }

    public function sync_nextcloud_email( $user_id, $old_user_data ): void
    {
        $user = get_userdata( $user_id );
        if ( ! $user || ! $old_user_data instanceof WP_User || $old_user_data->user_email === $user->user_email ) {
            return;
        }

        if ( ! $this->is_provisioned( $user ) ) {
            return;
        }

        agm_nextcloud_request(
            'POST',
            '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email',
            [
                'body' => [
                    'userId' => $user->user_login,
                    'email' => $user->user_email,
                ],
            ]
        );
    }

    public function queue_nextcloud_password( $password, $user_id, $old_user_data ): void
    {
        $password = (string) $password;
        if ( $old_user_data instanceof WP_User && wp_check_password( $password, $old_user_data->user_pass, $old_user_data->ID ) ) {
            return;
        }

        $this->pending_passwords[ (int) $user_id ] = $password;
    }

    public function sync_pending_passwords(): void
    {
        $pending = $this->pending_passwords;
        $this->pending_passwords = [];

        foreach ( $pending as $user_id => $password ) {
            $user = get_userdata( $user_id );
            if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
                continue;
            }

            if ( ! $this->is_provisioned( $user ) ) {
                continue;
            }

            agm_nextcloud_request(
                'PUT',
                '/ocs/v1.php/cloud/users/' . rawurlencode( $user->user_login ),
                [
                    'body' => [
                        'key'   => 'password',
                        'value' => $password,
                    ],
                ]
            );
        }
    }

    private function is_provisioned( WP_User $user ): bool
    {
        if ( '1' === get_user_meta( $user->ID, Agm_StatusProcessing::CUSTOMER_META_PROVISIONED, true ) ) {
            return true;
        }

        $synced_orders = array_filter(
            wc_get_orders(
                [
                    'customer_id' => $user->ID,
                    'limit'       => -1,
                ]
            ),
            static fn( WC_Order $order ): bool => Agm_StatusProcessing::SYNC_STATUS_SUCCESS === $order->get_meta( Agm_StatusProcessing::SYNC_META_STATUS, true )
        );
        if ( [] === $synced_orders ) {
            return false;
        }

        update_user_meta( $user->ID, Agm_StatusProcessing::CUSTOMER_META_PROVISIONED, '1' );

        return true;
    }
}
