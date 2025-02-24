<?php
defined( 'ABSPATH' ) || exit;

class AgmStatusProcessing
{
    public function __construct()
    {
        add_action('woocommerce_order_status_processing', [$this, 'order_complete_message']);
    }

    public function order_complete_message($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $data = $this->get_order_data($order);
        $return = wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group',
            [
                'body' => get_object_vars($data),
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( get_option('nextcloud_api_login') . ':' . get_option('nextcloud_api_password') )
                ]
            ]
        );
        if ($return['response']['code'] === 200) {
            $order->set_status( 'completed', '', true );
            $order->save();
        }
    }

    /**
     * Get order data from WooCommerce order object
     * Data: customer name, customer email, purchased items
     * 
     * @param WC_Order $order
     * @return stdClass
     * @since 1.0.0
     */
    private function get_order_data($order): stdClass
    {
        $items = $order->get_items();
        $item = current($items);
        $product = wc_get_product($item->get_product_id());
        $attributes = $product->get_attributes();

        $data = new stdClass();
        $data->groupid = $order->get_user()->user_login;
        $data->email = $order->get_user()->user_email;
        $data->displayname = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        foreach ($attributes as $name => $attribute) {
            preg_match('/^nextcloud-(?<type>string|list)-(?<name>.+)/', $name, $matches);
            if (!$matches) {
                continue;
            }
            $options = $attribute->get_options();
            switch ($matches['type']) {
                case 'string':
                    $data->{$matches['name']} = current($options);
                    break;
                case 'list':
                    $data->{$matches['name']} = $options;
                    break;
            }
        }
        return $data;
    }
}