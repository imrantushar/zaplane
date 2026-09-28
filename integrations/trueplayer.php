<?php
/**
 * TruePlayer integration for Zaplane.
 *
 * Triggers are wired to hooks that TruePlayer 1.6.0 really fires:
 *  - trueplayer/event/{name} (Events::emit) for viewer/quiz/subscriber events.
 *  - WordPress core post hooks for media (tp_video) and playlist (tp_playlist).
 *
 * @package Zaplane\Integrations
 */

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Class Trueplayer
 */
class Trueplayer extends IntegrationBase {

	const VIDEO_META    = '_trueplayer_config';
	const PLAYLIST_META = '_trueplayer_playlist';
	const PLAYLIST_TYPE = 'tp_playlist';
	const SOURCE_TYPES  = [ 'self', 'youtube', 'vimeo', 'url' ];
	const STATUSES      = [ 'publish', 'draft', 'private', 'pending' ];

	public static function get_slug(): string {
		return 'trueplayer';
	}

	public static function get_name(): string {
		return 'TruePlayer';
	}

	public static function get_icon(): string {
		return 'trueplayer.svg';
	}

	private static function active(): bool {
		return defined( 'TRUEPLAYER_VERSION' ) || class_exists( '\TruePlayer' );
	}

	/*
	 ---------------------------------------------------------------------
	 * Triggers
	 ------------------------------------------------------------------- */

	public static function get_triggers(): array {
		$event = static function ( string $label, string $name ): array {
			return [
				'label'         => $label,
				'hook'          => 'trueplayer/event/' . $name,
				'accepted_args' => 1,
			];
		};

		return [
			// Media (tp_video).
			'media_created'      => [
				'label'         => 'Media Created',
				'hook'          => 'save_post_tp_video',
				'accepted_args' => 3,
			],
			'media_updated'      => [
				'label'         => 'Media Updated',
				'hook'          => 'save_post_tp_video',
				'accepted_args' => 3,
			],
			'media_deleted'      => [
				'label'         => 'Media Deleted',
				'hook'          => 'before_delete_post',
				'accepted_args' => 2,
			],

			// Playlist (tp_playlist).
			'playlist_created'   => [
				'label'         => 'Playlist Created',
				'hook'          => 'save_post_tp_playlist',
				'accepted_args' => 3,
			],
			'playlist_updated'   => [
				'label'         => 'Playlist Updated',
				'hook'          => 'save_post_tp_playlist',
				'accepted_args' => 3,
			],
			'playlist_deleted'   => [
				'label'         => 'Playlist Deleted',
				'hook'          => 'before_delete_post',
				'accepted_args' => 2,
			],

			// Viewer events (TruePlayer event bus).
			'video_started'      => $event( 'Video Started', 'view.started' ),
			'video_watched'      => $event( 'Video Watched', 'view.completed' ),
			'video_completed'    => $event( 'Video Completed', 'view.completed' ),
			'video_percentage'   => $event( 'Video Reached Percentage', 'progress.milestone' ),
			'user_percentage'    => $event( 'User Watched Specific Percentage', 'progress.milestone' ),
			'milestone_reached'  => $event( 'Watch Milestone Reached', 'progress.milestone' ),
			'checkpoint_passed'  => $event( 'Checkpoint Passed', 'checkpoint.passed' ),
			'checkpoint_failed'  => $event( 'Checkpoint Failed', 'checkpoint.failed' ),
			'quiz_passed'        => $event( 'Quiz Passed', 'quiz.passed' ),
			'quiz_failed'        => $event( 'Quiz Failed', 'quiz.failed' ),
			'video_locked'       => $event( 'Video Locked', 'video.locked' ),
			'video_unlocked'     => $event( 'Video Unlocked', 'video.unlocked' ),
			'subscriber_added'   => $event( 'Subscriber Added', 'subscriber.added' ),
			'email_captured'     => $event( 'Email Captured', 'subscriber.added' ),
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		switch ( $trigger ) {
			case 'video_percentage':
			case 'user_percentage':
			case 'milestone_reached':
				return [
					[
						'key'      => 'percent',
						'label'    => 'Milestone',
						'type'     => 'select',
						'required' => false,
						'default'  => '',
						'options'  => [
							[
								'value' => '',
								'label' => 'Any milestone',
							],
							[
								'value' => '25',
								'label' => '25%',
							],
							[
								'value' => '50',
								'label' => '50%',
							],
							[
								'value' => '75',
								'label' => '75%',
							],
							[
								'value' => '100',
								'label' => '100%',
							],
						],
						'help'     => 'TruePlayer fires milestones at 25, 50, 75 and 100 percent. Leave on "Any" to run on all of them.',
					],
				];
		}
		return [];
	}

	/**
	 * Build the trigger output, or return false to skip this workflow.
	 *
	 * @param array $node Trigger node.
	 * @param array $args Hook arguments.
	 * @return array|false
	 */
	public static function resolve_trigger( array $node, array $args ) {
		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? '' ) );

		switch ( $event ) {
			case 'media_created':
			case 'media_updated':
			case 'playlist_created':
			case 'playlist_updated':
				return self::resolve_post_save( $event, $args );

			case 'media_deleted':
			case 'playlist_deleted':
				return self::resolve_post_delete( $event, $args );
		}

		$payload = $args[0] ?? [];
		if ( ! is_array( $payload ) ) {
			return false;
		}

		// Milestone filter (exact match on 25 / 50 / 75 / 100).
		if ( in_array( $event, [ 'video_percentage', 'user_percentage', 'milestone_reached' ], true ) ) {
			$cfg    = $node['data']['config'] ?? [];
			$filter = isset( $cfg['percent'] ) ? trim( (string) $cfg['percent'] ) : '';
			if ( '' !== $filter && (int) ( $payload['milestone'] ?? 0 ) !== (int) $filter ) {
				return false;
			}
		}

		$subject = isset( $payload['subject'] ) && is_array( $payload['subject'] ) ? $payload['subject'] : [];
		$is_user = 'user' === ( $subject['type'] ?? '' );

		$out = array_merge(
			$payload,
			[
				'video_id'         => (int) ( $payload['video_id'] ?? 0 ),
				'video_title'      => (string) ( $payload['video_title'] ?? '' ),
				'user_id'          => $is_user ? (int) ( $subject['id'] ?? 0 ) : 0,
				'user_email'       => (string) ( $subject['email'] ?? ( $payload['email'] ?? '' ) ),
				'user_name'        => (string) ( $subject['name'] ?? ( $payload['name'] ?? '' ) ),
				'user_type'        => (string) ( $subject['type'] ?? 'guest' ),
				'coverage_percent' => (float) ( $payload['coverage_percent'] ?? 0 ),
				'milestone'        => (int) ( $payload['milestone'] ?? 0 ),
				'provider'         => (string) ( $payload['provider'] ?? '' ),
			]
		);

