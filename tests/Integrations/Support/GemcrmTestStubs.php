<?php

/**
 * GemCRM stub classes for unit testing.
 *
 * These replace the real GemCRM plugin classes so the integration can be
 * tested without the plugin installed.
 */

namespace GemCrm\Database\Models {

	if ( ! class_exists( 'GemCrm\Database\Models\Contact' ) ) {
		class Contact {

			/** Simulates a successful create — returns a fake contact array. */
			public static function create( array $data ): array {
				return array_merge( [ 'id' => 42 ], $data );
			}

			/** Simulates a successful update — returns the merged contact. */
			public static function update( int $contact_id, array $data ): array {
				return array_merge( [ 'id' => $contact_id ], $data );
			}

			/** Simulates a successful delete — returns true. */
			public static function delete( int $contact_id ): bool {
				return $contact_id > 0;
			}

			/** Returns a predictable paginated contact list. */
			public static function index( array $params = [], ?int $user_id = null ): array {
				return [
					'records' => [
						[ 'id' => 1, 'first_name' => 'John', 'last_name' => 'Doe',  'email' => 'john@example.com' ],
						[ 'id' => 2, 'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com' ],
					],
				];
			}
		}
	}

	if ( ! class_exists( 'GemCrm\Database\Models\Tag' ) ) {
		class Tag {

			/** Returns a predictable list of tags. Uses 'title' to match query_tags(). */
			public static function index( array $params = [], ?int $user_id = null ): array {
				return [
					'records' => [
						[ 'id' => 1, 'title' => 'VIP' ],
						[ 'id' => 2, 'title' => 'Newsletter' ],
					],
				];
			}

			public static function attach_single( int $contact_id, int $tag_id ): void {
				// no-op in tests
			}

			public static function detach_single( int $contact_id, int $tag_id ): void {
				// no-op in tests
			}
		}
	}

	if ( ! class_exists( 'GemCrm\Database\Models\ListModel' ) ) {
		class ListModel {

			/** Returns a predictable list of lists. Uses 'title' to match query_lists(). */
			public static function index( array $params = [], ?int $user_id = null ): array {
				return [
					'records' => [
						[ 'id' => 10, 'title' => 'Subscribers' ],
						[ 'id' => 20, 'title' => 'Buyers' ],
					],
				];
			}

			public static function attach_single( int $contact_id, int $list_id ): void {
				// no-op in tests
			}

			public static function detach_single( int $contact_id, int $list_id ): void {
				// no-op in tests
			}
		}
	}

	if ( ! class_exists( 'GemCrm\Database\Models\Campaign' ) ) {
		class Campaign {

			/**
			 * Returns a paginated campaign list.
			 * Supports 'per_page' and 'search' params to mirror the real model.
			 */
			public static function index( array $params = [] ): array {
				$all = [
					[ 'id' => 1, 'title' => 'Welcome Series'   ],
					[ 'id' => 2, 'title' => 'Black Friday Sale' ],
					[ 'id' => 3, 'title' => 'Re-engagement'    ],
				];

				if ( ! empty( $params['search'] ) ) {
					$search = strtolower( $params['search'] );
					$all = array_values( array_filter( $all, fn( $c ) => false !== \strpos( \strtolower( $c['title'] ), $search ) ) );
				}

				$per_page = (int) ( $params['per_page'] ?? 10 );
				return [
					'records' => array_slice( $all, 0, $per_page ),
				];
			}

			/**
			 * Simulates updating a campaign — returns the merged campaign or null for invalid IDs.
			 */
			public static function update( int $campaign_id, array $data, ?int $user_id = null ): ?array {
				if ( $campaign_id <= 0 ) {
					return null;
				}
				return array_merge( [ 'id' => $campaign_id ], $data );
			}
		}
	}
}

namespace GemCrm\Classes {

	if ( ! class_exists( 'GemCrm\Classes\EmailSender' ) ) {

		/**
		 * Stub for GemCrm\Classes\EmailSender.
		 *
		 * Records the last send call so tests can assert on it.
		 * Uses a fluent interface matching the real class API.
		 */
		class EmailSender {

			public static array $last_send = [];

			private string  $subject;
			private string  $body;
			private ?string $pre_header;
			private ?string $from_email  = null;
			private ?string $from_name   = null;
			private ?string $reply_to    = null;
			private ?string $reply_to_name = null;
			private string  $to          = '';

			public function __construct( string $subject, string $body, ?string $pre_header = null ) {
				$this->subject    = $subject;
				$this->body       = $body;
				$this->pre_header = $pre_header;
			}

			public function from( ?string $email = null, ?string $name = null ): self {
				$this->from_email = $email;
				$this->from_name  = $name;
				return $this;
			}

			public function reply_to( ?string $email = null, ?string $name = null ): self {
				$this->reply_to      = $email;
				$this->reply_to_name = $name;
				return $this;
			}

			public function to( string $to ): self {
				$this->to = $to;
				return $this;
			}

			/** Simulates a successful send and records arguments for test assertions. */
			public function send(): bool {
				self::$last_send = [
					'subject'    => $this->subject,
					'body'       => $this->body,
					'pre_header' => $this->pre_header,
					'from_email' => $this->from_email,
					'from_name'  => $this->from_name,
					'reply_to'   => $this->reply_to,
					'to'         => $this->to,
				];
				return true;
			}
		}
	}
}
