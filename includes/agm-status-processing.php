<?php
defined( 'ABSPATH' ) || exit;

class AgmStatusProcessing
{
    private const SYNC_META_STATUS = '_agm_nextcloud_sync_status';
    private const SYNC_META_ATTEMPTS = '_agm_nextcloud_sync_attempts';
    private const SYNC_META_LAST_ERROR = '_agm_nextcloud_sync_last_error';
    private const SYNC_STATUS_PENDING = 'pending';
    private const SYNC_STATUS_SUCCESS = 'success';
    private const SYNC_STATUS_FAILED = 'failed';
    private const RETRY_HOOK = 'agm_retry_nextcloud_sync';
    private const RETRY_GROUP = 'nextcloud-admin-group-manager';
    private const MAX_ATTEMPTS = 5;

    public function __construct()
    {
        add_action('woocommerce_order_status_processing', [$this, 'order_complete_message']);
        add_action(self::RETRY_HOOK, [$this, 'retry_sync']);
        add_filter('woocommerce_order_actions', [$this, 'register_order_action']);
        add_action('woocommerce_order_action_agm_retry_nextcloud_sync', [$this, 'manual_retry']);
    }

    public function order_complete_message($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log('Order not found', ['order_id' => $order_id]);
            return;
        }

        if ($this->get_sync_status($order) === self::SYNC_STATUS_SUCCESS) {
            $this->log('Skipping Nextcloud sync because order is already synced', ['order_id' => $order_id]);
            return;
        }

