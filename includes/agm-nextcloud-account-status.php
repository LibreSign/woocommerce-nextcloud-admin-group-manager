<?php
defined( 'ABSPATH' ) || exit;

function agm_register_nextcloud_status_endpoint(): void {
    add_rewrite_endpoint( AgmNextcloudAccountStatus::ACCOUNT_ENDPOINT, EP_ROOT | EP_PAGES );
}

class AgmNextcloudAccountStatus
{
    public const ACCOUNT_ENDPOINT = 'nextcloud-status';

    private const ORDER_META_STATUS = '_agm_nextcloud_sync_status';
    private const ORDER_META_ATTEMPTS = '_agm_nextcloud_sync_attempts';
    private const ORDER_META_LAST_ERROR = '_agm_nextcloud_sync_last_error';

    private const USER_META_STATUS = '_agm_nextcloud_account_status';
    private const USER_META_MESSAGE = '_agm_nextcloud_account_last_message';
    private const USER_META_ORDER_ID = '_agm_nextcloud_account_last_order_id';
    private const USER_META_UPDATED_AT = '_agm_nextcloud_account_updated_at';
    private const USER_META_GROUP_ID = '_agm_nextcloud_account_groupid';
    private const USER_META_EMAIL = '_agm_nextcloud_account_email';

    public function __construct()
    {
        add_action( 'init', 'agm_register_nextcloud_status_endpoint' );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_account_menu_item' ] );
        add_action( 'woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', [ $this, 'render_account_endpoint' ] );
        add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_admin_section' ], 10, 1 );
    }

    public function add_account_menu_item( array $items ): array
    {
        if ( ! is_user_logged_in() ) {
            return $items;
        }

        $menu_items = [];
        $inserted = false;

        foreach ( $items as $key => $label ) {
            $menu_items[ $key ] = $label;
            if ( 'dashboard' === $key ) {
                $menu_items[ self::ACCOUNT_ENDPOINT ] = 'Status do Nextcloud';
                $inserted = true;
            }
        }

        if ( ! $inserted ) {
            $menu_items[ self::ACCOUNT_ENDPOINT ] = 'Status do Nextcloud';
        }

        return $menu_items;
    }

    public function render_account_endpoint(): void
    {
        if ( ! is_user_logged_in() ) {
            echo '<p>Faça login para ver o status da sua conta no Nextcloud.</p>';
            return;
        }

        $summary = $this->get_current_user_summary( get_current_user_id() );

        echo '<div class="woocommerce-MyAccount-content">';
        echo '<h2>Status do Nextcloud</h2>';
        echo $this->render_summary_box( $summary, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
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
        echo $this->render_summary_box( $summary, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    private function get_current_user_summary( int $user_id ): array
    {
        $summary = $this->build_empty_summary();

        foreach ( [
            self::USER_META_STATUS,
            self::USER_META_MESSAGE,
            self::USER_META_ORDER_ID,
            self::USER_META_UPDATED_AT,
            self::USER_META_GROUP_ID,
            self::USER_META_EMAIL,
        ] as $key ) {
            $summary['user_meta'][ $key ] = get_user_meta( $user_id, $key, true );
        }

        if ( '' !== (string) $summary['user_meta'][ self::USER_META_STATUS ] || (int) $summary['user_meta'][ self::USER_META_ORDER_ID ] > 0 ) {
            $summary['status'] = (string) $summary['user_meta'][ self::USER_META_STATUS ];
            $summary['message'] = (string) $summary['user_meta'][ self::USER_META_MESSAGE ];
            $summary['order_id'] = (int) $summary['user_meta'][ self::USER_META_ORDER_ID ];
            $summary['updated_at'] = (int) $summary['user_meta'][ self::USER_META_UPDATED_AT ];
            $summary['groupid'] = (string) $summary['user_meta'][ self::USER_META_GROUP_ID ];
            $summary['email'] = (string) $summary['user_meta'][ self::USER_META_EMAIL ];

            return $summary;
        }

        $order = $this->find_latest_order_with_sync_data( $user_id );
        if ( $order instanceof WC_Order ) {
            return $this->build_summary_from_order( $order );
        }

        return $summary;
    }

    private function find_latest_order_with_sync_data( int $user_id )
    {
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'limit'       => 20,
            'return'      => 'objects',
        ] );

        foreach ( $orders as $order ) {
            if ( $order instanceof WC_Order ) {
                if ( $this->order_has_nextcloud_context( $order ) ) {
                    return $order;
                }
            }
        }

        return null;
    }

    private function order_has_nextcloud_context( WC_Order $order ): bool
    {
        if ( '' !== (string) $order->get_meta( self::ORDER_META_STATUS, true ) ) {
            return true;
        }

        if ( (int) $order->get_meta( self::ORDER_META_ATTEMPTS, true ) > 0 ) {
            return true;
        }

        if ( '' !== (string) $order->get_meta( self::ORDER_META_LAST_ERROR, true ) ) {
            return true;
        }

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product = wc_get_product( $item->get_product_id() );
            if ( ! $product ) {
                continue;
            }

            foreach ( $product->get_attributes() as $name => $attribute ) {
                if ( preg_match( '/^nextcloud-(string|list)-.+/', (string) $name ) ) {
                    return true;
                }
            }
        }

        return false;
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
            'user_meta' => [],
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

    private function render_summary_box( array $summary, bool $is_account_page ): string
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

        if ( 'failed' === $summary['status'] && $is_account_page ) {
            $parts[] = '<p>Se a conta ainda não foi criada, entre em contato com o suporte ou aguarde uma nova tentativa de sincronização.</p>';
        }

        if ( 'unknown' === $summary['status'] && $is_account_page ) {
            $parts[] = '<p>Ainda não encontramos um pedido com provisionamento no Nextcloud para esta conta.</p>';
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

    private function build_empty_summary(): array
    {
        return [
            'status' => 'unknown',
            'label' => $this->get_status_label( 'unknown' ),
            'message' => '',
            'order_id' => 0,
            'order_status' => '',
            'attempts' => 0,
            'updated_at' => 0,
            'groupid' => '',
            'email' => '',
            'displayname' => '',
            'user_meta' => [],
        ];
    }
}
