<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\HttpFailure;
use LibreSign\WooNextcloud\Tests\Support\NextcloudServer;
use WP_UnitTestCase;

final class NextcloudRequestTest extends WP_UnitTestCase {

	private $nextcloud;

	public function set_up() {
		parent::set_up();

		$this->nextcloud = new NextcloudServer();
	}

	public function test_sends_the_request_as_the_configured_user() {
		agm_nextcloud_request( 'POST', '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group', array( 'body' => array( 'groupid' => 'ana' ) ) );

		$request = $this->nextcloud->request();

		$this->assertSame( 'POST', $request->getRequestMethod() );
		$this->assertSame( '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group', $request->getRequestUri() );
		$this->assertSame( array( 'groupid' => 'ana' ), $request->getParsedInput() );
		$this->assertSame( $this->nextcloud->expected_authorization(), $request->getHeaders()['Authorization'] );
		$this->assertSame( 'true', $request->getHeaders()['OCS-APIRequest'] );
		$this->assertSame( 'application/json', $request->getHeaders()['Accept'] );
	}

	public function test_does_not_double_the_slash_after_the_host() {
		update_option( 'nextcloud_api_host', $this->nextcloud->root() . '/' );

		agm_nextcloud_request( 'GET', '/ocs/v2.php/cloud/user?format=json' );

		$this->assertSame( array( '/ocs/v2.php/cloud/user?format=json' ), $this->nextcloud->paths() );
	}

	public function test_hands_back_what_nextcloud_answered() {
		$this->nextcloud->answer_with( NextcloudServer::response( 500, 'Internal Server Error' ) );

		$response = agm_nextcloud_request( 'GET', '/ocs/v2.php/cloud/user?format=json' );

		$this->assertSame( 500, $response->status );
		$this->assertSame( 'Internal Server Error', $response->body );
		$this->assertSame( '', $response->error );
	}

	public function test_hands_back_why_nextcloud_could_not_be_reached() {
		HttpFailure::on_every_request( 'Connection timed out' );

		$response = agm_nextcloud_request( 'GET', '/ocs/v2.php/cloud/user?format=json' );

		$this->assertSame( 'Connection timed out', $response->error );
		$this->assertFalse( $response->succeeded() );
	}
}
