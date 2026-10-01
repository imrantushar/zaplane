<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches a connected site that runs no Zaplane.
 *
 * WordPress exposes no route that subscribes to a hook, so a site without
 * Zaplane cannot be asked what fired on it. What it does expose is the core
 * REST API, and that answers "what changed": posts and media carry a modified
 * date, comments, users and terms carry rising IDs. This trait polls those
 * routes on the schedule the trigger bridge keeps, diffs the answers against
 * what the last poll saw, and turns the difference into the same trigger
 * events a paired site would have sent.
 *
 * The diffing is deliberately pure — items in, state in, events and state
 * out — so it can be tested without a connected site or HTTP.
 */
trait RestWatchTrait {

	/**
	 * Which way the connection runs: a Zaplane pairing or the core REST API.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 */
	public static function remote_connection_mode( array $credentials ): string {
		return self::remote_mode( $credentials );
	}

	/**
	 * Poll the connected site's core REST API for what changed.
	 *
	 * The first call establishes the baseline and fires nothing, whatever is
	 * already on the site. Every call after that hands back the trigger events
	 * that fired since, with the state to keep for the next one.
	 *
	 * @param array<string,mixed>            $credentials Connection credentials.
	 * @param array<int,array<string,mixed>> $specs       Trigger steps to watch.
	 * @param array<string,mixed>            $state       State left by the last poll.
	 * @return array{ok:bool,state:array<string,mixed>,events:array<int,array<string,mixed>>,error:string}
	 */
	public static function rest_watch( array $credentials, array $specs, array $state ): array {
		$state = is_array( $state ) ? $state : [];
		$poll  = (string) ( $state['poll'] ?? '' );
		$now   = self::rest_now();
		$posts = [];
		$media = [];
		$comments = [];
		$users = [];
		$terms = [];

		foreach ( $specs as $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}

			$event  = (string) ( $spec['event'] ?? '' );
			$config = is_array( $spec['config'] ?? null ) ? $spec['config'] : [];

			if ( in_array( $event, [ 'publish_post', 'post_updated', 'save_post', 'wp_trash_post', 'transition_post_status' ], true ) ) {
				$type = (string) ( $config['post_type'] ?? '' );

				if ( 'media' === $type ) {
					$type = 'attachment';
				}

				$posts[ '' !== $type ? $type : 'post' ][] = $spec;
				continue;
			}

			if ( in_array( $event, [ 'add_attachment', 'edit_attachment' ], true ) ) {
				$media[] = $spec;
				continue;
			}

			if ( in_array( $event, [ 'comment_post', 'wp_insert_comment' ], true ) ) {
				$comments[] = $spec;
				continue;
			}

			if ( 'user_register' === $event ) {
				$users[] = $spec;
				continue;
			}

			if ( in_array( $event, [ 'create_term', 'created_term' ], true ) ) {
				$taxonomy = (string) ( $config['taxonomy'] ?? '' );
				$terms[ '' !== $taxonomy ? $taxonomy : 'category' ][] = $spec;
			}
		}//end foreach

		$events = [];

		foreach ( $posts as $type => $type_specs ) {
			try {
				$base = self::remote_post_base( $credentials, $type );
			} catch ( \Exception $e ) {
				// A post type the connected site does not expose over REST has
				// nothing to watch.
				continue;
			}

			[ $items, $status ] = self::rest_fetch(
				$credentials,
				'wp/v2/' . $base,
				[
					'context'  => 'edit',
					'orderby'  => 'modified',
					'order'    => 'desc',
					'per_page' => 100,
				]
			);

			if ( null === $items ) {
				continue;
			}

			$type_state = is_array( $state['posts'][ $type ] ?? null ) ? $state['posts'][ $type ] : [];
			[ $type_events, $type_state ] = self::rest_diff_posts( $items, $type_specs, $type_state, $poll );

			$events                  = array_merge( $events, $type_events );
			$state['posts'][ $type ] = $type_state;
		}//end foreach

