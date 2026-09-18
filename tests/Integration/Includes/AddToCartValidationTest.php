<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\OrderFactory;
use WP_UnitTestCase;

final class AddToCartValidationTest extends WP_UnitTestCase {

	private $products;

	public function set_up() {
		parent::set_up();

		wc_load_cart();
		WC()->cart->empty_cart();

		$this->products = new OrderFactory();
	}

	private function validate( $product_id, $quantity = 1, $passed = true ) {
		return apply_filters( 'woocommerce_add_to_cart_validation', $passed, $product_id, $quantity );
	}

	public function test_keeps_only_the_product_being_added() {
		WC()->cart->add_to_cart( $this->products->product()->get_id() );

		$this->assertTrue( $this->validate( $this->products->product()->get_id() ) );
		$this->assertSame( 0, WC()->cart->get_cart_contents_count() );
	}

	public function test_leaves_an_empty_cart_alone() {
		$this->assertTrue( $this->validate( $this->products->product()->get_id() ) );
		$this->assertSame( 0, WC()->cart->get_cart_contents_count() );
	}

	public function test_keeps_a_refusal_another_validation_already_decided() {
		$this->assertFalse( $this->validate( $this->products->product()->get_id(), 1, false ) );
	}

	/**
	 * @dataProvider provide_meaningless_requests
	 */
	public function test_refuses_a_request_that_names_no_product( $product_id, $quantity ) {
		WC()->cart->add_to_cart( $this->products->product()->get_id() );

		$this->assertFalse( $this->validate( $product_id, $quantity ) );
		$this->assertSame( 1, WC()->cart->get_cart_contents_count() );
	}

	public function test_reads_a_negative_quantity_as_a_positive_one() {
		WC()->cart->add_to_cart( $this->products->product()->get_id() );

		$this->assertTrue( $this->validate( $this->products->product()->get_id(), -1 ) );
		$this->assertSame( 0, WC()->cart->get_cart_contents_count() );
	}

	public static function provide_meaningless_requests() {
		yield 'no product'  => array( 0, 1 );
		yield 'no quantity' => array( 1, 0 );
	}
}
