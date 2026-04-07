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
		}
	}

	if ( ! class_exists( 'GemCrm\Database\Models\Tag' ) ) {
		class Tag {

			/** Returns a predictable list of tags when $all = true. */
			public static function index( array $params = [], ?int $user_id = null, bool $all = false ): array {
				return [
					[ 'id' => 1, 'name' => 'VIP' ],
					[ 'id' => 2, 'name' => 'Newsletter' ],
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

			/** Returns a predictable list of lists when $all = true. */
			public static function index( array $params = [], ?int $user_id = null, bool $all = false ): array {
				return [
					[ 'id' => 10, 'name' => 'Subscribers' ],
					[ 'id' => 20, 'name' => 'Buyers' ],
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
}
