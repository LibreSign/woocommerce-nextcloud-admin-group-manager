<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\FakeHttp;
use LibreSign\WooNextcloud\Tests\Support\NextcloudSettings;
use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WC_Order;
use WP_Error;
use WP_UnitTestCase;

final class StatusProcessingTest extends WP_UnitTestCase {

	use NextcloudSettings;

	private const ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group';

	private const SET_ENABLED_ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v2.php/apps/admin_group_manager/api/v1/users-of-group/set-enabled';

	private const RETRY_HOOK = 'agm_retry_nextcloud_sync';

	private const RETRY_GROUP = 'nextcloud-admin-group-manager';

	private $nextcloud;

	private $orders;

	public function set_up() {
		parent::set_up();

		$this->register_nextcloud_settings();

		$this->nextcloud = new FakeHttp();
		$this->orders    = new OrderFactory();
	}

	private function process( WC_Order $order ) {
		do_action( 'woocommerce_order_status_processing', $order->get_id() );

		return wc_get_order( $order->get_id() );
	}

	private function notes_of( WC_Order $order ) {
		return array_map(
			static function ( $note ) {
				return $note->content;
			},
			wc_get_order_notes( array( 'order_id' => $order->get_id() ) )
		);
	}

	public function test_sends_the_customer_and_the_plan_to_nextcloud() {
		$this->nextcloud->answer_with( FakeHttp::response( 200, '{"ocs":{"meta":{"status":"ok"}}}' ) );

		$user  = self::factory()->user->create_and_get(
			array(
				'user_login' => 'ana',
				'user_email' => 'ana@example.org',
			)
		);
		$order = $this->orders->order(
			array(
				'customer_id' => $user->ID,
				'attributes'  => array(
					'nextcloud-string-quota' => array( '5GB' ),
					'nextcloud-list-groups'  => array( 'signers', 'admins' ),
					'color'                  => array( 'blue' ),
				),
			)
		);

		$this->process( $order );

		$request = $this->nextcloud->requests()[0];

		$this->assertSame( self::ENDPOINT, $request['url'] );
		$this->assertSame(
			array(
				'groupid'     => 'ana',
				'email'       => 'ana@example.org',
				'displayname' => 'Ana Lima',
				'quota'       => '5GB',
				'groups'      => array( 'signers', 'admins' ),
			),
			$request['args']['body']
		);
		$this->assertSame( $this->expected_authorization(), $request['args']['headers']['Authorization'] );
		$this->assertSame( 'true', $request['args']['headers']['OCS-APIRequest'] );
		$this->assertSame( 15, $request['args']['timeout'] );
	}

	public function test_completes_the_order_when_nextcloud_accepts_it() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$order = $this->process( $this->orders->order() );

