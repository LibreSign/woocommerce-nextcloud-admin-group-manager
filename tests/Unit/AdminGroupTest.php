<?php

namespace LibreSign\WooNextcloud\Tests\Unit;

use LibreSign\WooNextcloud\AdminGroup;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdminGroupTest extends TestCase {

	/**
	 * @dataProvider provide_orders
	 */
	public function test_describes_the_group_of_an_order( $user_login, $user_email, $billing_email, $attributes, $expected ) {
		$this->assertSame(
			$expected,
			AdminGroup::payload( $user_login, $user_email, $billing_email, 'Ana', 'Lima', $attributes )
		);
	}

	public static function provide_orders() {
		yield 'a customer is named by the login' => array(
			'ana',
			'ana@example.org',
			'billing@example.org',
			array(),
			array(
				'groupid'     => 'ana',
				'email'       => 'ana@example.org',
				'displayname' => 'Ana Lima',
			),
		);

		yield 'a guest is named by the billing email' => array(
			null,
			null,
			'guest@example.org',
			array(),
			array(
				'groupid'     => 'guest@example.org',
				'email'       => 'guest@example.org',
				'displayname' => 'Ana Lima',
			),
		);

		yield 'a string attribute sends its first option' => array(
			'ana',
			'ana@example.org',
			'',
			array( 'nextcloud-string-quota' => array( '5GB', '10GB' ) ),
			array(
				'groupid'     => 'ana',
				'email'       => 'ana@example.org',
				'displayname' => 'Ana Lima',
				'quota'       => '5GB',
			),
		);

		yield 'a list attribute sends every option' => array(
			'ana',
			'ana@example.org',
			'',
			array( 'nextcloud-list-apps' => array( 'libresign', 'deck' ) ),
			array(
				'groupid'     => 'ana',
				'email'       => 'ana@example.org',
				'displayname' => 'Ana Lima',
				'apps'        => array( 'libresign', 'deck' ),
			),
		);

		yield 'an attribute not meant for nextcloud is left out' => array(
			'ana',
			'ana@example.org',
			'',
			array(
				'color'                  => array( 'blue' ),
				'nextcloud-number-users' => array( '3' ),
			),
			array(
				'groupid'     => 'ana',
				'email'       => 'ana@example.org',
				'displayname' => 'Ana Lima',
			),
		);
	}

	public function test_a_display_name_is_trimmed_when_a_name_is_missing() {
		$payload = AdminGroup::payload( 'ana', 'ana@example.org', '', 'Ana', '', array() );

		$this->assertSame( 'Ana', $payload['displayname'] );
	}

	/**
	 * @dataProvider provide_incomplete_orders
	 */
	public function test_refuses_an_order_it_cannot_describe( $user_login, $user_email, $billing_email, $message ) {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( $message );

		AdminGroup::payload( $user_login, $user_email, $billing_email, 'Ana', 'Lima', array() );
	}

	public static function provide_incomplete_orders() {
		yield 'a guest with no billing email'  => array( null, null, '', 'Missing group identifier' );
		yield 'a customer with no email'       => array( 'ana', '', 'billing@example.org', 'Missing email' );
	}
}
