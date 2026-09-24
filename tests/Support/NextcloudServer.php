<?php

namespace LibreSign\WooNextcloud\Tests\Support;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\RequestInfo;
use donatj\MockWebServer\Response;
use donatj\MockWebServer\ResponseInterface;

final class NextcloudServer {

	private static $server;

	private $offset;

	public function __construct() {
		$server = self::server();
		$server->setDefaultResponse( new Response( '' ) );

		update_option( 'nextcloud_api_host', $server->getServerRoot() );
		update_option( 'nextcloud_api_login', 'admin' );
		update_option( 'nextcloud_api_password', 'admin-password' );

		$this->forget();
	}

	public static function response( $status, $body = '' ) {
		return new Response( $body, array(), $status );
	}

	public function root() {
		return self::server()->getServerRoot();
	}

	public function expected_authorization() {
		return 'Basic ' . base64_encode( 'admin:admin-password' );
	}

	public function answer_with( ResponseInterface $response ) {
		self::server()->setDefaultResponse( $response );
	}

	public function requests() {
		return array_slice( $this->received(), $this->offset );
	}

	public function request( $index = 0 ) {
		$requests = $this->requests();

		return $requests[ $index ];
	}

	public function paths() {
		return array_map(
			static function ( RequestInfo $request ) {
				return $request->getRequestUri();
			},
			$this->requests()
		);
	}

	public function forget() {
		$this->offset = count( $this->received() );
	}

	private function received() {
		$server   = self::server();
		$requests = array();
		$offset   = 0;
		$request  = $server->getRequestByOffset( $offset );

		while ( null !== $request ) {
			$requests[] = $request;
			++$offset;
			$request = $server->getRequestByOffset( $offset );
		}

		return $requests;
	}

	private static function server() {
		if ( null === self::$server ) {
			self::$server = new MockWebServer();
			self::$server->start();
		}

		return self::$server;
	}
}