		$this->assertSame( 'completed', $order->get_status() );
		$this->assertSame( 'success', $order->get_meta( '_agm_nextcloud_sync_status', true ) );
		$this->assertSame( '1', $order->get_meta( '_agm_nextcloud_sync_attempts', true ) );
		$this->assertSame( '', $order->get_meta( '_agm_nextcloud_sync_last_error', true ) );
		$this->assertContains( 'Nextcloud sync completed successfully.', $this->notes_of( $order ) );
	}

	public function test_falls_back_to_the_billing_email_when_the_order_has_no_customer() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$this->process( $this->orders->order( array( 'billing' => array( 'email' => 'guest@example.org' ) ) ) );

		$body = $this->nextcloud->args()['body'];

		$this->assertSame( 'guest@example.org', $body['groupid'] );
		$this->assertSame( 'guest@example.org', $body['email'] );
	}

	public function test_skips_an_order_already_synced() {
		$order = $this->orders->order();
		$order->update_meta_data( '_agm_nextcloud_sync_status', 'success' );
		$order->save();

		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$order = $this->process( $order );

		$this->assertSame( array(), $this->nextcloud->urls() );
		$this->assertSame( 'pending', $order->get_status() );
	}

	/**
	 * @dataProvider provide_incomplete_orders
	 */
	public function test_refuses_an_order_it_cannot_describe( $build, $message ) {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$order = $this->process( $build( $this->orders ) );

		$this->assertSame( array(), $this->nextcloud->urls() );
		$this->assertSame( 'failed', $order->get_meta( '_agm_nextcloud_sync_status', true ) );
		$this->assertSame( $message, $order->get_meta( '_agm_nextcloud_sync_last_error', true ) );
		$this->assertSame( '', $order->get_meta( '_agm_nextcloud_sync_attempts', true ) );
		$this->assertContains( 'Nextcloud sync failed: ' . $message, $this->notes_of( $order ) );
	}

	public static function provide_incomplete_orders() {
		yield 'an order with no line items' => array(
			static function ( OrderFactory $orders ) {
				return $orders->order_without_products();
			},
			'Order has no product items',
		);

		yield 'a guest order with no billing email' => array(
			static function ( OrderFactory $orders ) {
				return $orders->order( array( 'billing' => array( 'email' => '' ) ) );
			},
			'Missing group identifier',
		);
	}

	public function test_keeps_the_order_open_and_schedules_a_retry_when_nextcloud_fails() {
		$this->nextcloud->answer_with( FakeHttp::response( 500, '<p>Internal Server Error</p>' ) );

		$order = $this->process( $this->orders->order() );

		$this->assertSame( 'pending', $order->get_status() );
		$this->assertSame( 'failed', $order->get_meta( '_agm_nextcloud_sync_status', true ) );
		$this->assertSame( 'HTTP 500: Internal Server Error', $order->get_meta( '_agm_nextcloud_sync_last_error', true ) );
		$this->assertSame( '1', $order->get_meta( '_agm_nextcloud_sync_attempts', true ) );
		$this->assertContains(
			'Nextcloud sync failed: HTTP 500: Internal Server Error A retry was scheduled automatically.',
			$this->notes_of( $order )
		);
	}

	public function test_records_the_transport_error_when_nextcloud_is_unreachable() {
		$this->nextcloud->answer_with( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$order = $this->process( $this->orders->order() );

		$this->assertSame( 'failed', $order->get_meta( '_agm_nextcloud_sync_status', true ) );
		$this->assertSame( 'Connection timed out', $order->get_meta( '_agm_nextcloud_sync_last_error', true ) );
	}

	/**
	 * @dataProvider provide_retry_delays
	 */
	public function test_spaces_the_retries_further_apart_on_every_attempt( $previous_attempts, $delay ) {
		$this->nextcloud->answer_with( FakeHttp::response( 500 ) );

		$order = $this->orders->order();
		$order->update_meta_data( '_agm_nextcloud_sync_attempts', (string) $previous_attempts );
		$order->save();

		$this->process( $order );

		$this->assertEqualsWithDelta(
			time() + $delay,
			as_next_scheduled_action( self::RETRY_HOOK, array( 'order_id' => $order->get_id() ), self::RETRY_GROUP ),
			5
		);
	}

	public static function provide_retry_delays() {
		yield 'the first failure waits five minutes' => array( 0, 5 * MINUTE_IN_SECONDS );
		yield 'the second waits fifteen minutes'     => array( 1, 15 * MINUTE_IN_SECONDS );
		yield 'the third waits an hour'              => array( 2, HOUR_IN_SECONDS );
		yield 'the fourth waits three hours'         => array( 3, 3 * HOUR_IN_SECONDS );
	}

	public function test_gives_up_after_the_fifth_attempt() {
		$this->nextcloud->answer_with( FakeHttp::response( 500 ) );

		$order = $this->orders->order();
		$order->update_meta_data( '_agm_nextcloud_sync_attempts', '4' );
		$order->save();

		$order = $this->process( $order );

		$this->assertFalse(
			as_next_scheduled_action( self::RETRY_HOOK, array( 'order_id' => $order->get_id() ), self::RETRY_GROUP )
		);
		$this->assertSame( '5', $order->get_meta( '_agm_nextcloud_sync_attempts', true ) );
		$this->assertContains( 'Nextcloud sync failed: HTTP 500: ', $this->notes_of( $order ) );
	}

	public function test_the_scheduled_retry_syncs_the_order_again() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$order = $this->orders->order();

		do_action( self::RETRY_HOOK, $order->get_id() );

		$this->assertSame( array( self::ENDPOINT ), $this->nextcloud->urls() );
		$this->assertSame( 'completed', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_a_retry_of_an_order_that_no_longer_exists_does_nothing() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		do_action( self::RETRY_HOOK, 987654321 );

		$this->assertSame( array(), $this->nextcloud->urls() );
	}

	public function test_offers_the_manual_retry_among_the_order_actions() {
		$this->assertSame(
			'Retry Nextcloud sync',
			apply_filters( 'woocommerce_order_actions', array() )['agm_retry_nextcloud_sync']
		);
	}

	public function test_the_manual_retry_disables_the_account_of_an_order_no_longer_active() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$user  = self::factory()->user->create_and_get( array( 'user_login' => 'ana' ) );
		$order = $this->orders->order(
			array(
				'customer_id' => $user->ID,
				'status'      => 'on-hold',
			)
		);

		do_action( 'woocommerce_order_action_agm_retry_nextcloud_sync', $order );

		$this->assertSame( array( self::SET_ENABLED_ENDPOINT ), $this->nextcloud->urls() );
		$this->assertSame(
			array(
				'groupid' => 'ana',
				'enabled' => 0,
			),
			$this->nextcloud->args()['body']
		);

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( 'success', $order->get_meta( '_agm_nextcloud_sync_status', true ) );
		$this->assertContains( 'Nextcloud account disabled successfully after manual retry.', $this->notes_of( $order ) );
	}

	public function test_the_manual_retry_syncs_an_order_still_active() {
		$this->nextcloud->answer_with( FakeHttp::response( 200 ) );

		$order = $this->orders->order();

		do_action( 'woocommerce_order_action_agm_retry_nextcloud_sync', $order );

		$this->assertSame( array( self::ENDPOINT ), $this->nextcloud->urls() );
		$this->assertContains( 'Nextcloud sync completed successfully after manual retry.', $this->notes_of( wc_get_order( $order->get_id() ) ) );
	}
}
