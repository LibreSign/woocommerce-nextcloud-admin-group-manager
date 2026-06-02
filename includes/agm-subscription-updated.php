<?php
defined( 'ABSPATH' ) || exit;

class AgmSubscriptionUpdated extends AgmToggleEnabled
{
    public function __construct()
    {
        add_action('woocommerce_subscription_status_changed', [$this, 'teste']);
    }

    public function teste($subscription_id)
    {
        $subscription = wcs_get_subscription($subscription_id);
        if (!$subscription) {
            return;
        }

        $status = $subscription->get_status();

        if ($this->should_disable($status)) {
            $this->disable_related_orders($subscription);
            return;
        }

        if ('active' === $status) {
            $this->enable_related_orders($subscription);
        }
    }

    private function should_disable(string $status): bool
    {
        return in_array($status, array_merge(wcs_get_subscription_ended_statuses(), ['on-hold']), true);
    }

    private function disable_related_orders($subscription): void
    {
        foreach ($subscription->get_related_orders() as $order_id) {
            parent::disable($order_id);
        }
    }

    private function enable_related_orders($subscription): void
    {
        foreach ($subscription->get_related_orders() as $order_id) {
            parent::enable($order_id);
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            $order->set_status( 'completed', '', true );
            $order->save();
        }
    }
}
