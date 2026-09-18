<?php

if ( 'cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI ) {
	exit;
}

function agm_tests_env( $name, $default ) {
	$value = getenv( $name );

	return false === $value || '' === $value ? $default : $value;
}

define( 'ABSPATH', rtrim( agm_tests_env( 'WP_CORE_DIR', dirname( __DIR__ ) . '/vendor/wordpress' ), '/' ) . '/' );

define( 'DB_NAME', agm_tests_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', agm_tests_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', agm_tests_env( 'WP_TESTS_DB_PASSWORD', 'root' ) );
define( 'DB_HOST', agm_tests_env( 'WP_TESTS_DB_HOST', 'mariadb' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the WordPress test suite reads this global.
$table_prefix = agm_tests_env( 'WP_TESTS_TABLE_PREFIX', 'wptests_' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'LibreSign Test Site' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', true );

define( 'AUTH_KEY', 'agm-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'agm-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'agm-tests-logged-in-key' );
define( 'NONCE_KEY', 'agm-tests-nonce-key' );
define( 'AUTH_SALT', 'agm-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'agm-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'agm-tests-logged-in-salt' );
define( 'NONCE_SALT', 'agm-tests-nonce-salt' );
