<?php

namespace LibreSign\WooNextcloud\Tests\Support;

use WP_Error;

final class FakeHttp {

	private $requests = array();

	public function answer_with( $response ) {
		$this->answer_each( array( '*' => $response ) );
	}

	public function answer_each( $responses ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $responses ) {
				$this->requests[] = array(
					'url'  => (string) $url,
					'args' => (array) $args,
				);

				if ( isset( $responses[ $url ] ) ) {
					return $responses[ $url ];
				}

				if ( isset( $responses['*'] ) ) {
					return $responses['*'];
				}

				return new WP_Error(
					'agm_tests_http_unstubbed',
					sprintf( 'No response was stubbed for %s.', $url )
				);
			},
			10,
			3
		);
	}

	public function requests() {
		return $this->requests;
	}

	public function urls() {
		return array_column( $this->requests, 'url' );
	}

	public function args( $index = 0 ) {
		return isset( $this->requests[ $index ] ) ? $this->requests[ $index ]['args'] : array();
	}

	public function forget() {
		$this->requests = array();
	}

	public static function response( $code, $body = '' ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
