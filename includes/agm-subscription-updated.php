<?php
defined( 'ABSPATH' ) || exit;

class AgmSubscriptionUpdated extends AgmToggleEnabled
{
    public function __construct()
    {
        add_action('woocommerce_subscription_status_changed', [$this, 'teste']);
    }

    public function teste($subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        $status = $subscription->get_status();
        // Disabled
        if (in_array($status, wcs_get_subscription_ended_statuses())) {
            $order_ids = $subscription->get_related_orders();
            foreach ($order_ids as $order_id) {
                parent::disable($order_id);
                /**
                 * @todo Check if is necessary to cancel the order after cancel the subscruption.
                 *
                 * To consider:
                 *
                 * Maybe don't will be possible cancel the entire order because have costs that can't be refundable. 
                 * Considering this will be necessary make a way to discount the termination fine before change the
                 * status of order to canceled.
                 */
            }
            return;
        }
        // Enabled
        if (in_array($status, ['active'])) {
            $order_ids = $subscription->get_related_orders();
            foreach ($order_ids as $order_id) {
                parent::enable($order_id);
                $order = wc_get_order($order_id);
                $order->set_status( 'completed', '', true );
                $order->save();
            }
        }
    }
}