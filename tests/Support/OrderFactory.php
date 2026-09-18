<?php

namespace LibreSign\WooNextcloud\Tests\Support;

use WC_Order;
use WC_Product_Attribute;
use WC_Product_Simple;

final class OrderFactory {

	public function product( $attributes = array() ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'LibreSign plan' );
		$product->set_regular_price( '10' );
		$product->set_attributes( $this->build_attributes( $attributes ) );
		$product->save();

		return $product;
	}

	public function order( $overrides = array() ) {
		$order = $this->order_without_products( $overrides );
		$order->add_product( $this->product( isset( $overrides['attributes'] ) ? $overrides['attributes'] : array() ), 1 );
		$order->save();

		return $order;
	}

	public function order_without_products( $overrides = array() ) {
		$billing = array_replace(
			array(
				'email'      => 'billing@example.org',
				'first_name' => 'Ana',
				'last_name'  => 'Lima',
			),
			isset( $overrides['billing'] ) ? $overrides['billing'] : array()
		);

		$order = wc_create_order(
			array(
				'customer_id' => isset( $overrides['customer_id'] ) ? $overrides['customer_id'] : 0,
				'status'      => isset( $overrides['status'] ) ? $overrides['status'] : 'pending',
			)
		);

		$order->set_billing_email( $billing['email'] );
		$order->set_billing_first_name( $billing['first_name'] );
		$order->set_billing_last_name( $billing['last_name'] );
		$order->save();

		return $order;
	}

	private function build_attributes( $attributes ) {
		$built = array();

		foreach ( $attributes as $name => $options ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $name );
			$attribute->set_options( $options );
			$attribute->set_visible( true );

			$built[] = $attribute;
		}

		return $built;
	}
}
