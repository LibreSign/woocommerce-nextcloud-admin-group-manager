<?php

namespace LibreSign\WooNextcloud;

final class RetryPolicy {

	private const DELAYS = array(
		1 => 5 * 60,
		2 => 15 * 60,
		3 => 60 * 60,
		4 => 3 * 60 * 60,
	);

	public static function next_retry_at( int $attempt, int $now ): ?int {
		return isset( self::DELAYS[ $attempt ] ) ? $now + self::DELAYS[ $attempt ] : null;
	}
}
