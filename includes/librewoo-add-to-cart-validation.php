<?php 
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
    * Class WooOneProductCart
    * Limits only one product in the cart
    * 
    * @since 1.0.0
    *
    */
    
class WooOneProductCart {
    
    public function __construct() {
        // Hook into WooCommerce add to cart validation
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_single_product'], 10, 3);
    }

    public function validate_single_product($passed, $product_id, $quantity) {
        // Validate product ID and quantity
        $product_id = absint($product_id);
        $quantity = absint($quantity);

        if (!$product_id || !$quantity) {
            return false; // Invalid product ID or quantity
        }

        // If there's more than one product in the cart, empty it
        if (WC()->cart->get_cart_contents_count() > 0) {
            $this->empty_cart();
        }

        return $passed;
    }

    private function empty_cart() {
        // Empty the WooCommerce cart
        wc_empty_cart();
    }
}