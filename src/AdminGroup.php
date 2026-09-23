<?php

namespace LibreSign\WooNextcloud;

use RuntimeException;

final class AdminGroup {

	public static function id( ?string $user_login, string $billing_email ): string {
		return $user_login ?? $billing_email;
	}

	/**
	 * @param array<string, string[]> $attributes
	 * @return array<string, string|string[]>
	 */
	public static function payload( ?string $user_login, ?string $user_email, string $billing_email, string $first_name, string $last_name, array $attributes ): array {
		$payload = array(
			'groupid'     => self::id( $user_login, $billing_email ),
			'email'       => $user_email ?? $billing_email,
			'displayname' => trim( $first_name . ' ' . $last_name ),
		);

		if ( ! $payload['groupid'] ) {
			throw new RuntimeException( 'Missing group identifier' );
		}
		if ( ! $payload['email'] ) {
			throw new RuntimeException( 'Missing email' );
		}

		foreach ( $attributes as $name => $options ) {
			if ( ! preg_match( '/^nextcloud-(?<type>string|list)-(?<name>.+)/', $name, $matches ) ) {
				continue;
			}
			$payload[ $matches['name'] ] = 'string' === $matches['type'] ? current( $options ) : $options;
		}

		return $payload;
	}
}
