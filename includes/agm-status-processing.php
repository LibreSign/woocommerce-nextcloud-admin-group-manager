<?php
defined( 'ABSPATH' ) || exit;

use LibreSign\WooNextcloud\AdminGroup;
use LibreSign\WooNextcloud\NextcloudResponse;
use LibreSign\WooNextcloud\RetryPolicy;

class Agm_StatusProcessing
{
    private const SYNC_META_STATUS = '_agm_nextcloud_sync_status';
    private const SYNC_META_ATTEMPTS = '_agm_nextcloud_sync_attempts';
    private const SYNC_META_LAST_ERROR = '_agm_nextcloud_sync_last_error';
    private const SYNC_STATUS_PENDING = 'pending';
    private const SYNC_STATUS_SUCCESS = 'success';
    private const SYNC_STATUS_FAILED = 'failed';
    private const RETRY_HOOK = 'agm_retry_nextcloud_sync';
    private const RETRY_GROUP = 'nextcloud-admin-group-manager';

    /** @codeCoverageIgnore */
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
            $payload = $this->get_order_data($order);
        } catch (RuntimeException $exception) {
            $this->mark_sync_failure($order, $exception->getMessage(), $manual, false);
            $this->log('Unable to build Nextcloud payload for disable sync', [
                'order_id' => $order_id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $this->set_sync_status($order, self::SYNC_STATUS_PENDING);

        $this->log('Disabling Nextcloud account because order is not active', [
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
            'groupid' => $payload['groupid'],
        ]);

        (new Agm_ToggleEnabled())->disable($order_id);

        $this->mark_sync_success($order, $manual);
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
            $payload = $this->get_order_data($order);
        } catch (RuntimeException $exception) {
            $this->mark_sync_failure($order, $exception->getMessage(), $manual, false);
            $this->log('Unable to build Nextcloud payload', [
                'order_id' => $order_id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $attempt = $this->increment_attempts($order);
        $this->set_sync_status($order, self::SYNC_STATUS_PENDING);

        $response = agm_nextcloud_request(
            'POST',
            '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group',
            [
                'body' => $payload,
                'timeout' => 15,
            ]
        );

        $this->log_response($order_id, $payload, $response, $attempt);

        if ($response->succeeded()) {
            $this->mark_sync_success($order, $manual);
            $order->set_status( 'completed', '', true );
            $order->save();
            return;
        }

        $message = $response->failure_message();
        $retry_at = RetryPolicy::next_retry_at($attempt, time());
        $this->mark_sync_failure($order, $message, $manual, null !== $retry_at);
        if (null !== $retry_at) {
            $this->schedule_retry($order_id, $retry_at);
        } else {
            $this->clear_retry_schedule($order_id);
        }
    }

    private function get_order_data(WC_Order $order): array
    {
        $items = $order->get_items();
        $item = current($items);
        if (!$item instanceof WC_Order_Item_Product) {
            throw new RuntimeException('Order has no product items');
        }

        $product = wc_get_product($item->get_product_id());
        if (!$product) {
            throw new RuntimeException('Order item product not found');
        }

        $user = $order->get_user();

        return AdminGroup::payload(
            $user ? $user->user_login : null,
            $user ? $user->user_email : null,
            $order->get_billing_email(),
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            array_map(
                static fn(WC_Product_Attribute $attribute): array => $attribute->get_options(),
                $product->get_attributes()
            )
        );
    }

    private function log_response($order_id, array $payload, NextcloudResponse $response, int $attempt): void
    {
        $context = [
            'order_id' => $order_id,
            'payload' => $payload,
            'attempt' => $attempt,
        ];
        if ('' !== $response->error) {
            $context['error'] = $response->error;
            $this->log('Nextcloud request failed', $context);
            return;
        }
        $context['response_code'] = $response->status;
        $context['response_body'] = $response->body;
        $this->log('Nextcloud request completed', $context);
    }

    private function increment_attempts(WC_Order $order): int
    {
        $attempts = (int)$order->get_meta(self::SYNC_META_ATTEMPTS, true);
        $attempts++;
        $order->update_meta_data(self::SYNC_META_ATTEMPTS, (string) $attempts);
        $order->save();
        return $attempts;
    }

    private function set_sync_status(WC_Order $order, string $status, string $message = ''): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, $status);
        if ('' !== $message) {
            $order->update_meta_data(self::SYNC_META_LAST_ERROR, $message);
        } elseif ('success' === $status) {
            $order->delete_meta_data(self::SYNC_META_LAST_ERROR);
        }
        $order->save();
    }

    private function get_sync_status(WC_Order $order): string
    {
        return (string)$order->get_meta(self::SYNC_META_STATUS, true);
    }

    private function mark_sync_success(WC_Order $order, bool $manual): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, self::SYNC_STATUS_SUCCESS);
        $order->delete_meta_data(self::SYNC_META_LAST_ERROR);
        $order->save();
        $this->clear_retry_schedule($order->get_id());
        $order->add_order_note(
            $manual
                ? 'Nextcloud sync completed successfully after manual retry.'
                : 'Nextcloud sync completed successfully.'
        );
    }

    private function mark_sync_failure(WC_Order $order, string $message, bool $manual, bool $scheduled_retry): void
    {
        $order->update_meta_data(self::SYNC_META_STATUS, self::SYNC_STATUS_FAILED);
        $order->update_meta_data(self::SYNC_META_LAST_ERROR, $message);
        $order->save();

        $note = $manual
            ? 'Manual Nextcloud sync failed: '
            : 'Nextcloud sync failed: ';
        $note .= $message;
        if ($scheduled_retry) {
            $note .= ' A retry was scheduled automatically.';
        }
        $order->add_order_note($note);
    }

    private function schedule_retry(int $order_id, int $timestamp): void
    {
        $this->clear_retry_schedule($order_id);
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

    private function log(string $message, array $context = []): void
    {
        wc_get_logger()->info($message . ' ' . wp_json_encode($context), ['source' => 'nextcloud-admin-group-manager']);
    }
}
