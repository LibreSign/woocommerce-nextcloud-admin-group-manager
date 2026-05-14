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
            $this->log('Order not found', ['order_id' => $order_id]);
            return;
        }

        try {
            $data = $this->get_order_data($order);
        } catch (RuntimeException $exception) {
            $this->log('Unable to build Nextcloud payload', [
                'order_id' => $order_id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $payload = get_object_vars($data);
        $return = wp_remote_post(
            get_option('nextcloud_api_host') . '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group',
            [
                'body' => $payload,
                'headers' => agm_nextcloud_request_headers(),
            ]
        );
        $this->log_response($order_id, $payload, $return);
        if (is_array($return) && isset($return['response']['code']) && (int)$return['response']['code'] === 200) {
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
        if (!$item) {
            throw new RuntimeException('Order has no items');
        }

        $product = wc_get_product($item->get_product_id());
        if (!$product) {
            throw new RuntimeException('Order item product not found');
        }

        $attributes = $product->get_attributes();
        $user = $order->get_user();

        $data = new stdClass();
        $data->groupid = $user ? $user->user_login : $order->get_billing_email();
        $data->email = $user ? $user->user_email : $order->get_billing_email();
        $data->displayname = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (!$data->groupid) {
            throw new RuntimeException('Missing group identifier');
        }
        if (!$data->email) {
            throw new RuntimeException('Missing email');
        }
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

    private function log_response($order_id, array $payload, $response): void
    {
        $context = [
            'order_id' => $order_id,
            'payload' => $payload,
        ];
        if (is_wp_error($response)) {
            $context['error'] = $response->get_error_message();
            $this->log('Nextcloud request failed', $context);
            return;
        }
        $context['response_code'] = $response['response']['code'] ?? null;
        $context['response_body'] = $response['body'] ?? null;
        $this->log('Nextcloud request completed', $context);
    }

    private function log(string $message, array $context = []): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info($message . ' ' . wp_json_encode($context), ['source' => 'nextcloud-admin-group-manager']);
            return;
        }
        error_log('[nextcloud-admin-group-manager] ' . $message . ' ' . wp_json_encode($context));
    }
}
