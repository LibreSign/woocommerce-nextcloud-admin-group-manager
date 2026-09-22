<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use PasswordHash;
use RuntimeException;
use WC_Form_Handler;
use WP_UnitTestCase;

final class UpdateEmailTest extends WP_UnitTestCase {

	private const EMAIL_ENDPOINT = '/ocs/v2.php/apps/admin_group_manager/api/v1/change-admin-email';

	private const USER_ENDPOINT = '/ocs/v1.php/cloud/users/ana';

	private $nextcloud;

	private $user_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-includes/class-phpass.php';
	}

	public function set_up() {
		parent::set_up();

		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );

		$this->nextcloud = new NextcloudServer();
		$this->nextcloud->answer_with( NextcloudServer::response( 200 ) );

		$this->user_id = self::factory()->user->create(
			array(
				'user_login'   => 'ana',
				'user_email'   => 'ana@example.org',
				'user_pass'    => 'the-old-password',
				'first_name'   => 'Ana',
				'last_name'    => 'Lima',
				'display_name' => 'Ana Lima',
			)
		);
		wp_set_current_user( $this->user_id );

		$this->finish_request();
		$this->nextcloud->forget();
	}

	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	public function test_sends_the_new_address_when_the_profile_changes() {
		wp_update_user(
			array(
				'ID'         => $this->user_id,
				'user_email' => 'ana@libresign.coop',
			)
		);

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

	public function test_sends_the_password_changed_on_the_wordpress_profile() {
		$this->submit_wordpress_profile( 'the-new-password', 'the-new-password' );
		$this->finish_request();

		$this->assert_sent_password( 'the-new-password' );
	}

	public function test_sends_the_password_changed_on_the_woocommerce_account_page() {
		$this->submit_woocommerce_account( 'the-old-password', 'the-new-password' );
		$this->finish_request();

		$this->assert_sent_password( 'the-new-password' );
	}

	public function test_sends_the_password_reset_through_wordpress() {
		reset_password( get_userdata( $this->user_id ), 'the-new-password' );
		$this->finish_request();

		$this->assert_sent_password( 'the-new-password' );
	}

	public function test_ignores_password_fields_left_in_a_request_that_did_not_change_the_password() {
		$_POST['pass1'] = 'not-the-password';
		$_POST['pass2'] = 'not-the-password';

		wp_update_user(
			array(
				'ID'         => $this->user_id,
				'user_email' => 'ana@libresign.coop',
			)
		);
		$this->finish_request();

		$this->assertSame( array( self::EMAIL_ENDPOINT ), $this->nextcloud->paths() );
	}

	public function test_ignores_a_profile_saved_without_a_new_password() {
		$this->submit_wordpress_profile( '', '' );
		$this->finish_request();

		$this->assertSame( array( self::EMAIL_ENDPOINT ), $this->nextcloud->paths() );
	}

	public function test_ignores_a_password_the_confirmation_does_not_match() {
		$this->submit_wordpress_profile( 'the-new-password', 'a-typo' );
		$this->finish_request();

		$this->assertSame( array(), $this->nextcloud->paths() );
		$this->assertTrue( wp_check_password( 'the-old-password', get_userdata( $this->user_id )->user_pass ) );
	}

	public function test_ignores_a_password_wordpress_refused_to_store() {
		self::factory()->user->create( array( 'user_email' => 'taken@example.org' ) );
		$this->nextcloud->forget();

		$result = wp_update_user(
			array(
				'ID'         => $this->user_id,
				'user_email' => 'taken@example.org',
				'user_pass'  => 'the-new-password',
			)
		);
		$this->finish_request();

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->nextcloud->paths() );
	}

	public function test_ignores_the_password_of_a_user_being_created() {
		self::factory()->user->create(
			array(
				'user_login' => 'bruno',
				'user_pass'  => 'a-password',
			)
		);
		$this->finish_request();

		$this->assertSame( array(), $this->nextcloud->paths() );
	}

	public function test_ignores_the_rehash_of_a_legacy_password_on_login() {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->users,
			array( 'user_pass' => ( new PasswordHash( 8, true ) )->HashPassword( 'the-old-password' ) ),
			array( 'ID' => $this->user_id )
		);
		clean_user_cache( $this->user_id );

		$this->assertSame( $this->user_id, wp_authenticate( 'ana', 'the-old-password' )->ID );
		$this->finish_request();

		$this->assertStringStartsNotWith( '$P$', get_userdata( $this->user_id )->user_pass );
		$this->assertSame( array(), $this->nextcloud->paths() );
	}

	private function submit_wordpress_profile( $pass1, $pass2 ) {
		$_POST = array(
			'email'        => 'ana@example.org',
			'nickname'     => 'ana',
			'display_name' => 'Ana Lima',
			'pass1'        => $pass1,
			'pass2'        => $pass2,
		);

		return edit_user( $this->user_id );
	}

	private function submit_woocommerce_account( $current_password, $new_password ) {
		$fields = array(
			'action'               => 'save_account_details',
			'account_first_name'   => 'Ana',
			'account_last_name'    => 'Lima',
			'account_display_name' => 'Ana Lima',
			'account_email'        => 'ana@example.org',
			'password_current'     => $current_password,
			'password_1'           => $new_password,
			'password_2'           => $new_password,
		);
		$_POST    = $fields;
		$_REQUEST = $fields + array( 'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ) );

		$saved = static function () {
			throw new RuntimeException( 'saved' );
		};
		add_action( 'woocommerce_save_account_details', $saved );

		try {
			WC_Form_Handler::save_account_details();
			$this->fail( 'WooCommerce did not save the account details.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'saved', $e->getMessage() );
		} finally {
			remove_action( 'woocommerce_save_account_details', $saved );
		}
	}

	private function assert_sent_password( $password, $times = 1 ) {
		$requests = array_values(
			array_filter(
				$this->nextcloud->requests(),
				static function ( $request ) {
					return self::USER_ENDPOINT === $request->getRequestUri();
				}
			)
		);

		$this->assertCount( $times, $requests );
		foreach ( $requests as $request ) {
			$this->assertSame( 'PUT', $request->getRequestMethod() );
			$this->assertSame(
				array(
					'key'   => 'password',
					'value' => $password,
				),
				$request->getParsedInput()
			);
		}
	}

	private function finish_request() {
		do_action( 'shutdown' );
	}
}
