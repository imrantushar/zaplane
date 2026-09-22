<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalises Custom App manifests before they are stored or run.
 *
 * A manifest is fully user-authored, so this is a security boundary as much as
 * a shape check: the slug becomes part of a generated PHP class name and a REST
 * route, and every request URL is eventually sent through the HTTP client. We
 * are strict here so the runtime can stay trusting.
 */
class ManifestValidator {

	public const KINDS          = [ 'http', 'local' ];
	public const AUTH_TYPES     = [ 'none', 'api_key', 'bearer', 'basic', 'oauth2' ];
	public const TRIGGER_MODES  = [ 'polling', 'webhook' ];
	public const HANDLER_TYPES  = [ 'function', 'do_action', 'apply_filters' ];
	public const HTTP_METHODS   = [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ];

	/**
	 * Reduce an arbitrary label/slug to a safe integration slug: lowercase, only
	 * [a-z0-9_], must start with a letter. Returns '' when nothing usable is left.
	 */
	public static function sanitize_slug( string $slug ): string {
		$slug = strtolower( $slug );
		$slug = preg_replace( '/[^a-z0-9_]+/', '_', $slug );
		$slug = trim( (string) $slug, '_' );
		$slug = preg_replace( '/_{2,}/', '_', (string) $slug );

		if ( '' === $slug || ! preg_match( '/^[a-z]/', $slug ) ) {
			return '';
		}

		return substr( $slug, 0, 64 );
	}