        $this->sync_order($order);
    }

    public function retry_sync($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log('Retry skipped because order was not found', ['order_id' => $order_id]);
            return;
        }

        $this->sync_current_state($order);
    }

    public function manual_retry($order)
    {
        if (!$order instanceof WC_Order) {
            return;
        }

        $this->clear_retry_schedule($order->get_id());
        $this->sync_current_state($order, true);
    }

    public function register_order_action($actions)
    {
        $actions['agm_retry_nextcloud_sync'] = 'Retry Nextcloud sync';
        return $actions;
    }

    private function sync_current_state(WC_Order $order, bool $manual = false): void
    {
        if ($this->should_disable_for_order($order)) {
            $this->sync_disable($order, $manual);
            return;
        }

        $this->sync_order($order, $manual);
    }

    private function should_disable_for_order(WC_Order $order): bool
    {
        return in_array($order->get_status(), ['on-hold', 'cancelled', 'failed', 'refunded'], true);
    }

    private function sync_disable(WC_Order $order, bool $manual = false): void
    {
        $order_id = $order->get_id();

        try {
            $data = $this->get_order_data($order);
        } catch (RuntimeException $exception) {
            $this->mark_sync_failure($order, $exception->getMessage(), $manual, false);
            $this->log('Unable to build Nextcloud payload for disable sync', [
                'order_id' => $order_id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $this->set_sync_status($order, self::SYNC_STATUS_PENDING, '', $data);

        $this->log('Disabling Nextcloud account because order is not active', [
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
            'groupid' => $data->groupid ?? null,
        ]);

        (new AgmToggleEnabled())->disable($order_id);

        $this->mark_sync_success($order, $manual, $data);
        $order->add_order_note(
            $manual
                ? 'Nextcloud account disabled successfully after manual retry.'
                : 'Nextcloud account disabled because the order is no longer active.'
        );
    }

    private function sync_order(WC_Order $order, bool $manual = false): void
    {
        $order_id = $order->get_id();

        try {
            $data = $this->get_order_data($order);
        } catch (RuntimeException $exception) {
            $this->mark_sync_failure($order, $exception->getMessage(), $manual, false);
            $this->log('Unable to build Nextcloud payload', [
                'order_id' => $order_id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $payload = get_object_vars($data);
        $attempt = $this->increment_attempts($order);
        $this->set_sync_status($order, self::SYNC_STATUS_PENDING, '', $data);

        $return = wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group',
            [
                'body' => $payload,
                'headers' => agm_nextcloud_request_headers(),
                'timeout' => 15,
            ]
        );

        $this->log_response($order_id, $payload, $return, $attempt);

        if ($this->request_succeeded($return)) {
            $this->mark_sync_success($order, $manual, $data);
            $order->set_status( 'completed', '', true );
            $order->save();
            return;
        }

        $message = $this->build_failure_message($return);
        $schedule_retry = $attempt < self::MAX_ATTEMPTS;
        $this->mark_sync_failure($order, $message, $manual, $schedule_retry, $data);
        if ($schedule_retry) {
            $this->schedule_retry($order_id, $attempt);
        } else {
            $this->clear_retry_schedule($order_id);
        }
    }

    /**
     * Get order data from WooCommerce order object
     * Data: customer name, customer email, purchased items
     * 
     * @param WC_Order $order
     * @return stdClass
     * @since 1.0.0
     */
    private function get_order_data($order): stdClass
    {
        $items = $order->get_items();
        $item = current($items);
        if (!$item) {
            throw new RuntimeException('Order has no items');
        }

        $product = wc_get_product($item->get_product_id());
        if (!$product) {
            throw new RuntimeException('Order item product not found');
        }

        $attributes = $product->get_attributes();
        $user = $order->get_user();

        $data = new stdClass();
        $data->groupid = $user ? $user->user_login : $order->get_billing_email();
        $data->email = $user ? $user->user_email : $order->get_billing_email();
        $data->displayname = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (!$data->groupid) {
            throw new RuntimeException('Missing group identifier');
        }
        if (!$data->email) {
            throw new RuntimeException('Missing email');
        }
        foreach ($attributes as $name => $attribute) {
            preg_match('/^nextcloud-(?<type>string|list)-(?<name>.+)/', $name, $matches);
            if (!$matches) {
                continue;
            }
            $options = $attribute->get_options();
            switch ($matches['type']) {
                case 'string':
                    $data->{$matches['name']} = current($options);
                    break;
                case 'list':
                    $data->{$matches['name']} = $options;
                    break;
            }
        }
        return $data;
    }

    private function request_succeeded($response): bool
    {
        return is_array($response) && isset($response['response']['code']) && (int)$response['response']['code'] === 200;
    }

    private function build_failure_message($response): string
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $status_code = (int)($response['response']['code'] ?? 0);
        $body = (string)($response['body'] ?? '');

        if ($status_code > 0) {
            return sprintf('HTTP %d: %s', $status_code, wp_strip_all_tags($body));
        }

        return 'Unknown error while calling Nextcloud API.';
    }

    private function log_response($order_id, array $payload, $response, int $attempt): void
    {
        $context = [
            'order_id' => $order_id,
            'payload' => $payload,
            'attempt' => $attempt,
        ];
        if (is_wp_error($response)) {
            $context['error'] = $response->get_error_message();
            $this->log('Nextcloud request failed', $context);
            return;
        }
        $context['response_code'] = $response['response']['code'] ?? null;
        $context['response_body'] = $response['body'] ?? null;
        $this->log('Nextcloud request completed', $context);
    }

    private function increment_attempts(WC_Order $order): int
    {
        $attempts = (int)$order->get_meta(self::SYNC_META_ATTEMPTS, true);
        $attempts++;
        $order->update_meta_data(self::SYNC_META_ATTEMPTS, $attempts);
        $order->save();
        return $attempts;
    }

    private function set_sync_status(WC_Order $order, string $status, string $message = '', ?stdClass $data = null): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, $status);
        if ('' !== $message) {
            $order->update_meta_data(self::SYNC_META_LAST_ERROR, $message);
        } elseif ('success' === $status) {
            $order->delete_meta_data(self::SYNC_META_LAST_ERROR);
        }
        $order->save();
        $this->update_customer_sync_summary($order, $status, $message, $data);
    }

    private function get_sync_status(WC_Order $order): string
    {
        return (string)$order->get_meta(self::SYNC_META_STATUS, true);
    }

    private function mark_sync_success(WC_Order $order, bool $manual, ?stdClass $data = null): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, self::SYNC_STATUS_SUCCESS);
        $order->delete_meta_data(self::SYNC_META_LAST_ERROR);
        $order->save();
        $this->update_customer_sync_summary($order, self::SYNC_STATUS_SUCCESS, '', $data);
        $this->clear_retry_schedule($order->get_id());
        $order->add_order_note(
            $manual
                ? 'Nextcloud sync completed successfully after manual retry.'
                : 'Nextcloud sync completed successfully.'
        );
    }

    private function mark_sync_failure(WC_Order $order, string $message, bool $manual, bool $scheduled_retry, ?stdClass $data = null): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, self::SYNC_STATUS_FAILED);
        $order->update_meta_data(self::SYNC_META_LAST_ERROR, $message);
        $order->save();
        $this->update_customer_sync_summary($order, self::SYNC_STATUS_FAILED, $message, $data);

        $note = $manual
            ? 'Manual Nextcloud sync failed: '
            : 'Nextcloud sync failed: ';
        $note .= $message;
        if ($scheduled_retry) {
            $note .= ' A retry was scheduled automatically.';
        }
        $order->add_order_note($note);
    }

    private function update_customer_sync_summary(WC_Order $order, string $status, string $message = '', ?stdClass $data = null): void
    {
        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) {
            return;
        }

        $identity = $data ? $data : $this->get_order_data_for_user_meta( $order );

        if ( $identity ) {
            update_user_meta( $user_id, '_agm_nextcloud_account_groupid', (string) ( $identity->groupid ?? '' ) );
            update_user_meta( $user_id, '_agm_nextcloud_account_email', (string) ( $identity->email ?? '' ) );
        }

        update_user_meta( $user_id, '_agm_nextcloud_account_status', $status );
        update_user_meta( $user_id, '_agm_nextcloud_account_last_message', $message );
        update_user_meta( $user_id, '_agm_nextcloud_account_last_order_id', $order->get_id() );
        update_user_meta( $user_id, '_agm_nextcloud_account_updated_at', time() );
    }

    private function get_order_data_for_user_meta( WC_Order $order ): ?stdClass
    {
        try {
            return $this->get_order_data( $order );
        } catch ( RuntimeException $exception ) {
            return null;
        }
    }

    private function schedule_retry(int $order_id, int $attempt): void
    {
        $this->clear_retry_schedule($order_id);
        $timestamp = time() + $this->get_retry_delay($attempt);
        $args = ['order_id' => $order_id];

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, self::RETRY_HOOK, $args, self::RETRY_GROUP);
            return;
        }

        wp_schedule_single_event($timestamp, self::RETRY_HOOK, [$order_id]);
    }

    private function clear_retry_schedule(int $order_id): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::RETRY_HOOK, ['order_id' => $order_id], self::RETRY_GROUP);
        }

        wp_clear_scheduled_hook(self::RETRY_HOOK, [$order_id]);
    }

    private function get_retry_delay(int $attempt): int
    {
        $delays = [
            1 => 5 * MINUTE_IN_SECONDS,
            2 => 15 * MINUTE_IN_SECONDS,
            3 => HOUR_IN_SECONDS,
            4 => 3 * HOUR_IN_SECONDS,
            5 => 6 * HOUR_IN_SECONDS,
        ];

        return $delays[$attempt] ?? (6 * HOUR_IN_SECONDS);
    }

    private function log(string $message, array $context = []): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info($message . ' ' . wp_json_encode($context), ['source' => 'nextcloud-admin-group-manager']);
            return;
        }
        error_log('[nextcloud-admin-group-manager] ' . $message . ' ' . wp_json_encode($context));
    }
}
