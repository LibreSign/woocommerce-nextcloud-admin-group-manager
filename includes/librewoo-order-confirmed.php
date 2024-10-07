<?php
// You shall not pass!
if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class WooOrderComplete
 *
 * Triggers LibreSign API when an order is completed
 *
 *
 * @since 1.0.0
 */

class WooOrderComplete
{
    public function __construct()
    {
        add_action("woocommerce_order_status_processing", [
            $this,
            "order_complete_message",
        ]);
    }

    public function order_complete_message($order_id)
    {
        // Ensure the order ID is valid
        $order_id = absint($order_id);

        if (!$order_id) {
            return;
        }
        if (!is_numeric($order_id)) {
            return;
        }

        /**
         * Get order data from WooCommerce order object
         * Data: customer name, customer email, purchased items
         *
         */
        $this->get_order_data(wc_get_order($order_id));

        $this->librewoo_trigger($this->get_order_data(wc_get_order($order_id)));
    }

    /**
     * Get order data from WooCommerce order object
     * Data: customer name, customer email, purchased items
     *
     * @param WC_Order $order
     * @return stdClass
     * @since 1.0.0
     */
    private function get_order_data($order_id)
    {
        $order = $order_id;

        $woo_client_info = new stdClass();
        $woo_client_info->customer_name = $order->get_billing_first_name();
        $woo_client_info->customer_last_name = $order->get_billing_last_name();
        $woo_client_info->customer_email = $order->get_billing_email();
        // Retrieve purchased items
        $woo_client_info->purchased_items = [];
        /// Loop through each order item
        foreach ($order->get_items() as $item_id => $item) {
            $woo_client_info->purchased_items[] = [
                "id" => $item->get_id(), // Product ID
                "name" => $item->get_name(), // Product name
                "quantity" => $item->get_quantity(), // Quantity ordered
                "total" => wc_price($item->get_total()), // Total price (formatted with currency symbol)
            ];
        }

        return $woo_client_info;
    }

    /**
     * Trigger LibreSign API
     * @return void
     * @since 1.0.0
     */
    private function librewoo_trigger($order_data)
    {
        //Convert stdClass to array
        $order_data = get_object_vars($order_data);
        $email = $order_data["customer_email"];
        $name =
            $order_data["customer_name"] .
            " " .
            $order_data["customer_last_name"];
        $quota = $order_data["purchased_items"][0]["name"];
        $this->librewoo_trigger_log($email, $name, $quota);
    }

    function librewoo_trigger_log($email, $name, $quota)
    {
        // Validate email, name and quota
        $email ? $email : false;
        $name ? $name : false;
        $quota ? $quota : false;

        // Logs
        if ($email && $name && $quota) {
            error_log(
                sprintf(
                    "LibreSign: Name: %s Email: %s Quota: %s",
                    $name,
                    $email,
                    $quota
                )
            );
        } else {
            $variables = [
                "name" => $name,
                "email" => $email,
                "quota" => $quota,
            ];
            foreach ($variables as $key => $value) {
                if (!$value) {
                    error_log(sprintf("LibreSign: Missing %s", $key));
                }
            }
        }

        // TRIGGER LibreSign API HERE
    }
}
