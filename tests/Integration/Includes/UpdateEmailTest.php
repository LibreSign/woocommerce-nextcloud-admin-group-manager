<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\FakeHttp;
use LibreSign\WooNextcloud\Tests\Support\NextcloudSettings;
use WP_UnitTestCase;

final class UpdateEmailTest extends WP_UnitTestCase {

	use NextcloudSettings;

	private const EMAIL_ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email';

	private const USER_ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v1.php/cloud/users/ana';

	private $nextcloud;

	private $user_id;

	public function set_up() {
		parent::set_up();

		$this->register_nextcloud_settings();

		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );

		$this->nextcloud = new FakeHttp();
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$this->user_id = self::factory()->user->create(
			array(
				'user_login' => 'ana',
				'user_email' => 'ana@example.org',
				'user_pass'  => 'the-old-password',
			)
		);

		$this->nextcloud->forget();
	}

	public function test_sends_the_new_address_when_the_profile_changes() {
		wp_update_user(
			array(
				'ID'         => $this->user_id,
				'user_email' => 'ana@libresign.coop',
			)
		);

		$request = $this->nextcloud->requests()[0];

		$this->assertSame( self::EMAIL_ENDPOINT, $request['url'] );
		$this->assertSame(
			array(
				'userId' => 'ana',
				'email'  => 'ana@libresign.coop',
			),
			$request['args']['body']
		);
		$this->assertSame( $this->expected_authorization(), $request['args']['headers']['Authorization'] );
	}

	public function test_sends_the_new_password_once_wordpress_stored_it() {
		wp_set_password( 'the-new-password', $this->user_id );

		$this->assertSame( array(), $this->nextcloud->urls() );

		do_action( 'shutdown' );

		$request = $this->nextcloud->requests()[0];

		$this->assertSame( self::USER_ENDPOINT, $request['url'] );
		$this->assertSame( 'PUT', $request['args']['method'] );
		$this->assertSame(
			array(
				'key'   => 'password',
				'value' => 'the-new-password',
			),
			$request['args']['body']
		);
	}

	public function test_ignores_a_password_that_did_not_change() {
		wp_set_password( 'the-old-password', $this->user_id );

		do_action( 'shutdown' );

		$this->assertSame( array(), $this->nextcloud->urls() );
	}

	public function test_ignores_the_password_of_a_user_being_created() {
		self::factory()->user->create( array( 'user_login' => 'bruno' ) );

		do_action( 'shutdown' );

		$this->assertSame( array(), $this->nextcloud->urls() );
	}
}