		if ( $media ) {
			[ $items, $status ] = self::rest_fetch(
				$credentials,
				'wp/v2/media',
				[
					'context'  => 'edit',
					'orderby'  => 'modified',
					'order'    => 'desc',
					'per_page' => 100,
				]
			);

			if ( null !== $items ) {
				$media_state = is_array( $state['media'] ?? null ) ? $state['media'] : [];
				[ $media_events, $media_state ] = self::rest_diff_media( $items, $media, $media_state, $poll );

				$events         = array_merge( $events, $media_events );
				$state['media'] = $media_state;
			}
		}

		if ( $comments ) {
			[ $items, $status ] = self::rest_fetch(
				$credentials,
				'wp/v2/comments',
				[
					'context'  => 'edit',
					'orderby'  => 'id',
					'order'    => 'desc',
					'per_page' => 100,
				]
			);

			if ( null !== $items ) {
				[ $comment_events, $comment_id ] = self::rest_diff_ids(
					$items,
					$comments,
					(int) ( $state['comment_id'] ?? 0 ),
					[ self::class, 'rest_map_comment' ]
				);

				$events              = array_merge( $events, $comment_events );
				$state['comment_id'] = $comment_id;
			}
		}//end if

		if ( $users ) {
			[ $items, $status ] = self::rest_fetch(
				$credentials,
				'wp/v2/users',
				[
					'context'  => 'edit',
					'orderby'  => 'id',
					'order'    => 'desc',
					'per_page' => 100,
				]
			);

			if ( null !== $items ) {
				[ $user_events, $user_id ] = self::rest_diff_ids(
					$items,
					$users,
					(int) ( $state['user_id'] ?? 0 ),
					[ self::class, 'rest_map_user_row' ]
				);

				$events            = array_merge( $events, $user_events );
				$state['user_id']  = $user_id;
			}
		}//end if

		foreach ( $terms as $taxonomy => $tax_specs ) {
			try {
				$base = self::remote_tax_base( $credentials, $taxonomy );
			} catch ( \Exception $e ) {
				continue;
			}

			[ $items, $status ] = self::rest_fetch(
				$credentials,
				'wp/v2/' . $base,
				[
					'context'  => 'edit',
					'orderby'  => 'id',
					'order'    => 'desc',
					'per_page' => 100,
				]
			);

			if ( null === $items ) {
				continue;
			}

			[ $term_events, $term_id ] = self::rest_diff_ids(
				$items,
				$tax_specs,
				(int) ( $state['terms'][ $taxonomy ] ?? 0 ),
				[ self::class, 'remote_map_term' ]
			);

			$events                       = array_merge( $events, $term_events );
			$state['terms'][ $taxonomy ]  = $term_id;
		}//end foreach

		$state['poll'] = $now;

