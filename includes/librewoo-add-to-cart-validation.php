<?php 
defined( 'ABSPATH' ) || exit;

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
            wc_empty_cart();
        }

        return $passed;
    }
}