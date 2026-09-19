<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WC_Order;
use WP_UnitTestCase;

final class SubscriptionUpdatedTest extends WP_UnitTestCase {

	private const ENDPOINT = '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled';

	private $nextcloud;

	private $orders;

	private $order;

	public function set_up() {
		parent::set_up();

		$this->nextcloud = new NextcloudServer();
		$this->nextcloud->answer_with( NextcloudServer::response( 200 ) );
		$this->orders = new OrderFactory();

		$user        = self::factory()->user->create_and_get( array( 'user_login' => 'ana' ) );
		$this->order = $this->orders->order( array( 'customer_id' => $user->ID ) );
	}

	private function subscription_of_the_order( $status ) {
		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $this->order->get_id(),
				'billing_period'   => 'month',
				'billing_interval' => 1,
			)
		);
		$subscription->set_status( $status );
		$subscription->save();

		$this->nextcloud->forget();

		return $subscription;
	}

	private function announce( $subscription ) {
		do_action( 'woocommerce_subscription_status_changed', $subscription->get_id(), 'active', $subscription->get_status(), $subscription );
	}

	/**
	 * @dataProvider provide_statuses_that_end_the_plan
	 */
	public function test_closes_the_account_of_every_order_behind_the_subscription( $status ) {
		$this->announce( $this->subscription_of_the_order( $status ) );

		$this->assertSame( array( self::ENDPOINT ), $this->nextcloud->paths() );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => '0',
			),
			$this->nextcloud->request()->getParsedInput()
		);
	}

	public static function provide_statuses_that_end_the_plan() {
		yield 'the customer cancelled'            => array( 'cancelled' );
		yield 'the subscription expired'          => array( 'expired' );
		yield 'the customer switched plans'       => array( 'switched' );
		yield 'the cancellation is taking effect' => array( 'pending-cancel' );
		yield 'a payment is pending'              => array( 'on-hold' );
	}

	public function test_reopens_the_account_and_completes_the_orders_when_the_plan_goes_active() {
		$this->announce( $this->subscription_of_the_order( 'active' ) );

		$this->assertSame( array( self::ENDPOINT ), $this->nextcloud->paths() );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => '1',
			),
			$this->nextcloud->request()->getParsedInput()
		);
		$this->assertSame( 'completed', wc_get_order( $this->order->get_id() )->get_status() );
	}

	public function test_ignores_a_subscription_still_waiting_for_the_first_payment() {
		$this->announce( $this->subscription_of_the_order( 'pending' ) );

		$this->assertSame( array(), $this->nextcloud->paths() );
	}

	public function test_ignores_a_subscription_that_no_longer_exists() {
		do_action( 'woocommerce_subscription_status_changed', 987654321, 'active', 'cancelled' );

		$this->assertSame( array(), $this->nextcloud->paths() );
	}
}
