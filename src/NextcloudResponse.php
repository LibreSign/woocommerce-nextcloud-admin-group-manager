<?php

namespace LibreSign\WooNextcloud;

final class NextcloudResponse {

	private function __construct(
		public readonly int $status,
		public readonly string $body,
		public readonly string $error,
	) {
	}

	public static function received( int $status, string $body ): self {
		return new self( $status, $body, '' );
	}

	public static function unreachable( string $error ): self {
		return new self( 0, '', $error );
	}

	public function succeeded(): bool {
		return 200 === $this->status;
	}

	public function failure_message(): string {
		if ( '' !== $this->error ) {
			return $this->error;
		}

		if ( $this->status > 0 ) {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $this->body );

			return sprintf( 'HTTP %d: %s', $this->status, trim( strip_tags( (string) $text ) ) );
		}

		return 'Unknown error while calling Nextcloud API.';
	}
}
