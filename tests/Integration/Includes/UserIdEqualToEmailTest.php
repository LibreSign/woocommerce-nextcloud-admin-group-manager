<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use WP_UnitTestCase;

final class UserIdEqualToEmailTest extends WP_UnitTestCase {

	/**
	 * @dataProvider provide_emails
	 */
	public function test_names_the_customer_after_the_address_they_signed_up_with( $email, $expected ) {
		$this->assertSame( $expected, apply_filters( 'woocommerce_new_customer_username', 'ana.lima', $email ) );
	}

	public static function provide_emails() {
		yield 'the address becomes the username' => array( 'ana@example.org', 'ana@example.org' );
		yield 'surrounding spaces are dropped'   => array( ' ana@example.org ', 'ana@example.org' );
		yield 'an address with no domain leaves no username' => array( 'ana', '' );
	}
}
