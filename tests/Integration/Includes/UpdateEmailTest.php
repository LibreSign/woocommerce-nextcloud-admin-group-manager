<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use WP_UnitTestCase;

final class UpdateEmailTest extends WP_UnitTestCase {

	private const EMAIL_ENDPOINT = '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email';

	private const USER_ENDPOINT = '/ocs/v1.php/cloud/users/ana';

	private $nextcloud;

	private $user_id;

	public function set_up() {
		parent::set_up();

		$this->nextcloud = new NextcloudServer();
		$this->nextcloud->answer_with( NextcloudServer::response( 200 ) );

		$this->user_id = self::factory()->user->create(
			array(
				'user_login' => 'ana',
				'user_email' => 'ana@example.org',
				'user_pass'  => 'the-old-password',
			)
		);

		$this->nextcloud->forget();
	}

	public function tear_down() {
		unset( $_POST['pass1'], $_POST['pass2'], $_POST['password_1'], $_POST['password_2'] );

		parent::tear_down();
	}

	public function test_sends_the_new_address_when_the_profile_changes() {
		$this->update_email( 'ana@libresign.coop' );

		$request = $this->nextcloud->request();

		$this->assertSame( self::EMAIL_ENDPOINT, $request->getRequestUri() );
		$this->assertSame(
			array(
				'userId' => 'ana',
				'email'  => 'ana@libresign.coop',
			),
			$request->getParsedInput()
		);
		$this->assertSame( $this->nextcloud->expected_authorization(), $request->getHeaders()['Authorization'] );
	}

	public function test_sends_the_password_the_profile_form_posted() {
		$_POST['pass1'] = 'the-new-password';
		$_POST['pass2'] = 'the-new-password';

		$this->update_email( 'ana@libresign.coop' );

		$request = $this->nextcloud->requests()[1];

		$this->assertSame( self::USER_ENDPOINT, $request->getRequestUri() );
		$this->assertSame( 'PUT', $request->getRequestMethod() );
		$this->assertSame(
			array(
				'key'   => 'password',
				'value' => 'the-new-password',
			),
			$request->getParsedInput()
		);
	}

	public function test_sends_the_password_the_account_form_posted() {
		$_POST['password_1'] = 'the-new-password';
		$_POST['password_2'] = 'the-new-password';

		$this->update_email( 'ana@libresign.coop' );

		$this->assertSame( self::USER_ENDPOINT, $this->nextcloud->paths()[1] );
	}

	public function test_ignores_a_password_the_confirmation_does_not_match() {
		$_POST['pass1'] = 'the-new-password';
		$_POST['pass2'] = 'a-typo';

		$this->update_email( 'ana@libresign.coop' );

		$this->assertSame( array( self::EMAIL_ENDPOINT ), $this->nextcloud->paths() );
	}

	public function test_ignores_a_profile_change_that_posted_no_password() {
		$this->update_email( 'ana@libresign.coop' );

		$this->assertSame( array( self::EMAIL_ENDPOINT ), $this->nextcloud->paths() );
	}

	private function update_email( $email ) {
		wp_update_user(
			array(
				'ID'         => $this->user_id,
				'user_email' => $email,
			)
		);
	}
}