	/**
	 * A field key used inside request templates / config. Same rules as a slug
	 * but allowed to be a bare word.
	 */
	public static function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		$key = preg_replace( '/[^a-z0-9_]+/', '_', $key );
		return trim( (string) $key, '_' );
	}

	/**
	 * Validate a manifest.
	 *
	 * @param array    $manifest       The manifest to check.
	 * @param string[] $reserved_slugs Slugs already taken by built-in integrations.
	 * @return string[] List of human-readable errors. Empty means valid.
	 */
	public static function validate( array $manifest, array $reserved_slugs = [] ): array {
		$errors = [];

		// --- Identity ------------------------------------------------------
		$slug = isset( $manifest['slug'] ) ? (string) $manifest['slug'] : '';
		if ( '' === $slug ) {
			$errors[] = 'A slug is required.';
		} elseif ( self::sanitize_slug( $slug ) !== $slug ) {
			$errors[] = 'Slug must be lowercase and contain only letters, numbers and underscores, starting with a letter.';
		} elseif ( in_array( $slug, $reserved_slugs, true ) ) {
			$errors[] = sprintf( 'The slug "%s" is already used by a built-in integration.', $slug );
		}

		if ( empty( $manifest['name'] ) || ! is_string( $manifest['name'] ) ) {
			$errors[] = 'A display name is required.';
		}

		// --- Kind ----------------------------------------------------------
		// "http" (default) apps talk to a REST API; "local" apps integrate with
		// another plugin on the same site via WP hooks and PHP callables.
		$kind = isset( $manifest['kind'] ) ? (string) $manifest['kind'] : 'http';
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			$errors[] = sprintf( 'Unknown app kind "%s".', $kind );
			$kind     = 'http';
		}

		if ( 'local' === $kind ) {
			return array_merge( $errors, self::validate_local( $manifest ) );
		}

		// --- base_url ------------------------------------------------------
		$base_url = isset( $manifest['base_url'] ) ? (string) $manifest['base_url'] : '';
		if ( '' !== $base_url && ! self::is_safe_url( $base_url ) ) {
			$errors[] = 'Base URL must be a valid http(s) URL.';
		}

		// --- Auth ----------------------------------------------------------
		$auth      = isset( $manifest['auth'] ) && is_array( $manifest['auth'] ) ? $manifest['auth'] : [];
		$auth_type = isset( $auth['type'] ) ? (string) $auth['type'] : 'none';
		if ( ! in_array( $auth_type, self::AUTH_TYPES, true ) ) {
			$errors[] = sprintf( 'Unknown auth type "%s".', $auth_type );
		}
		if ( 'oauth2' === $auth_type ) {
			$oauth = isset( $auth['oauth2'] ) && is_array( $auth['oauth2'] ) ? $auth['oauth2'] : [];
			foreach ( [ 'authorize_url', 'token_url' ] as $required ) {
				if ( empty( $oauth[ $required ] ) || ! self::is_safe_url( (string) $oauth[ $required ] ) ) {
					$errors[] = sprintf( 'OAuth2 auth requires a valid %s.', $required );
				}
			}
			if ( ! empty( $oauth['refresh_url'] ) && ! self::is_safe_url( (string) $oauth['refresh_url'] ) ) {
				$errors[] = 'OAuth2 auth has an invalid refresh_url.';
			}
		}

		if ( ! empty( $auth['fields'] ) ) {
			$errors = array_merge( $errors, self::validate_fields( $auth['fields'], 'Authentication' ) );
		}
		if ( ! empty( $auth['test'] ) ) {
			$errors = array_merge( $errors, self::validate_request( $auth['test'], $base_url, 'Authentication test' ) );
		}

		// --- Actions -------------------------------------------------------
		$actions  = isset( $manifest['actions'] ) && is_array( $manifest['actions'] ) ? $manifest['actions'] : [];
		$triggers = isset( $manifest['triggers'] ) && is_array( $manifest['triggers'] ) ? $manifest['triggers'] : [];

		if ( empty( $actions ) && empty( $triggers ) ) {
			$errors[] = 'A custom app needs at least one action or trigger.';
		}

		$action_keys = [];
		foreach ( $actions as $i => $action ) {
			$label = 'Action #' . ( (int) $i + 1 );
			if ( ! is_array( $action ) ) {
				$errors[] = $label . ' is malformed.';
				continue;
			}
			if ( empty( $action['key'] ) ) {
				$errors[] = $label . ' is missing a key.';
			} elseif ( self::sanitize_key( (string) $action['key'] ) !== (string) $action['key'] ) {
				$errors[] = $label . ' key must contain only lowercase letters, numbers and underscores.';
			} elseif ( isset( $action_keys[ (string) $action['key'] ] ) ) {
				$errors[] = $label . ' duplicates the key used by Action #' . $action_keys[ (string) $action['key'] ] . '.';
			} else {
				$action_keys[ (string) $action['key'] ] = (int) $i + 1;
			}
			if ( '' === trim( (string) ( $action['label'] ?? '' ) ) ) {
				$errors[] = $label . ' needs a label (shown in the Action Type dropdown).';
			}
			$errors = array_merge( $errors, self::validate_request( $action['request'] ?? null, $base_url, $label ) );
			$errors = array_merge( $errors, self::validate_fields( $action['fields'] ?? [], $label ) );
		}

		// --- Triggers ------------------------------------------------------
		$trigger_keys = [];
		foreach ( $triggers as $i => $trigger ) {
			$label = 'Trigger #' . ( (int) $i + 1 );
			if ( ! is_array( $trigger ) ) {
				$errors[] = $label . ' is malformed.';
				continue;
			}
			if ( empty( $trigger['key'] ) ) {
				$errors[] = $label . ' is missing a key.';
			} elseif ( self::sanitize_key( (string) $trigger['key'] ) !== (string) $trigger['key'] ) {
				$errors[] = $label . ' key must contain only lowercase letters, numbers and underscores.';
			} elseif ( isset( $trigger_keys[ (string) $trigger['key'] ] ) ) {
				$errors[] = $label . ' duplicates the key used by Trigger #' . $trigger_keys[ (string) $trigger['key'] ] . '.';
			} else {
				$trigger_keys[ (string) $trigger['key'] ] = (int) $i + 1;
			}
			if ( '' === trim( (string) ( $trigger['label'] ?? '' ) ) ) {
				$errors[] = $label . ' needs a label (shown in the Trigger Type dropdown).';
			}
			$mode = isset( $trigger['mode'] ) ? (string) $trigger['mode'] : '';
			if ( ! in_array( $mode, self::TRIGGER_MODES, true ) ) {
				$errors[] = $label . ' must have a mode of "polling" or "webhook".';
				continue;
			}
			if ( 'polling' === $mode ) {
				$polling = isset( $trigger['polling'] ) && is_array( $trigger['polling'] ) ? $trigger['polling'] : [];
				$errors  = array_merge( $errors, self::validate_request( $polling['request'] ?? null, $base_url, $label . ' polling' ) );
				// items_path may be an empty string (the response body IS the list),
				// but the key must be present so the intent is explicit.
				if ( ! array_key_exists( 'items_path', $polling ) ) {
					$errors[] = $label . ' polling requires an items_path (use "" when the response is itself the list).';
				}
				if ( empty( $polling['dedupe_key'] ) ) {
					$errors[] = $label . ' polling requires a dedupe_key.';
				}
			}
			$errors = array_merge( $errors, self::validate_fields( $trigger['fields'] ?? [], $label ) );
		}//end foreach

		return $errors;
	}

	/**
	 * Validation rules for a `kind: local` app (WP hooks + PHP callables).
	 *
	 * @return string[]
	 */
	protected static function validate_local( array $manifest ): array {
		$errors   = [];
		$actions  = isset( $manifest['actions'] ) && is_array( $manifest['actions'] ) ? $manifest['actions'] : [];
		$triggers = isset( $manifest['triggers'] ) && is_array( $manifest['triggers'] ) ? $manifest['triggers'] : [];

		if ( empty( $actions ) && empty( $triggers ) ) {
			$errors[] = 'A custom app needs at least one action or trigger.';
		}

		$action_keys = [];
		foreach ( $actions as $i => $action ) {
			$label = 'Action #' . ( (int) $i + 1 );
			if ( ! is_array( $action ) || empty( $action['key'] ) ) {
				$errors[] = $label . ' is missing a key.';
				continue;
			}
			if ( self::sanitize_key( (string) $action['key'] ) !== (string) $action['key'] ) {
				$errors[] = $label . ' key must contain only lowercase letters, numbers and underscores.';
			} elseif ( isset( $action_keys[ (string) $action['key'] ] ) ) {
				$errors[] = $label . ' duplicates the key used by Action #' . $action_keys[ (string) $action['key'] ] . '.';
			} else {
				$action_keys[ (string) $action['key'] ] = (int) $i + 1;
			}
			if ( '' === trim( (string) ( $action['label'] ?? '' ) ) ) {
				$errors[] = $label . ' needs a label (shown in the Action Type dropdown).';
			}

			$handler = isset( $action['handler'] ) && is_array( $action['handler'] ) ? $action['handler'] : [];
			$type    = (string) ( $handler['type'] ?? '' );
			if ( ! in_array( $type, self::HANDLER_TYPES, true ) ) {
				$errors[] = $label . ' needs a handler type of function, do_action, or apply_filters.';
			} elseif ( 'function' === $type && empty( $handler['callable'] ) ) {
				$errors[] = $label . ' function handler needs a function name.';
			} elseif ( ( 'do_action' === $type || 'apply_filters' === $type ) && empty( $handler['name'] ) ) {
				$errors[] = $label . ' ' . $type . ' handler needs a hook name.';
			}

			$errors = array_merge( $errors, self::validate_fields( $action['fields'] ?? [], $label ) );
		}//end foreach

		$trigger_keys = [];
		foreach ( $triggers as $i => $trigger ) {
			$label = 'Trigger #' . ( (int) $i + 1 );
			if ( ! is_array( $trigger ) || empty( $trigger['key'] ) ) {
				$errors[] = $label . ' is missing a key.';
				continue;
			}
			if ( self::sanitize_key( (string) $trigger['key'] ) !== (string) $trigger['key'] ) {
				$errors[] = $label . ' key must contain only lowercase letters, numbers and underscores.';
			} elseif ( isset( $trigger_keys[ (string) $trigger['key'] ] ) ) {
				$errors[] = $label . ' duplicates the key used by Trigger #' . $trigger_keys[ (string) $trigger['key'] ] . '.';
			} else {
				$trigger_keys[ (string) $trigger['key'] ] = (int) $i + 1;
			}
			if ( '' === trim( (string) ( $trigger['label'] ?? '' ) ) ) {
				$errors[] = $label . ' needs a label (shown in the Trigger Type dropdown).';
			}
			if ( empty( $trigger['hook'] ) ) {
				$errors[] = $label . ' needs a WordPress hook name to listen on.';
			}
			$errors = array_merge( $errors, self::validate_fields( $trigger['fields'] ?? [], $label ) );
		}

		return $errors;
	}

	/**
	 * Validate one request template ({ method, path|url, headers, body }).
	 *
	 * @return string[]
	 */
	protected static function validate_request( $request, string $base_url, string $label ): array {
		$errors = [];

		if ( ! is_array( $request ) ) {
			$errors[] = $label . ' is missing a request definition.';
			return $errors;
		}

		$method = isset( $request['method'] ) ? strtoupper( (string) $request['method'] ) : 'GET';
		if ( ! in_array( $method, self::HTTP_METHODS, true ) ) {
			$errors[] = sprintf( '%s uses an unsupported HTTP method "%s".', $label, $method );
		}

		$path = isset( $request['path'] ) ? (string) $request['path'] : '';
		$url  = isset( $request['url'] ) ? (string) $request['url'] : '';

		if ( '' === $path && '' === $url ) {
			$errors[] = $label . ' needs a path or url.';
		}

		// An absolute URL must itself be safe. A relative path needs a base_url to
		// resolve against — but template placeholders ({{...}}) may only appear in
		// the host later, so we only hard-fail when there is neither.
		if ( '' !== $url && ! self::has_placeholder( $url ) && ! self::is_safe_url( $url ) ) {
			$errors[] = $label . ' has an invalid absolute URL.';
		}
		if ( '' === $url && '' !== $path && '' === $base_url ) {
			$errors[] = $label . ' uses a relative path but the app has no base URL.';
		}

		return $errors;
	}

	/**
	 * Shallow validation of a field-schema array (the same vocabulary the node
	 * config renderer understands).
	 *
	 * @return string[]
	 */
	protected static function validate_fields( $fields, string $label ): array {
		if ( empty( $fields ) ) {
			return [];
		}
		if ( ! is_array( $fields ) ) {
			return [ $label . ' has a malformed fields list.' ];
		}

		$errors = [];
		$field_keys = [];
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['key'] ) ) {
				$errors[] = $label . ' has a field with no key.';
			} elseif ( self::sanitize_key( (string) $field['key'] ) !== (string) $field['key'] ) {
				$errors[] = $label . ' has a field key that must contain only lowercase letters, numbers and underscores.';
			} elseif ( isset( $field_keys[ (string) $field['key'] ] ) ) {
				$errors[] = $label . ' has duplicate field key "' . (string) $field['key'] . '".';
			} else {
				$field_keys[ (string) $field['key'] ] = true;
			}
		}
		return $errors;
	}

	/**
	 * True when the string contains a {{ template }} placeholder.
	 */
	protected static function has_placeholder( string $value ): bool {
		return (bool) preg_match( '/\{\{.*?\}\}/', $value );
	}

	/**
	 * A URL we are willing to store: parseable, http/https only. Runtime SSRF
	 * blocking (private ranges etc.) is enforced again at request time by the
	 * HTTP client, so this is the first of two gates.
	 */
	public static function is_safe_url( string $url ): bool {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}
		return in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true );
	}
}
