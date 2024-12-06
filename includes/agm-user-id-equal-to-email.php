<?php
defined( 'ABSPATH' ) || exit;

class AgmUserIdEqualToEmail
{
    public function __construct()
    {
        add_filter('woocommerce_new_customer_username', [$this, 'new_customer_username'], 10, 2);
    }

    function new_customer_username($username, $email)
    {
        $new_username = sanitize_email($email);
        return $new_username;
    }
}