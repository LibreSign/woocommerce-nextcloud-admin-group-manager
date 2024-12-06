<?php
defined( 'ABSPATH' ) || exit;

class AgmStatusCanceled extends AgmToggleEnabled
{
    public function __construct()
    {
        add_action('woocommerce_order_status_cancelled', [$this, 'disable']);
    }
}