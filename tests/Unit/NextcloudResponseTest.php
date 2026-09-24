<?php

namespace LibreSign\WooNextcloud\Tests\Unit;

use LibreSign\WooNextcloud\NextcloudResponse;
use PHPUnit\Framework\TestCase;

final class NextcloudResponseTest extends TestCase {

	/**
	 * @dataProvider provide_responses
	 */
	public function test_tells_whether_nextcloud_accepted_the_request( $response, $succeeded ) {
		$this->assertSame( $succeeded, $response->succeeded() );
	}

	public static function provide_responses() {
		yield 'an answer with HTTP 200'           => array( NextcloudResponse::received( 200, '' ), true );
		yield 'an answer with another 2xx'        => array( NextcloudResponse::received( 201, '' ), false );
		yield 'an answer with HTTP 500'           => array( NextcloudResponse::received( 500, 'Internal Server Error' ), false );
		yield 'an answer without a status'        => array( NextcloudResponse::received( 0, '' ), false );
		yield 'a request that never reached it'   => array( NextcloudResponse::unreachable( 'Connection timed out' ), false );
	}

	/**
	 * @dataProvider provide_failures
	 */
	public function test_describes_why_the_request_failed( $response, $message ) {
		$this->assertSame( $message, $response->failure_message() );
	}

	public static function provide_failures() {
		yield 'a request that never reached it' => array(
			NextcloudResponse::unreachable( 'Connection timed out' ),
			'Connection timed out',
		);

		yield 'a plain text answer' => array(
			NextcloudResponse::received( 500, 'Internal Server Error' ),
			'HTTP 500: Internal Server Error',
		);

		yield 'an html page keeps only its text' => array(
			NextcloudResponse::received( 503, "<html><head><style>body { color: red; }</style><script>var a = 1;</script></head><body><h1>Maintenance</h1></body></html>\n" ),
			'HTTP 503: Maintenance',
		);

		yield 'an answer without a status' => array(
			NextcloudResponse::received( 0, 'anything' ),
			'Unknown error while calling Nextcloud API.',
		);
	}
}