		if ( isset( $payload['attempt_no'] ) ) {
			$out['attempts'] = (int) $payload['attempt_no'];
		}

		return $out;
	}

	/**
	 * Handle save_post_{type}: ( $post_id, $post, $update ).
	 */
	private static function resolve_post_save( string $event, array $args ) {
		$post   = $args[1] ?? null;
		$update = ! empty( $args[2] );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		if ( 0 === strpos( $event, 'media_' ) ) {
			$type = self::video_type();
		} else {
			$type = self::PLAYLIST_TYPE;
		}
		if ( $type !== $post->post_type ) {
			return false;
		}
		if ( in_array( $post->post_status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
			return false;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return false;
		}

		$is_created_event = '_created' === substr( $event, -8 );
		if ( $is_created_event === $update ) {
			return false;
		}

		return 0 === strpos( $event, 'media_' )
			? self::media_payload( $post )
			: self::playlist_payload( $post );
	}

	/**
	 * Handle before_delete_post: ( $post_id, $post ).
	 */
	private static function resolve_post_delete( string $event, array $args ) {
		$post_id = (int) ( $args[0] ?? 0 );
		$post    = $args[1] ?? get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$type = 0 === strpos( $event, 'media_' ) ? self::video_type() : self::PLAYLIST_TYPE;
		if ( $type !== $post->post_type ) {
			return false;
		}

		return [
			'deleted_id' => (int) $post->ID,
			'id'         => (int) $post->ID,
			'title'      => (string) $post->post_title,
		];
	}

	private static function media_payload( \WP_Post $post ): array {
		$tags = wp_get_object_terms( $post->ID, self::video_taxonomy(), [ 'fields' => 'names' ] );

		return [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'status'    => (string) $post->post_status,
			'shortcode' => sprintf( '[trueplayer id="%d"]', $post->ID ),
			'tags'      => is_wp_error( $tags ) ? [] : array_values( $tags ),
			'modified'  => (string) $post->post_modified_gmt,
			'config'    => self::get_json_meta( $post->ID, self::VIDEO_META ),
		];
	}

	private static function playlist_payload( \WP_Post $post ): array {
		return [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'status'    => (string) $post->post_status,
			'shortcode' => sprintf( '[trueplayer_playlist id="%d"]', $post->ID ),
			'modified'  => (string) $post->post_modified_gmt,
			'config'    => self::get_json_meta( $post->ID, self::PLAYLIST_META ),
		];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$viewer = [
			'event'            => 'view.completed',
			'timestamp'        => '2026-09-28T12:00:00+00:00',
			'site'             => 'example.com',
			'video_id'         => 123,
			'video_title'      => 'Lesson 1: Introduction',
			'user_id'          => 42,
			'user_email'       => 'john@example.com',
			'user_name'        => 'John Doe',
			'user_type'        => 'user',
			'coverage_percent' => 75.5,
			'milestone'        => 0,
			'email'            => 'john@example.com',
			'name'             => 'John Doe',
			'subject'          => [
				'type'  => 'user',
				'id'    => '42',
				'email' => 'john@example.com',
				'name'  => 'John Doe',
			],
		];

		$quiz = [
			'gate_id'    => 'final',
			'score'      => 90,
			'passed'     => true,
			'attempt_no' => 1,
			'attempts'   => 1,
		];

		$media = [
			'id'        => 123,
			'title'     => 'Lesson 1: Introduction',
			'status'    => 'publish',
			'shortcode' => '[trueplayer id="123"]',
			'tags'      => [ 'Course A' ],
			'modified'  => '2026-09-28 12:00:00',
			'config'    => [
				'source' => [
					'type' => 'self',
					'src'  => 'https://example.com/video.mp4',
				],
			],
		];

		$playlist = [
			'id'        => 5,
			'title'     => 'Course Playlist',
			'status'    => 'publish',
			'shortcode' => '[trueplayer_playlist id="5"]',
			'modified'  => '2026-09-28 12:00:00',
			'config'    => [
				'title'        => 'Course Playlist',
				'layout'       => 'sidebar',
				'videos'       => [ 123, 124 ],
				'autoplayNext' => true,
				'showTitles'   => true,
			],
		];

		$milestone = array_merge(
			$viewer,
			[
				'event'     => 'progress.milestone',
				'milestone' => 75,
			]
		);

		$samples = [
			'media_created'     => $media,
			'media_updated'     => $media,
			'media_deleted'     => [
				'deleted_id' => 123,
				'id'         => 123,
				'title'      => 'Lesson 1: Introduction',
			],
			'playlist_created'  => $playlist,
			'playlist_updated'  => $playlist,
			'playlist_deleted'  => [
				'deleted_id' => 5,
				'id'         => 5,
				'title'      => 'Course Playlist',
			],
			'video_started'     => array_merge( $viewer, [ 'event' => 'view.started' ] ),
			'video_watched'     => $viewer,
			'video_completed'   => $viewer,
			'video_percentage'  => $milestone,
			'user_percentage'   => $milestone,
			'milestone_reached' => $milestone,
			'checkpoint_passed' => array_merge( $viewer, $quiz, [ 'event' => 'checkpoint.passed' ] ),
			'checkpoint_failed' => array_merge(
				$viewer,
				$quiz,
				[
					'event'  => 'checkpoint.failed',
					'passed' => false,
					'score'  => 40,
				]
			),
			'quiz_passed'       => array_merge( $viewer, $quiz, [ 'event' => 'quiz.passed' ] ),
			'quiz_failed'       => array_merge(
				$viewer,
				$quiz,
				[
					'event'  => 'quiz.failed',
					'passed' => false,
					'score'  => 40,
				]
			),
			'video_locked'      => array_merge(
				$viewer,
				$quiz,
				[
					'event'  => 'video.locked',
					'passed' => false,
					'score'  => 40,
				]
			),
			'video_unlocked'    => array_merge( $viewer, [ 'event' => 'video.unlocked' ] ),
			'subscriber_added'  => array_merge(
				$viewer,
				[
					'event'    => 'subscriber.added',
					'provider' => 'wp_mail',
				]
			),
			'email_captured'    => array_merge(
				$viewer,
				[
					'event'    => 'subscriber.added',
					'provider' => 'wp_mail',
				]
			),
		];

		return $samples[ $trigger ] ?? $viewer;
	}

	/*
	 ---------------------------------------------------------------------
	 * Actions
	 ------------------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'create_media'         => [ 'label' => 'Create Media' ],
			'update_media'         => [ 'label' => 'Update Media' ],
			'delete_media'         => [ 'label' => 'Delete Media' ],
			'publish_media'        => [ 'label' => 'Publish Media' ],
			'draft_media'          => [ 'label' => 'Draft Media' ],
			'change_media_status'  => [ 'label' => 'Change Media Status' ],

			'create_playlist'      => [ 'label' => 'Create Playlist' ],
			'update_playlist'      => [ 'label' => 'Update Playlist' ],
			'delete_playlist'      => [ 'label' => 'Delete Playlist' ],
			'add_to_playlist'      => [ 'label' => 'Add Media to Playlist' ],
			'remove_from_playlist' => [ 'label' => 'Remove Media from Playlist' ],

			'record_progress'      => [ 'label' => 'Record Watch Progress' ],
			'reset_progress'       => [ 'label' => 'Reset Watch Progress' ],
			'mark_completed'       => [ 'label' => 'Mark Video Completed' ],
			'get_watch_progress'   => [ 'label' => 'Get Watch Progress' ],
			'get_viewer_progress'  => [ 'label' => 'Get Viewer Progress' ],

			'get_media_analytics'  => [ 'label' => 'Get Media Analytics' ],
			'get_video_analytics'  => [ 'label' => 'Get Video Analytics' ],
			'get_viewer_analytics' => [ 'label' => 'Get Viewer Analytics' ],
			'get_watch_time'       => [ 'label' => 'Get Watch Time' ],
			'get_completion_rate'  => [ 'label' => 'Get Completion Rate' ],
			'get_watch_heatmap'    => [ 'label' => 'Get Watch Heatmap' ],

			'send_email_capture'   => [ 'label' => 'Send Email Capture' ],
			'add_subscriber'       => [ 'label' => 'Add Subscriber' ],

			'trigger_webhook'      => [ 'label' => 'Trigger Webhook' ],
			'send_webhook'         => [ 'label' => 'Send Webhook' ],
			'send_json_payload'    => [ 'label' => 'Send JSON Payload' ],
			'call_rest_api'        => [ 'label' => 'Call REST API' ],
			'get_api_data'         => [ 'label' => 'Get API Data' ],
		];
	}

	private static function field( string $key, string $label, bool $required = false, array $extra = [] ): array {
		return array_merge(
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'expression',
				'required' => $required,
			],
			$extra
		);
	}

	private static function select( string $key, string $label, array $options, bool $required = false, string $default = '' ): array {
		$list = [];
		foreach ( $options as $value => $text ) {
			$list[] = [
				'value' => (string) $value,
				'label' => $text,
			];
		}
		$field = [
			'key'      => $key,
			'label'    => $label,
			'type'     => 'select',
			'required' => $required,
			'options'  => $list,
		];
		if ( '' !== $default ) {
			$field['default'] = $default;
		}
		return $field;
	}

	private static function source_options(): array {
		return [
			'self'    => 'Self-hosted / Media Library',
			'youtube' => 'YouTube',
			'vimeo'   => 'Vimeo',
			'url'     => 'Direct URL',
		];
	}

	private static function viewer_fields(): array {
		return [
			self::field( 'video_id', 'Video ID', true ),
			self::field(
				'user_id',
				'User ID',
				false,
				[ 'help' => 'WordPress user ID. Use this or the email below.' ]
			),
			self::field(
				'user_email',
				'User Email',
				false,
				[ 'help' => 'Used when User ID is empty. Must belong to a WordPress user.' ]
			),
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'create_media':
				return [
					self::field(
						'title',
						'Media Title',
						true,
						[ 'placeholder' => 'Lesson 1: Introduction' ]
					),
					self::select( 'source_type', 'Source Type', self::source_options(), false, 'self' ),
					self::field(
						'source_url',
						'Source URL / Video ID',
						true,
						[ 'placeholder' => 'https://example.com/video.mp4' ]
					),
					self::field(
						'tags',
						'Tags (comma-separated)',
						false,
						[ 'placeholder' => 'Course A, Module 1' ]
					),
					self::field( 'poster', 'Poster URL' ),
				];

			case 'update_media':
				return [
					self::field( 'media_id', 'Media ID', true ),
					self::field( 'title', 'New Title' ),
					self::select( 'source_type', 'Source Type', self::source_options() ),
					self::field( 'source_url', 'Source URL / Video ID' ),
					self::field(
						'tags',
						'Tags (comma-separated, replaces all)',
						false,
						[ 'help' => 'Leave empty to keep existing tags.' ]
					),
					self::field( 'poster', 'Poster URL' ),
				];

			case 'delete_media':
			case 'publish_media':
			case 'draft_media':
				return [ self::field( 'media_id', 'Media ID', true ) ];

			case 'change_media_status':
				return [
					self::field( 'media_id', 'Media ID', true ),
					self::select(
						'status',
						'New Status',
						[
							'publish' => 'Publish',
							'draft'   => 'Draft',
							'private' => 'Private',
							'pending' => 'Pending Review',
						],
						true
					),
				];

			case 'create_playlist':
				return [
					self::field( 'title', 'Playlist Title', true ),
					self::select(
						'layout',
						'Layout',
						[
							'sidebar' => 'Sidebar',
							'grid'    => 'Grid',
						],
						false,
						'sidebar'
					),
					self::field(
						'video_ids',
						'Video IDs (comma-separated)',
						false,
						[ 'placeholder' => '123, 124, 125' ]
					),
					self::select(
						'autoplay_next',
						'Autoplay Next',
						[
							'yes' => 'Yes',
							'no'  => 'No',
						],
						false,
						'yes'
					),
				];

			case 'update_playlist':
				return [
					self::field( 'playlist_id', 'Playlist ID', true ),
					self::field( 'title', 'New Title' ),
					self::select(
						'layout',
						'Layout',
						[
							''        => 'Keep current',
							'sidebar' => 'Sidebar',
							'grid'    => 'Grid',
						]
					),
					self::field( 'video_ids', 'Video IDs (comma-separated, replaces all)' ),
					self::select(
						'autoplay_next',
						'Autoplay Next',
						[
							''    => 'Keep current',
							'yes' => 'Yes',
							'no'  => 'No',
						]
					),
				];

			case 'delete_playlist':
				return [ self::field( 'playlist_id', 'Playlist ID', true ) ];

			case 'add_to_playlist':
			case 'remove_from_playlist':
				return [
					self::field( 'playlist_id', 'Playlist ID', true ),
					self::field( 'video_id', 'Video ID', true ),
				];

			case 'record_progress':
				return array_merge(
					self::viewer_fields(),
					[
						self::field(
							'percent',
							'Watch Percent (0-100)',
							true,
							[ 'placeholder' => '75' ]
						),
					]
				);

			case 'reset_progress':
			case 'mark_completed':
			case 'get_watch_progress':
			case 'get_viewer_progress':
			case 'get_viewer_analytics':
				return self::viewer_fields();

			case 'get_media_analytics':
			case 'get_video_analytics':
			case 'get_watch_time':
			case 'get_completion_rate':
			case 'get_watch_heatmap':
				return [ self::field( 'video_id', 'Video ID', true ) ];

			case 'send_email_capture':
			case 'add_subscriber':
				return [
					self::field( 'email', 'Email', true ),
					self::field( 'name', 'Name' ),
					self::field( 'video_id', 'Video ID (optional)' ),
					self::select(
						'provider',
						'Provider',
						[
							'wp_mail'   => 'Email notification (wp_mail)',
							'gemcrm'    => 'GemCRM',
							'mailchimp' => 'Mailchimp',
						],
						false,
						'wp_mail'
					),
					self::field( 'lists', 'List IDs (comma-separated)' ),
					self::field( 'tags', 'Tags (comma-separated)' ),
				];

			case 'trigger_webhook':
			case 'send_webhook':
			case 'send_json_payload':
				return [
					self::field( 'url', 'Webhook URL', true ),
					self::field(
						'event_name',
						'Event Name',
						false,
						[
							'placeholder' => 'video.completed',
							'help'        => 'Sent as the X-TruePlayer-Event header.',
						]
					),
					self::field(
						'payload',
						'JSON Payload',
						false,
						[
							'placeholder' => '{"video_id": 123}',
							'help'        => 'Leave empty to send the previous step output.',
						]
					),
					self::field(
						'secret',
						'Signing Secret (optional)',
						false,
						[ 'help' => 'Adds X-TruePlayer-Signature: sha256=<hmac>.' ]
					),
				];

			case 'call_rest_api':
			case 'get_api_data':
				$fields = [
					self::field( 'url', 'API URL', true ),
				];
				if ( 'call_rest_api' === $action ) {
					$fields[] = self::select(
						'method',
						'HTTP Method',
						[
							'GET'    => 'GET',
							'POST'   => 'POST',
							'PUT'    => 'PUT',
							'PATCH'  => 'PATCH',
							'DELETE' => 'DELETE',
						],
						false,
						'GET'
					);
				}
				$fields[] = self::field(
					'headers',
					'Headers (JSON)',
					false,
					[ 'placeholder' => '{"Authorization": "Bearer token"}' ]
				);
				$fields[] = self::field(
					'query',
					'Query String',
					false,
					[ 'placeholder' => 'page=1&limit=10' ]
				);
				if ( 'call_rest_api' === $action ) {
					$fields[] = self::field( 'body', 'Body (JSON)' );
				}
				return $fields;
		}
		return [];
	}

	public static function get_action_sample_output( string $action ): array {
		$samples = [
			'create_media'         => [
				'success'   => true,
				'id'        => 126,
				'title'     => 'New Lesson',
				'status'    => 'publish',
				'shortcode' => '[trueplayer id="126"]',
			],
			'update_media'         => [
				'success' => true,
				'id'      => 123,
				'fields'  => [ 'title', 'config' ],
			],
			'delete_media'         => [
				'success' => true,
				'id'      => 123,
				'deleted' => true,
			],
			'publish_media'        => [
				'success' => true,
				'id'      => 123,
				'status'  => 'publish',
			],
			'draft_media'          => [
				'success' => true,
				'id'      => 123,
				'status'  => 'draft',
			],
			'change_media_status'  => [
				'success' => true,
				'id'      => 123,
				'status'  => 'private',
			],
			'create_playlist'      => [
				'success'   => true,
				'id'        => 6,
				'title'     => 'New Playlist',
				'shortcode' => '[trueplayer_playlist id="6"]',
			],
			'update_playlist'      => [
				'success' => true,
				'id'      => 5,
				'updated' => true,
			],
			'delete_playlist'      => [
				'success' => true,
				'id'      => 5,
				'deleted' => true,
			],
			'add_to_playlist'      => [
				'success'     => true,
				'playlist_id' => 5,
				'video_id'    => 123,
				'videos'      => [ 123, 124 ],
			],
			'remove_from_playlist' => [
				'success'     => true,
				'playlist_id' => 5,
				'video_id'    => 123,
				'videos'      => [ 124 ],
			],
			'record_progress'      => [
				'success'  => true,
				'video_id' => 123,
				'user_id'  => 42,
				'percent'  => 75,
				'status'   => 'completed',
			],
			'reset_progress'       => [
				'success'  => true,
				'video_id' => 123,
				'user_id'  => 42,
				'reset'    => true,
			],
			'mark_completed'       => [
				'success'   => true,
				'video_id'  => 123,
				'user_id'   => 42,
				'completed' => true,
			],
			'get_watch_progress'   => [
				'success'         => true,
				'video_id'        => 123,
				'user_id'         => 42,
				'percent'         => 75.5,
				'watched_seconds' => 450,
				'duration'        => 600,
				'completed'       => false,
				'status'          => 'in_progress',
				'locked'          => false,
				'resumeAt'        => 450,
			],
			'get_media_analytics'  => [
				'success'  => true,
				'video_id' => 123,
				'summary'  => [
					'viewers'     => 100,
					'views'       => 150,
					'completions' => 90,
				],
			],
			'get_viewer_analytics' => [
				'success'     => true,
				'video_id'    => 123,
				'user_id'     => 42,
				'percent'     => 75.5,
				'sessions'    => 3,
				'completions' => 1,
				'status'      => 'in_progress',
				'attempts'    => [],
			],
			'get_watch_time'       => [
				'success'       => true,
				'video_id'      => 123,
				'watch_seconds' => 450,
				'duration'      => 600,
			],
			'get_completion_rate'  => [
				'success'         => true,
				'video_id'        => 123,
				'completion_rate' => 60.0,
				'total_viewers'   => 150,
				'completions'     => 90,
			],
			'get_watch_heatmap'    => [
				'success'  => true,
				'video_id' => 123,
				'heatmap'  => [
					[
						'bucket'  => 0,
						'plays'   => 150,
						'reached' => 150,
					],
				],
			],
			'add_subscriber'       => [
				'success'  => true,
				'email'    => 'john@example.com',
				'provider' => 'wp_mail',
				'message'  => 'Subscriber added',
			],
			'send_webhook'         => [
				'success' => true,
				'status'  => 200,
				'body'    => [],
			],
			'call_rest_api'        => [
				'success' => true,
				'status'  => 200,
				'body'    => [],
			],
		];

		$samples['get_video_analytics']  = $samples['get_media_analytics'];
		$samples['get_viewer_progress']  = $samples['get_watch_progress'];
		$samples['send_email_capture']   = $samples['add_subscriber'];
		$samples['trigger_webhook']      = $samples['send_webhook'];
		$samples['send_json_payload']    = $samples['send_webhook'];
		$samples['get_api_data']         = $samples['call_rest_api'];

		return $samples[ $action ] ?? [ 'success' => true ];
	}

	/*
	 ---------------------------------------------------------------------
	 * Execution
	 ------------------------------------------------------------------- */

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::active() ) {
			return self::error_output( 'TruePlayer plugin is not active.' );
		}

		$action = (string) ( $node['data']['event'] ?? ( $node['action'] ?? '' ) );
		$c      = isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ? $node['data']['config'] : [];

		switch ( $action ) {
			case 'create_media':
				return self::action_create_media( $c );
			case 'update_media':
				return self::action_update_media( $c );
			case 'delete_media':
				return self::action_delete_media( (int) ( $c['media_id'] ?? 0 ) );
			case 'publish_media':
				return self::action_change_status( (int) ( $c['media_id'] ?? 0 ), 'publish' );
			case 'draft_media':
				return self::action_change_status( (int) ( $c['media_id'] ?? 0 ), 'draft' );
			case 'change_media_status':
				return self::action_change_status( (int) ( $c['media_id'] ?? 0 ), (string) ( $c['status'] ?? '' ) );

			case 'create_playlist':
				return self::action_create_playlist( $c );
			case 'update_playlist':
				return self::action_update_playlist( $c );
			case 'delete_playlist':
				return self::action_delete_playlist( (int) ( $c['playlist_id'] ?? 0 ) );
			case 'add_to_playlist':
				return self::action_playlist_member( (int) ( $c['playlist_id'] ?? 0 ), (int) ( $c['video_id'] ?? 0 ), true );
			case 'remove_from_playlist':
				return self::action_playlist_member( (int) ( $c['playlist_id'] ?? 0 ), (int) ( $c['video_id'] ?? 0 ), false );

			case 'record_progress':
				return self::action_record_progress( $c );
			case 'reset_progress':
				return self::action_reset_progress( $c );
			case 'mark_completed':
				return self::action_mark_completed( $c );
			case 'get_watch_progress':
			case 'get_viewer_progress':
				return self::action_get_progress( $c );

			case 'get_media_analytics':
			case 'get_video_analytics':
				return self::action_get_analytics( (int) ( $c['video_id'] ?? 0 ) );
			case 'get_viewer_analytics':
				return self::action_get_viewer_analytics( $c );
			case 'get_watch_time':
				return self::action_get_watch_time( (int) ( $c['video_id'] ?? 0 ) );
			case 'get_completion_rate':
				return self::action_get_completion_rate( (int) ( $c['video_id'] ?? 0 ) );
			case 'get_watch_heatmap':
				return self::action_get_heatmap( (int) ( $c['video_id'] ?? 0 ) );

			case 'send_email_capture':
			case 'add_subscriber':
				return self::action_add_subscriber( $c );

			case 'trigger_webhook':
			case 'send_webhook':
			case 'send_json_payload':
				return self::action_send_webhook( $c, $input );
			case 'call_rest_api':
			case 'get_api_data':
				return self::action_call_api( $c, 'get_api_data' === $action );
		}

		return self::error_output( 'Unknown action: ' . $action );
	}

	/* ---------- Shared helpers ---------- */

	private static function video_type(): string {
		return defined( 'TRUEPLAYER_VIDEO_POST_TYPE' ) ? TRUEPLAYER_VIDEO_POST_TYPE : 'tp_video';
	}

	private static function video_taxonomy(): string {
		return 'tp_video_tag';
	}

	private static function is_video( int $id ): bool {
		return $id > 0 && self::video_type() === get_post_type( $id );
	}

	private static function is_playlist( int $id ): bool {
		return $id > 0 && self::PLAYLIST_TYPE === get_post_type( $id );
	}

	private static function get_json_meta( int $id, string $key ): array {
		$raw  = get_post_meta( $id, $key, true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;
		return is_array( $data ) ? $data : [];
	}

	private static function save_json_meta( int $id, string $key, array $data ) {
		if ( class_exists( '\TruePlayer\Helper' ) ) {
			return \TruePlayer\Helper::update_json_meta( $id, $key, $data );
		}
		return update_post_meta( $id, $key, wp_slash( wp_json_encode( $data ) ) );
	}

	private static function csv( $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
	}

	private static function id_list( $value ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) ) );
	}

	/**
	 * Same rules as TruePlayer's own controller: URLs are escaped, YouTube and
	 * Vimeo ids pass through as ids. Returns '' when the source is unusable.
	 */
	private static function clean_source( string $type, string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '#^(?:https?:)?//#i', $raw ) ) {
			return esc_url_raw( $raw, [ 'http', 'https' ] );
		}
		if ( in_array( $type, [ 'youtube', 'vimeo' ], true ) && preg_match( '/^[\w-]{1,64}$/', $raw ) ) {
			return $raw;
		}
		return '';
	}

	/**
	 * Resolve a WordPress user into a TruePlayer subject, or null.
	 */
	private static function resolve_subject( array $c ) {
		$user_id = (int) ( $c['user_id'] ?? 0 );
		if ( $user_id < 1 ) {
			$email = sanitize_email( (string) ( $c['user_email'] ?? '' ) );
			if ( '' !== $email ) {
				$user    = get_user_by( 'email', $email );
				$user_id = $user ? (int) $user->ID : 0;
			}
		}
		if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
			return null;
		}
		return new \TruePlayer\Subject( 'user', $user_id );
	}

	/* ---------- Media ---------- */

	private static function action_create_media( array $c ): array {
		$title = sanitize_text_field( (string) ( $c['title'] ?? '' ) );
		if ( '' === $title ) {
			return self::error_output( 'Media title is required.' );
		}

		$type = (string) ( $c['source_type'] ?? 'self' );
		if ( ! in_array( $type, self::SOURCE_TYPES, true ) ) {
			$type = 'self';
		}
		$src = self::clean_source( $type, (string) ( $c['source_url'] ?? '' ) );
		if ( '' === $src ) {
			return self::error_output( 'A valid source URL (or YouTube/Vimeo ID) is required.' );
		}

		$id = wp_insert_post(
			[
				'post_type'   => self::video_type(),
				'post_status' => 'publish',
				'post_title'  => $title,
			],
			true
		);
		if ( is_wp_error( $id ) ) {
			return self::error_output( $id->get_error_message() );
		}

		$source = [
			'type' => $type,
			'src'  => $src,
		];
		$poster = esc_url_raw( trim( (string) ( $c['poster'] ?? '' ) ), [ 'http', 'https' ] );
		if ( '' !== $poster ) {
			$source['poster'] = $poster;
		}
		self::save_json_meta( $id, self::VIDEO_META, [ 'source' => $source ] );

		$tags = self::csv( $c['tags'] ?? '' );
		if ( ! empty( $tags ) ) {
			wp_set_object_terms( $id, $tags, self::video_taxonomy(), false );
		}

		return self::success_output(
			[
				'id'        => (int) $id,
				'title'     => $title,
				'status'    => 'publish',
				'shortcode' => sprintf( '[trueplayer id="%d"]', $id ),
			]
		);
	}

	private static function action_update_media( array $c ): array {
		$id = (int) ( $c['media_id'] ?? 0 );
		if ( ! self::is_video( $id ) ) {
			return self::error_output( 'Media not found.' );
		}

		$updated = [];

		$title = sanitize_text_field( (string) ( $c['title'] ?? '' ) );
		if ( '' !== $title ) {
			wp_update_post(
				[
					'ID'         => $id,
					'post_title' => $title,
				]
			);
			$updated[] = 'title';
		}

		$src_raw = trim( (string) ( $c['source_url'] ?? '' ) );
		$poster  = trim( (string) ( $c['poster'] ?? '' ) );
		if ( '' !== $src_raw || '' !== $poster ) {
			$config = self::get_json_meta( $id, self::VIDEO_META );
			if ( ! isset( $config['source'] ) || ! is_array( $config['source'] ) ) {
				$config['source'] = [];
			}

			if ( '' !== $src_raw ) {
				$type = (string) ( $c['source_type'] ?? '' );
				if ( ! in_array( $type, self::SOURCE_TYPES, true ) ) {
					$type = (string) ( $config['source']['type'] ?? 'self' );
				}
				$src = self::clean_source( $type, $src_raw );
				if ( '' === $src ) {
					return self::error_output( 'Invalid source URL or ID.' );
				}
				$config['source']['type'] = $type;
				$config['source']['src']  = $src;
			}
			if ( '' !== $poster ) {
				$config['source']['poster'] = esc_url_raw( $poster, [ 'http', 'https' ] );
			}

			self::save_json_meta( $id, self::VIDEO_META, $config );
			$updated[] = 'config';
		}

		$tags = self::csv( $c['tags'] ?? '' );
		if ( ! empty( $tags ) ) {
			wp_set_object_terms( $id, $tags, self::video_taxonomy(), false );
			$updated[] = 'tags';
		}

		return self::success_output(
			[
				'id'      => $id,
				'updated' => ! empty( $updated ),
				'fields'  => $updated,
			]
		);
	}

	private static function action_delete_media( int $id ): array {
		if ( ! self::is_video( $id ) ) {
			return self::error_output( 'Media not found.' );
		}
		if ( ! wp_delete_post( $id, true ) ) {
			return self::error_output( 'Could not delete media.' );
		}
		return self::success_output(
			[
				'id'      => $id,
				'deleted' => true,
			]
		);
	}

	private static function action_change_status( int $id, string $status ): array {
		if ( ! self::is_video( $id ) ) {
			return self::error_output( 'Media not found.' );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return self::error_output( 'Invalid status: ' . $status );
		}
		$result = wp_update_post(
			[
				'ID'          => $id,
				'post_status' => $status,
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			return self::error_output( $result->get_error_message() );
		}
		return self::success_output(
			[
				'id'     => $id,
				'status' => $status,
			]
		);
	}

	/* ---------- Playlists ---------- */

	private static function action_create_playlist( array $c ): array {
		$title = sanitize_text_field( (string) ( $c['title'] ?? '' ) );
		if ( '' === $title ) {
			return self::error_output( 'Playlist title is required.' );
		}

		$layout = (string) ( $c['layout'] ?? 'sidebar' );
		if ( ! in_array( $layout, [ 'sidebar', 'grid' ], true ) ) {
			$layout = 'sidebar';
		}

		$id = wp_insert_post(
			[
				'post_type'   => self::PLAYLIST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			],
			true
		);
		if ( is_wp_error( $id ) ) {
			return self::error_output( $id->get_error_message() );
		}

		self::save_json_meta(
			$id,
			self::PLAYLIST_META,
			[
				'title'        => $title,
				'layout'       => $layout,
				'videos'       => self::id_list( $c['video_ids'] ?? '' ),
				'autoplayNext' => 'no' !== ( $c['autoplay_next'] ?? 'yes' ),
				'showTitles'   => true,
			]
		);

		return self::success_output(
			[
				'id'        => (int) $id,
				'title'     => $title,
				'shortcode' => sprintf( '[trueplayer_playlist id="%d"]', $id ),
			]
		);
	}

	private static function action_update_playlist( array $c ): array {
		$id = (int) ( $c['playlist_id'] ?? 0 );
		if ( ! self::is_playlist( $id ) ) {
			return self::error_output( 'Playlist not found.' );
		}

		$config = self::get_json_meta( $id, self::PLAYLIST_META );

		$title = sanitize_text_field( (string) ( $c['title'] ?? '' ) );
		if ( '' !== $title ) {
			wp_update_post(
				[
					'ID'         => $id,
					'post_title' => $title,
				]
			);
			$config['title'] = $title;
		}

		$layout = (string) ( $c['layout'] ?? '' );
		if ( in_array( $layout, [ 'sidebar', 'grid' ], true ) ) {
			$config['layout'] = $layout;
		}

		if ( '' !== trim( (string) ( $c['video_ids'] ?? '' ) ) ) {
			$config['videos'] = self::id_list( $c['video_ids'] );
		}

		$autoplay = (string) ( $c['autoplay_next'] ?? '' );
		if ( in_array( $autoplay, [ 'yes', 'no' ], true ) ) {
			$config['autoplayNext'] = 'yes' === $autoplay;
		}

		self::save_json_meta( $id, self::PLAYLIST_META, $config );

		return self::success_output(
			[
				'id'      => $id,
				'updated' => true,
			]
		);
	}

	private static function action_delete_playlist( int $id ): array {
		if ( ! self::is_playlist( $id ) ) {
			return self::error_output( 'Playlist not found.' );
		}
		if ( ! wp_delete_post( $id, true ) ) {
			return self::error_output( 'Could not delete playlist.' );
		}
		return self::success_output(
			[
				'id'      => $id,
				'deleted' => true,
			]
		);
	}

	private static function action_playlist_member( int $playlist_id, int $video_id, bool $add ): array {
		if ( ! self::is_playlist( $playlist_id ) ) {
			return self::error_output( 'Playlist not found.' );
		}
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}

		$config = self::get_json_meta( $playlist_id, self::PLAYLIST_META );
		$videos = isset( $config['videos'] ) && is_array( $config['videos'] ) ? array_map( 'absint', $config['videos'] ) : [];

		if ( $add ) {
			$videos[] = $video_id;
		} else {
			$videos = array_diff( $videos, [ $video_id ] );
		}
		$config['videos'] = array_values( array_unique( $videos ) );

		self::save_json_meta( $playlist_id, self::PLAYLIST_META, $config );

		return self::success_output(
			[
				'playlist_id' => $playlist_id,
				'video_id'    => $video_id,
				'videos'      => $config['videos'],
			]
		);
	}

	/* ---------- Progress ---------- */

	private static function progress_guard( array $c ) {
		$video_id = (int) ( $c['video_id'] ?? 0 );
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}
		$subject = self::resolve_subject( $c );
		if ( null === $subject ) {
			return self::error_output( 'A valid WordPress user (ID or email) is required.' );
		}
		return [ $video_id, $subject ];
	}

	private static function action_record_progress( array $c ): array {
		$guard = self::progress_guard( $c );
		if ( isset( $guard['port'] ) ) {
			return $guard;
		}
		list( $video_id, $subject ) = $guard;

		$percent = max( 0, min( 100, (float) ( $c['percent'] ?? 0 ) ) );
		$row     = \TruePlayer\Services\ProgressService::get_row( $video_id, $subject );
		$gating  = \TruePlayer\Services\ProgressService::gating_config( $video_id );

		$done   = ! empty( $row['completed'] ) || $percent >= (float) ( $gating['completionThreshold'] ?? 75 );
		$status = $done ? 'completed' : 'in_progress';
		if ( $row && 'locked' === $row['status'] ) {
			$status = 'locked';
		}

		\TruePlayer\Services\ProgressService::upsert(
			$video_id,
			$subject,
			[
				'percent'   => $percent,
				'completed' => $done ? 1 : 0,
				'status'    => $status,
				'last_seen' => current_time( 'mysql', true ),
			]
		);

		return self::success_output(
			[
				'video_id' => $video_id,
				'user_id'  => (int) $subject->id,
				'percent'  => $percent,
				'status'   => $status,
			]
		);
	}

	private static function action_reset_progress( array $c ): array {
		$guard = self::progress_guard( $c );
		if ( isset( $guard['port'] ) ) {
			return $guard;
		}
		list( $video_id, $subject ) = $guard;

		global $wpdb;
		$wpdb->delete(
			\TruePlayer\Services\ProgressService::table(),
			[
				'video_id'     => $video_id,
				'subject_type' => $subject->type,
				'subject_id'   => $subject->id,
			],
			[ '%d', '%s', '%s' ]
		);

		return self::success_output(
			[
				'video_id' => $video_id,
				'user_id'  => (int) $subject->id,
				'reset'    => true,
			]
		);
	}

	private static function action_mark_completed( array $c ): array {
		$guard = self::progress_guard( $c );
		if ( isset( $guard['port'] ) ) {
			return $guard;
		}
		list( $video_id, $subject ) = $guard;

		\TruePlayer\Services\ProgressService::upsert(
			$video_id,
			$subject,
			[
				'percent'   => 100,
				'completed' => 1,
				'status'    => 'completed',
				'last_seen' => current_time( 'mysql', true ),
			]
		);

		return self::success_output(
			[
				'video_id'  => $video_id,
				'user_id'   => (int) $subject->id,
				'completed' => true,
			]
		);
	}

	private static function action_get_progress( array $c ): array {
		$guard = self::progress_guard( $c );
		if ( isset( $guard['port'] ) ) {
			return $guard;
		}
		list( $video_id, $subject ) = $guard;

		$state = \TruePlayer\Services\ProgressService::state( $video_id, $subject );
		$row   = \TruePlayer\Services\ProgressService::get_row( $video_id, $subject );

		return self::success_output(
			array_merge(
				[
					'video_id'        => $video_id,
					'user_id'         => (int) $subject->id,
					'watched_seconds' => (int) ( $row['watched_seconds'] ?? 0 ),
					'duration'        => (int) ( $row['duration'] ?? 0 ),
				],
				$state
			)
		);
	}

	/* ---------- Analytics ---------- */

	private static function action_get_analytics( int $video_id ): array {
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}
		$analytics = \TruePlayer\Services\AnalyticsService::for_video( $video_id );
		return self::success_output(
			[
				'video_id' => $video_id,
				'summary'  => $analytics['summary'] ?? [],
				'quiz'     => $analytics['quiz'] ?? [],
			]
		);
	}

	private static function action_get_viewer_analytics( array $c ): array {
		$guard = self::progress_guard( $c );
		if ( isset( $guard['port'] ) ) {
			return $guard;
		}
		list( $video_id, $subject ) = $guard;

		$row = \TruePlayer\Services\ProgressService::get_row( $video_id, $subject );
		if ( ! $row ) {
			return self::error_output( 'This viewer has no progress for the video.' );
		}
		$detail = \TruePlayer\Services\AnalyticsService::viewer_detail( (int) $row['id'] );
		if ( ! $detail ) {
			return self::error_output( 'Viewer not found.' );
		}

		return self::success_output(
			array_merge(
				[
					'video_id' => $video_id,
					'user_id'  => (int) $subject->id,
				],
				$detail
			)
		);
	}

	private static function action_get_watch_time( int $video_id ): array {
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}
		global $wpdb;
		$table = \TruePlayer\Services\ProgressService::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(watched_seconds) AS avg_watch, AVG(duration) AS avg_duration FROM {$table} WHERE video_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$video_id
			),
			ARRAY_A
		);

		return self::success_output(
			[
				'video_id'      => $video_id,
				'watch_seconds' => (int) round( (float) ( $row['avg_watch'] ?? 0 ) ),
				'duration'      => (int) round( (float) ( $row['avg_duration'] ?? 0 ) ),
			]
		);
	}

	private static function action_get_completion_rate( int $video_id ): array {
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}
		global $wpdb;
		$table = \TruePlayer\Services\ProgressService::table();
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE video_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$video_id
			)
		);
		$done  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE video_id = %d AND completed = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$video_id
			)
		);

		return self::success_output(
			[
				'video_id'        => $video_id,
				'completion_rate' => $total > 0 ? round( $done / $total * 100, 2 ) : 0,
				'total_viewers'   => $total,
				'completions'     => $done,
			]
		);
	}

	private static function action_get_heatmap( int $video_id ): array {
		if ( ! self::is_video( $video_id ) ) {
			return self::error_output( 'Video not found.' );
		}
		global $wpdb;
		$table = \TruePlayer\Services\ProgressService::engagement_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bucket, plays, reached FROM {$table} WHERE video_id = %d ORDER BY bucket ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$video_id
			),
			ARRAY_A
		);

		return self::success_output(
			[
				'video_id' => $video_id,
				'heatmap'  => is_array( $rows ) ? $rows : [],
			]
		);
	}

	/* ---------- Subscribers ---------- */

	private static function action_add_subscriber( array $c ): array {
		$email = sanitize_email( (string) ( $c['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return self::error_output( 'A valid email is required.' );
		}
		if ( ! class_exists( '\TruePlayer\Integrations' ) ) {
			return self::error_output( 'TruePlayer integrations are unavailable.' );
		}

		$provider = (string) ( $c['provider'] ?? 'wp_mail' );
		if ( '' === $provider ) {
			$provider = 'wp_mail';
		}

		$result = \TruePlayer\Integrations::subscribe(
			[
				'email'    => $email,
				'name'     => sanitize_text_field( (string) ( $c['name'] ?? '' ) ),
				'video_id' => (int) ( $c['video_id'] ?? 0 ),
				'layer_id' => '',
				'provider' => $provider,
				'lists'    => self::csv( $c['lists'] ?? '' ),
				'tags'     => self::csv( $c['tags'] ?? '' ),
			]
		);

		if ( empty( $result['ok'] ) ) {
			return self::error_output( (string) ( $result['message'] ?? 'Could not subscribe.' ) );
		}

		return self::success_output(
			[
				'email'    => $email,
				'provider' => $provider,
				'message'  => (string) ( $result['message'] ?? 'Subscriber added' ),
			]
		);
	}

	/* ---------- Webhook / HTTP ---------- */

	private static function decode_json_field( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return [];
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function valid_http_url( string $url ): bool {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, [ 'http', 'https' ], true );
	}

	private static function action_send_webhook( array $c, array $input ): array {
		$url = trim( (string) ( $c['url'] ?? '' ) );
		if ( '' === $url ) {
			return self::error_output( 'Webhook URL is required.' );
		}
		if ( ! self::valid_http_url( $url ) ) {
			return self::error_output( 'URL must use http or https.' );
		}

		$raw = $c['payload'] ?? ( $c['json_payload'] ?? '' );
		if ( is_array( $raw ) ) {
			$payload = $raw;
		} elseif ( '' !== trim( (string) $raw ) ) {
			$payload = json_decode( (string) $raw, true );
			if ( ! is_array( $payload ) ) {
				return self::error_output( 'Payload must be valid JSON.' );
			}
		} else {
			$payload = $input;
		}

		$headers = [ 'Content-Type' => 'application/json' ];
		$event   = sanitize_text_field( (string) ( $c['event_name'] ?? '' ) );
		if ( '' !== $event ) {
			$headers['X-TruePlayer-Event'] = $event;
		}

		$body   = wp_json_encode( $payload );
		$secret = (string) ( $c['secret'] ?? '' );
		if ( '' !== $secret ) {
			$headers['X-TruePlayer-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $body, $secret );
		}

		$response = wp_safe_remote_post(
			$url,
			[
				'timeout' => 15,
				'headers' => $headers,
				'body'    => $body,
			]
		);
		if ( is_wp_error( $response ) ) {
			return self::error_output( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		return self::response_output( $status, wp_remote_retrieve_body( $response ) );
	}

	private static function action_call_api( array $c, bool $force_get ): array {
		$url = trim( (string) ( $c['url'] ?? '' ) );
		if ( '' === $url ) {
			return self::error_output( 'API URL is required.' );
		}

		$query = ltrim( trim( (string) ( $c['query'] ?? '' ) ), '?&' );
		if ( '' !== $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}
		if ( ! self::valid_http_url( $url ) ) {
			return self::error_output( 'URL must use http or https.' );
		}

		$method = $force_get ? 'GET' : strtoupper( (string) ( $c['method'] ?? 'GET' ) );
		if ( ! in_array( $method, [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
			return self::error_output( 'Unsupported HTTP method.' );
		}

		$headers = self::decode_json_field( $c['headers'] ?? '' );
		$args    = [
			'method'  => $method,
			'timeout' => 15,
		];

		$body = $force_get ? '' : $c['body'] ?? '';
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
		}
		$body = (string) $body;
		if ( '' !== $body && 'GET' !== $method ) {
			$args['body'] = $body;
			$has_type     = false;
			foreach ( array_keys( $headers ) as $name ) {
				if ( 'content-type' === strtolower( (string) $name ) ) {
					$has_type = true;
				}
			}
			if ( ! $has_type ) {
				$headers['Content-Type'] = 'application/json';
			}
		}
		$args['headers'] = $headers;

		$response = wp_safe_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::error_output( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		return self::response_output( $status, wp_remote_retrieve_body( $response ) );
	}

	private static function response_output( int $status, string $raw ): array {
		$decoded = json_decode( $raw, true );
		$ok      = $status >= 200 && $status < 300;

		return [
			'port' => 'main',
			'data' => [
				'success' => $ok,
				'status'  => $status,
				'body'    => null !== $decoded ? $decoded : $raw,
			],
		];
	}

	/* ---------- Output helpers ---------- */

	protected static function success_output( array $data ): array {
		return [
			'port' => 'main',
			'data' => array_merge( [ 'success' => true ], $data ),
		];
	}

	protected static function error_output( string $error, int $status = 0 ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => false,
				'status'  => $status,
				'error'   => $error,
			],
		];
	}
}
