<?php

namespace LibreSign\WooNextcloud\Tests\Support;

trait NextcloudSettings {

	private const NEXTCLOUD_HOST = 'https://nextcloud.example.org';

	private function register_nextcloud_settings() {
		update_option( 'nextcloud_api_host', self::NEXTCLOUD_HOST );
		update_option( 'nextcloud_api_login', 'admin' );
		update_option( 'nextcloud_api_password', 'admin-password' );
	}

	private function expected_authorization() {
		return 'Basic ' . base64_encode( 'admin:admin-password' );
	}
}
