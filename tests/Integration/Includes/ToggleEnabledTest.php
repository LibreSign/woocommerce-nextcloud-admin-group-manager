<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use Agm_ToggleEnabled;
use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WP_UnitTestCase;

final class ToggleEnabledTest extends WP_UnitTestCase {

	private const ENDPOINT = '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled';

	private $nextcloud;

	private $orders;

	public function set_up() {
		parent::set_up();

		$this->nextcloud = new NextcloudServer();
		$this->nextcloud->answer_with( NextcloudServer::response( 200 ) );
		$this->orders = new OrderFactory();
	}

	private function order_of_a_customer() {
		$user = self::factory()->user->create_and_get( array( 'user_login' => 'ana' ) );

		return $this->orders->order( array( 'customer_id' => $user->ID ) );
	}

	public function test_disables_the_nextcloud_account() {
		$order = $this->order_of_a_customer();

		( new Agm_ToggleEnabled() )->disable( $order->get_id() );

		$request = $this->nextcloud->request();

		$this->assertSame( self::ENDPOINT, $request->getRequestUri() );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => '0',
			),
			$request->getParsedInput()
		);
		$this->assertSame( $this->nextcloud->expected_authorization(), $request->getHeaders()['Authorization'] );
	}

	public function test_leaves_nextcloud_alone_when_the_order_no_longer_exists() {
		( new Agm_ToggleEnabled() )->disable( 987654321 );

		$this->assertSame( array(), $this->nextcloud->paths() );
	}

	public function test_disables_the_guest_account_under_the_billing_email_it_was_created_with() {
		$order = $this->orders->order( array( 'billing' => array( 'email' => 'guest@example.org' ) ) );

		( new Agm_ToggleEnabled() )->disable( $order->get_id() );

		$this->assertSame( array( self::ENDPOINT ), $this->nextcloud->paths() );
		$this->assertSame(
			array(
				'groupid' => 'guest@example.org',
				'enabled' => '0',
			),
			$this->nextcloud->request()->getParsedInput()
		);
	}

	public function test_leaves_nextcloud_alone_when_the_order_names_nobody() {
		$order = $this->orders->order( array( 'billing' => array( 'email' => '' ) ) );

		( new Agm_ToggleEnabled() )->disable( $order->get_id() );

		$this->assertSame( array(), $this->nextcloud->paths() );
	}
}
