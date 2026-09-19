<?php

namespace LibreSign\WooNextcloud\Tests\Support;

use WP_Error;

final class HttpFailure {

	public static function on_every_request( $message ) {
		add_filter(
			'pre_http_request',
			static function () use ( $message ) {
				return new WP_Error( 'http_request_failed', $message );
			}
		);
	}
}
