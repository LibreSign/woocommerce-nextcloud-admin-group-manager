<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WP_UnitTestCase;

final class StatusFailedTest extends WP_UnitTestCase {

	public function test_disables_the_nextcloud_account_when_the_payment_fails() {
		$nextcloud = new NextcloudServer();
		$nextcloud->answer_with( NextcloudServer::response( 200 ) );
		$user  = self::factory()->user->create_and_get( array( 'user_login' => 'ana' ) );
		$order = ( new OrderFactory() )->order( array( 'customer_id' => $user->ID ) );
		$nextcloud->forget();

		$order->update_status( 'failed' );

		$this->assertSame( array( '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled' ), $nextcloud->paths() );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => '0',
			),
			$nextcloud->request()->getParsedInput()
		);
	}
}
