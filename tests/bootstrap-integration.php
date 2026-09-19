<?php

if ( 'cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI ) {
	exit;
}

$agm_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $agm_autoload ) ) {
	echo 'Error: run `composer install` before running the tests.' . PHP_EOL;
	exit( 1 );
}

require_once $agm_autoload;

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor-bin/phpunit/vendor/yoast/phpunit-polyfills' );

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$agm_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( false === $agm_wp_phpunit_dir || '' === $agm_wp_phpunit_dir ) {
	$agm_wp_phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

$agm_test_plugins = dirname( __DIR__ ) . '/vendor/test-plugins';

require_once $agm_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $agm_test_plugins ) {
		require $agm_test_plugins . '/woocommerce/woocommerce.php';
		require $agm_test_plugins . '/woocommerce-subscriptions/woocommerce-subscriptions.php';
		require dirname( __DIR__ ) . '/woocommerce-nextcloud-admin-group-manager.php';
	}
);

tests_add_filter(
	'pre_option_active_plugins',
	static function () {
		return array(
			'woocommerce/woocommerce.php',
			'woocommerce-subscriptions/woocommerce-subscriptions.php',
		);
	}
);

tests_add_filter(
	'setup_theme',
	static function () {
		WC_Install::install();
	}
);

tests_add_filter(
	'action_scheduler_init',
	static function () {
		WC_Subscriptions_Plugin::instance()->activate_plugin();
	}
);

tests_add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false !== $preempt ) {
			return $preempt;
		}

		return new WP_Error(
			'agm_tests_http_blocked',
			sprintf( 'Unexpected HTTP request to %s. Stub it with the pre_http_request filter.', $url )
		);
	},
	PHP_INT_MAX,
	3
);

require $agm_wp_phpunit_dir . '/includes/bootstrap.php';
