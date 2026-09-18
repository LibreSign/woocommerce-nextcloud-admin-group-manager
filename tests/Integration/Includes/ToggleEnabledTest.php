<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use Agm_ToggleEnabled;
use LibreSign\WooNextcloud\Tests\Support\FakeHttp;
use LibreSign\WooNextcloud\Tests\Support\NextcloudSettings;
use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WP_UnitTestCase;

final class ToggleEnabledTest extends WP_UnitTestCase {

	use NextcloudSettings;

	private const ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled';

	private $nextcloud;

	private $orders;

	public function set_up() {
		parent::set_up();

		$this->register_nextcloud_settings();

		$this->nextcloud = new FakeHttp();
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );
		$this->orders = new OrderFactory();
	}

	private function order_of_a_customer() {
		$user = self::factory()->user->create_and_get( array( 'user_login' => 'ana' ) );

		return $this->orders->order( array( 'customer_id' => $user->ID ) );
	}

	/**
	 * @dataProvider provide_statuses_that_close_the_account
	 */
	public function test_disables_the_nextcloud_account( $hook ) {
		$order = $this->order_of_a_customer();

		do_action( $hook, $order->get_id() );

		$request = $this->nextcloud->requests()[0];

		$this->assertSame( self::ENDPOINT, $request['url'] );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => 0,
			),
			$request['args']['body']
		);
		$this->assertSame( $this->expected_authorization(), $request['args']['headers']['Authorization'] );
	}

	public static function provide_statuses_that_close_the_account() {
		yield 'the customer cancelled the order' => array( 'woocommerce_order_status_cancelled' );
		yield 'the payment failed'               => array( 'woocommerce_order_status_failed' );
	}

	public function test_leaves_nextcloud_alone_when_the_order_no_longer_exists() {
		( new Agm_ToggleEnabled() )->disable( 987654321 );

		$this->assertSame( array(), $this->nextcloud->urls() );
	}

	public function test_leaves_nextcloud_alone_when_the_order_has_no_customer() {
		do_action( 'woocommerce_order_status_cancelled', $this->orders->order()->get_id() );

		$this->assertSame( array(), $this->nextcloud->urls() );
	}
}
