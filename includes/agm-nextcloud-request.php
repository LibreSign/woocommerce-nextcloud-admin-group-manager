<?php
defined( 'ABSPATH' ) || exit;

use LibreSign\WooNextcloud\NextcloudResponse;

function agm_nextcloud_request_headers(): array {
	$login = (string) get_option( 'nextcloud_api_login' );
	$password = (string) get_option( 'nextcloud_api_password' );

	return array(
		'Authorization'  => 'Basic ' . base64_encode( $login . ':' . $password ),
		'Accept'         => 'application/json',
		'OCS-APIRequest' => 'true',
	);
}

function agm_nextcloud_request( string $method, string $path, array $args = array() ): NextcloudResponse {
	$response = wp_remote_request(
		rtrim( (string) get_option( 'nextcloud_api_host' ), '/' ) . $path,
		array_merge(
			$args,
			array(
				'method'  => $method,
				'headers' => agm_nextcloud_request_headers(),
			)
		)
	);

	if ( is_wp_error( $response ) ) {
		return NextcloudResponse::unreachable( $response->get_error_message() );
	}

	return NextcloudResponse::received(
		(int) wp_remote_retrieve_response_code( $response ),
		wp_remote_retrieve_body( $response )
	);
}
