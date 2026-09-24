<?php

class WC_Subscription extends WC_Order {
	/**
	 * @return int[]
	 */
	public function get_related_orders() {}
}

/**
 * @param int|WC_Subscription $the_subscription
 * @return WC_Subscription|false
 */
function wcs_get_subscription( $the_subscription ) {}

/**
 * @return string[]
 */
function wcs_get_subscription_ended_statuses() {}

class WC_Subscriptions_Plugin {
	/**
	 * @return self
	 */
	public static function instance() {}

	/**
	 * @return void
	 */
	public function activate_plugin() {}
}

/**
 * @param array<string, mixed> $args
 * @return WC_Subscription|WP_Error
 */
function wcs_create_subscription( $args = array() ) {}
