<?php

namespace LibreSign\WooNextcloud\Tests\Integration;

use WP_UnitTestCase;

final class WoocommerceNextcloudAdminGroupManagerTest extends WP_UnitTestCase {

	public function test_declares_the_wordpress_and_php_versions_it_supports() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$data = get_plugin_data( dirname( __DIR__, 2 ) . '/woocommerce-nextcloud-admin-group-manager.php', false, false );

		$this->assertSame( '7.0', $data['RequiresWP'] );
		$this->assertSame( '8.3', $data['RequiresPHP'] );
	}
}
