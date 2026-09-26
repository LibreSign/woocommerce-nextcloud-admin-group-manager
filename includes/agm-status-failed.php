<?php
defined( 'ABSPATH' ) || exit;

class Agm_StatusFailed extends Agm_ToggleEnabled
{
    /** @codeCoverageIgnore */
    public function __construct()
    {
        add_action('woocommerce_order_status_failed', [$this, 'disable']);
    }
}