<?php
defined( 'ABSPATH' ) || exit;

class AgmNextcloudAccountStatus
{
    private const ORDER_META_STATUS = '_agm_nextcloud_sync_status';
    private const ORDER_META_ATTEMPTS = '_agm_nextcloud_sync_attempts';
    private const ORDER_META_LAST_ERROR = '_agm_nextcloud_sync_last_error';

    public function __construct()
    {
        add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_admin_section' ], 10, 1 );
    }

    public function render_admin_section( $order = null ): void
    {
        if ( ! $order instanceof WC_Order ) {
            $order = $this->get_current_order();
        }

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $summary = $this->build_summary_from_order( $order );

        echo '<div class="order_data_column" style="clear: both; width: 100%;">';
        echo '<h3>Status do Nextcloud</h3>';
        echo $this->render_summary_box( $summary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<p class="description">Se o status estiver com falha, use a ação do pedido <strong>Retry Nextcloud sync</strong> para reenviar o provisionamento.</p>';
        echo '</div>';
    }

    private function get_current_order()
    {
        global $post;

        if ( ! $post instanceof WP_Post ) {
            return null;
        }

        $order = wc_get_order( $post->ID );
        if ( $order instanceof WC_Order ) {
            return $order;
        }

        return null;
    }

    private function build_summary_from_order( WC_Order $order ): array
    {
        $status = (string) $order->get_meta( self::ORDER_META_STATUS, true );
        $message = (string) $order->get_meta( self::ORDER_META_LAST_ERROR, true );
        $attempts = (int) $order->get_meta( self::ORDER_META_ATTEMPTS, true );
        $user = $order->get_user();
        $data = $this->get_order_identity( $order );
        $updated_at = $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0;

        if ( '' === $status ) {
            $status = $this->derive_status_from_order_state( $order );
            if ( 'success' === $status && '' === $message ) {
                $message = 'Conta criada no Nextcloud.';
            }
        }

        return [
            'status' => $status,
            'label' => $this->get_status_label( $status ),
            'message' => $message,
            'order_id' => $order->get_id(),
            'order_status' => $order->get_status(),
            'attempts' => $attempts,
            'updated_at' => $updated_at,
            'groupid' => $data['groupid'],
            'email' => $data['email'],
            'displayname' => $user instanceof WP_User ? $user->display_name : '',
        ];
    }

    private function derive_status_from_order_state( WC_Order $order ): string
    {
        $order_status = $order->get_status();

        if ( in_array( $order_status, [ 'completed' ], true ) ) {
            return 'success';
        }

        if ( in_array( $order_status, [ 'processing', 'on-hold' ], true ) ) {
            return 'pending';
        }

        if ( in_array( $order_status, [ 'failed', 'cancelled', 'refunded' ], true ) ) {
            return 'failed';
        }

        return 'unknown';
    }

    private function get_order_identity( WC_Order $order ): array
    {
        $user = $order->get_user();
        $billing_email = (string) $order->get_billing_email();
        $groupid = $user instanceof WP_User ? (string) $user->user_login : $billing_email;
        $email = $user instanceof WP_User ? (string) $user->user_email : $billing_email;

        return [
            'groupid' => $groupid,
            'email' => $email,
        ];
    }

    private function get_status_label( string $status ): string
    {
        switch ( $status ) {
            case 'success':
                return 'Criada';
            case 'pending':
                return 'Provisionando';
            case 'failed':
                return 'Falhou';
            case 'unknown':
            default:
                return 'Ainda não provisionada';
        }
    }

    private function render_summary_box( array $summary ): string
    {
        $parts = [];
        $parts[] = '<div class="notice notice-' . esc_attr( $this->get_notice_class( $summary['status'] ) ) . ' inline" style="margin: 12px 0; padding: 12px;">';
        $parts[] = '<p><strong>Status:</strong> ' . esc_html( $summary['label'] ) . '</p>';

        if ( ! empty( $summary['order_id'] ) ) {
            $parts[] = '<p><strong>Pedido:</strong> #' . esc_html( (string) $summary['order_id'] ) . '</p>';
        }

        if ( ! empty( $summary['groupid'] ) ) {
            $parts[] = '<p><strong>Usuário:</strong> ' . esc_html( $summary['groupid'] ) . '</p>';
        }

        if ( ! empty( $summary['email'] ) ) {
            $parts[] = '<p><strong>E-mail:</strong> ' . esc_html( $summary['email'] ) . '</p>';
        }

        if ( ! empty( $summary['attempts'] ) ) {
            $parts[] = '<p><strong>Tentativas:</strong> ' . esc_html( (string) $summary['attempts'] ) . '</p>';
        }

        if ( ! empty( $summary['updated_at'] ) ) {
            $parts[] = '<p><strong>Atualizado em:</strong> ' . esc_html( wp_date( 'd/m/Y H:i', (int) $summary['updated_at'] ) ) . '</p>';
        }

        $message = (string) ( $summary['message'] ?: $this->get_status_message( $summary['status'] ) );
        if ( '' !== $message ) {
            $parts[] = '<p><strong>Detalhe:</strong> ' . esc_html( $message ) . '</p>';
        }

        $parts[] = '</div>';

        return implode( '', $parts );
    }

    private function get_notice_class( string $status ): string
    {
        switch ( $status ) {
            case 'success':
                return 'success';
            case 'pending':
                return 'warning';
            case 'failed':
                return 'error';
            default:
                return 'info';
        }
    }

    private function get_status_message( string $status ): string
    {
        switch ( $status ) {
            case 'success':
                return 'Conta criada no Nextcloud.';
            case 'pending':
                return 'Provisionamento em andamento.';
            case 'failed':
                return 'O provisionamento no Nextcloud falhou.';
            default:
                return '';
        }
    }
}
