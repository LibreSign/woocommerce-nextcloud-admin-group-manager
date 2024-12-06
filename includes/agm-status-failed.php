<?php
defined( 'ABSPATH' ) || exit;

class AgmStatusFailed extends AgmToggleEnabled
{
    public function __construct()
    {
        add_action('woocommerce_order_status_failed', [$this, 'disable']);
    }
}