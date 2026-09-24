<?php

namespace LibreSign\WooNextcloud\Tests\Unit;

use LibreSign\WooNextcloud\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase {

	/**
	 * @dataProvider provide_attempts
	 */
	public function test_spaces_the_retries_further_apart_on_every_attempt( $attempt, $expected ) {
		$this->assertSame( $expected, RetryPolicy::next_retry_at( $attempt, 1000 ) );
	}

	public static function provide_attempts() {
		yield 'the first failure waits five minutes'  => array( 1, 1300 );
		yield 'the second waits fifteen minutes'      => array( 2, 1900 );
		yield 'the third waits an hour'               => array( 3, 4600 );
		yield 'the fourth waits three hours'          => array( 4, 11800 );
		yield 'the fifth gives up'                    => array( 5, null );
	}
}