		return [
			'ok'     => true,
			'state'  => $state,
			'events' => $events,
			'error'  => '',
		];
	}

	/**
	 * Diff one post type's REST collection against the last poll.
	 *
	 * A post first seen here is new: it publishes publish_post when it went
	 * out published and save_post whatever its status. A post already known
	 * whose status moved fires the transition, and publish or trash events on
	 * top where the new status is one of those. A known post touched without
	 * moving status fires post_updated and save_post.
	 *
	 * @param array<int,array<string,mixed>> $items  REST posts, newest change first.
	 * @param array<int,array<string,mixed>> $specs  Trigger steps bound to this type.
	 * @param array<string,mixed>            $state  State left by the last poll.
	 * @param string                         $poll   When the last poll ran, GMT.
	 * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>}
	 */
	public static function rest_diff_posts( array $items, array $specs, array $state, string $poll ): array {
		$statuses = is_array( $state['statuses'] ?? null ) ? $state['statuses'] : [];
		$events   = [];
		$next     = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$id       = (int) $item['id'];
			$status   = (string) ( $item['status'] ?? '' );
			$date     = self::rest_time( (string) ( $item['date_gmt'] ?? '' ) );
			$modified = self::rest_time( (string) ( $item['modified_gmt'] ?? '' ) );

			$next[ $id ] = $status;

			$known  = isset( $statuses[ $id ] );
			$old    = $known ? (string) $statuses[ $id ] : '';
			$is_new = '' !== $poll && ! $known && '' !== $date && $date > $poll;

			if ( '' === $poll ) {
				// The first poll only records what is there. Nothing that sat
				// on the site before the watch began may fire a workflow.
				continue;
			}

			$changed = $known && $old !== $status;
			$touched = ! $is_new && '' !== $modified && $modified > $poll && ! $changed;

			foreach ( $specs as $spec ) {
				if ( ! is_array( $spec ) ) {
					continue;
				}

				$config = is_array( $spec['config'] ?? null ) ? $spec['config'] : [];
				$only   = (string) ( $config['post'] ?? '' );

				if ( '' !== $only && 'any' !== $only && (int) $only !== $id ) {
					continue;
				}

				if ( $is_new ) {
					if ( 'publish_post' === $spec['event'] && 'publish' === $status ) {
						$events[] = self::rest_event( $spec, self::remote_map_post( $item ) );
					}

					if ( 'save_post' === $spec['event'] ) {
						$events[] = self::rest_event( $spec, self::remote_map_post( $item ) );
					}

					continue;
				}

				if ( $changed ) {
					if ( 'transition_post_status' === $spec['event'] ) {
						$events[] = self::rest_event(
							$spec,
							array_merge( self::remote_map_post( $item ), [
								'old_status' => $old,
								'new_status' => $status,
							] )
						);
					}

					if ( 'publish_post' === $spec['event'] && 'publish' === $status ) {
						$events[] = self::rest_event( $spec, self::remote_map_post( $item ) );
					}

					if ( 'wp_trash_post' === $spec['event'] && 'trash' === $status ) {
						$events[] = self::rest_event( $spec, self::remote_map_post( $item ) );
					}

					continue;
				}//end if

				if ( $touched && in_array( $spec['event'], [ 'post_updated', 'save_post' ], true ) ) {
					$events[] = self::rest_event( $spec, self::remote_map_post( $item ) );
				}
			}//end foreach
		}//end foreach

		$state['statuses'] = count( $next ) > 500
			? array_slice( $next, -500, null, true )
			: $next;

		return [ $events, $state ];
	}

	/**
	 * Diff the media collection: rising IDs are new attachments, a modified
	 * date past the last poll is an edited one.
	 *
	 * @param array<int,array<string,mixed>> $items  REST attachments, newest change first.
	 * @param array<int,array<string,mixed>> $specs  Trigger steps bound to media.
	 * @param array<string,mixed>            $state  State left by the last poll.
	 * @param string                         $poll   When the last poll ran, GMT.
	 * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>}
	 */
	public static function rest_diff_media( array $items, array $specs, array $state, string $poll ): array {
		$last_id = (int) ( $state['id'] ?? 0 );
		$events  = [];
		$max     = $last_id;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$id       = (int) $item['id'];
			$modified = self::rest_time( (string) ( $item['modified_gmt'] ?? '' ) );
			$max      = max( $max, $id );

			if ( '' === $poll ) {
				continue;
			}

			$is_new = $id > $last_id;

			foreach ( $specs as $spec ) {
				if ( ! is_array( $spec ) ) {
					continue;
				}

				if ( $is_new && 'add_attachment' === $spec['event'] ) {
					$events[] = self::rest_event( $spec, self::remote_map_media( $item ) );
					continue;
				}

				if ( ! $is_new && 'edit_attachment' === $spec['event'] && '' !== $modified && $modified > $poll ) {
					$events[] = self::rest_event( $spec, self::remote_map_media( $item ) );
				}
			}
		}//end foreach

		$state['id'] = $max;

		return [ $events, $state ];
	}

	/**
	 * Diff a rising-ID collection — comments, users, terms — against the last
	 * poll.
	 *
	 * @param array<int,array<string,mixed>> $items REST items, highest ID first.
	 * @param array<int,array<string,mixed>> $specs Trigger steps bound to this collection.
	 * @param int                            $last_id Highest ID seen by the last poll.
	 * @param callable                       $map    Turns a REST item into the payload shape.
	 * @return array{0:array<int,array<string,mixed>>,1:int}
	 */
	public static function rest_diff_ids( array $items, array $specs, int $last_id, callable $map ): array {
		$events = [];
		$max    = $last_id;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = (int) ( $item['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$max = max( $max, $id );

			// The first poll records where the IDs stand; it fires nothing for
			// what was already there.
			if ( $last_id <= 0 || $id <= $last_id ) {
				continue;
			}

			$payload = $map( $item );

			if ( ! is_array( $payload ) || array() === $payload ) {
				continue;
			}

			foreach ( $specs as $spec ) {
				if ( is_array( $spec ) ) {
					$events[] = self::rest_event( $spec, $payload );
				}
			}
		}//end foreach

		return [ $events, $max ];
	}

	/**
	 * One REST item as the comment row the local comment triggers hand out.
	 *
	 * @param array<string,mixed> $item REST comment payload.
	 * @return array<string,mixed>
	 */
	public static function rest_map_comment( array $item ): array {
		return [
			'comment_ID'           => (int) ( $item['id'] ?? 0 ),
			'comment_post_ID'      => (int) ( $item['post'] ?? 0 ),
			'comment_author'       => (string) ( $item['author_name'] ?? '' ),
			'comment_author_email' => (string) ( $item['author_email'] ?? '' ),
			'comment_author_url'   => (string) ( $item['author_url'] ?? '' ),
			'comment_author_IP'    => (string) ( $item['author_ip'] ?? '' ),
			'comment_date'         => self::rest_time( (string) ( $item['date'] ?? '' ) ),
			'comment_date_gmt'     => self::rest_time( (string) ( $item['date_gmt'] ?? '' ) ),
			'comment_content'      => self::remote_text( $item['content'] ?? '' ),
			'comment_karma'        => (int) ( $item['karma'] ?? 0 ),
			'comment_approved'     => 'approved' === ( $item['status'] ?? '' ) ? '1' : '0',
			'comment_agent'        => (string) ( $item['author_user_agent'] ?? '' ),
			'comment_type'         => (string) ( $item['type'] ?? 'comment' ),
			'comment_parent'       => (int) ( $item['parent'] ?? 0 ),
			'user_id'              => (int) ( $item['author'] ?? 0 ),
		];
	}

	/**
	 * One REST user as the row the local user triggers hand out.
	 *
	 * @param array<string,mixed> $item REST user payload.
	 * @return array<string,mixed>
	 */
	public static function rest_map_user_row( array $item ): array {
		return [
			'ID'              => (int) ( $item['id'] ?? 0 ),
			'user_login'      => (string) ( $item['username'] ?? '' ),
			'user_nicename'   => (string) ( $item['slug'] ?? '' ),
			'user_email'      => (string) ( $item['email'] ?? '' ),
			'user_url'        => (string) ( $item['url'] ?? '' ),
			'user_registered' => self::rest_time( (string) ( $item['registered_date'] ?? '' ) ),
			'user_status'     => 0,
			'display_name'    => (string) ( $item['name'] ?? '' ),
			'roles'           => is_array( $item['roles'] ?? null ) ? $item['roles'] : [],
		];
	}

	/**
	 * One event shaped the way the trigger bridge dispatches it.
	 *
	 * @param array<string,mixed> $spec    Trigger step the event belongs to.
	 * @param array<string,mixed> $payload Resolved payload for the workflow.
	 * @return array<string,mixed>
	 */
	private static function rest_event( array $spec, array $payload ): array {
		return [
			'app'     => 'wordpress',
			'event'   => (string) ( $spec['event'] ?? '' ),
			'hook'    => (string) ( $spec['hook'] ?? '' ),
			'payload' => $payload,
		];
	}

	/**
	 * Fetch a collection from the connected site, or null when the route is
	 * not there (a post type or taxonomy the site does not expose).
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $route       Route relative to /wp-json/.
	 * @param array<string,mixed> $query       Query string arguments.
	 * @return array{0:?array<int,array<string,mixed>>,1:int}
	 */
	private static function rest_fetch( array $credentials, string $route, array $query ): array {
		try {
			[ $items, $status ] = self::remote_request( $credentials, 'GET', $route, null, $query, [], true );
		} catch ( \Exception $e ) {
			return [ null, 0 ];
		}

		if ( 200 !== $status || ! is_array( $items ) ) {
			return [ null, (int) $status ];
		}

		return [ $items, $status ];
	}

	/**
	 * A REST date as the GMT MySQL time the diffs compare.
	 *
	 * @param string $value Date from a REST payload.
	 */
	private static function rest_time( string $value ): string {
		return str_replace( 'T', ' ', $value );
	}

	/**
	 * Now, in GMT MySQL time.
	 */
	private static function rest_now(): string {
		return function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
	}
}
