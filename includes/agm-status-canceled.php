<?php
defined( 'ABSPATH' ) || exit;

class Agm_StatusCanceled extends Agm_ToggleEnabled
{
    public function __construct()
    {
        add_action('woocommerce_order_status_cancelled', [$this, 'disable']);
    }
}