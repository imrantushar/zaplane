<?php
namespace Zaplane\Integrations\Funnelkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function resolve_funnelkit_trigger( string $event, array $args, array $config ) {
		switch ( $event ) {
			case 'woofunnels_loaded':
				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'path' => (string) ( $args[0] ?? '' ),
					]
				);

			case 'core_modules_loaded':
			case 'loaded':
			case 'importing_completed':
			case 'container':
			case 'container_top':
			case 'container_bottom':
			case 'wp_footer':
			case 'checkout_loaded':
			case 'template_body_top':
			case 'template_container_top':
			case 'template_container_bottom':
			case 'template_wp_footer':
				return self::resolve_generic_trigger_payload( $event, $args );

			case 'funnel_created':
				$funnel_id = (int) ( $args[0] ?? 0 );
				if ( $funnel_id <= 0 || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				$steps = self::normalize_payload_value( $args[1] ?? [] );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id'   => $funnel_id,
						'funnel'      => self::resolve_funnel_payload( $funnel_id ),
						'steps'       => $steps,
						'total_steps' => is_array( $steps ) ? count( $steps ) : 0,
					]
				);

			case 'duplicate_funnel':
				$new_funnel_id    = self::resolve_funnel_id_from_value( $args[0] ?? null );
				$source_funnel_id = self::resolve_funnel_id_from_value( $args[1] ?? null );

				$selected_funnel = self::resolve_selected_id( $config, [ 'funnel_id' ] );
				if ( null !== $selected_funnel && $selected_funnel !== $new_funnel_id && $selected_funnel !== $source_funnel_id ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'new_funnel_id'    => $new_funnel_id,
						'source_funnel_id' => $source_funnel_id,
						'new_funnel'       => $new_funnel_id > 0 ? self::resolve_funnel_payload( $new_funnel_id ) : null,
						'source_funnel'    => $source_funnel_id > 0 ? self::resolve_funnel_payload( $source_funnel_id ) : null,
					]
				);

			case 'funnel_imported':
			case 'funnel_updated':
				$funnel_id = self::resolve_funnel_id_from_value( $args[0] ?? null );
				if ( $funnel_id <= 0 || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id'      => $funnel_id,
						'funnel'         => self::resolve_funnel_payload( $funnel_id ),
						'funnel_changes' => self::normalize_payload_value( $args[1] ?? [] ),
					]
				);

			case 'step_duplicated':
				$step_id = self::resolve_step_id_from_value( $args[0] ?? null );
				if ( $step_id <= 0 || ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				$funnel_id = self::resolve_funnel_id_from_step( $step_id );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'   => $step_id,
						'funnel_id' => $funnel_id,
						'step'      => self::resolve_step_payload( $step_id ),
						'funnel'    => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'step_viewed':
			case 'step_converted':
				$step_id = self::resolve_step_id_from_value( $args[0] ?? null );
				if ( $step_id <= 0 || ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				$funnel_id = self::resolve_funnel_id_from_step( $step_id );
				if ( ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'      => $step_id,
						'funnel_id'    => $funnel_id,
						'step'         => self::resolve_step_payload( $step_id ),
						'funnel'       => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
						'step_context' => self::normalize_payload_value( $args[1] ?? [] ),
					]
				);

			case 'funnel_ended':
				$current_step = self::normalize_payload_value( $args[0] ?? [] );
				$step_id      = self::resolve_step_id_from_value( $args[0] ?? null );
				$funnel_id    = self::resolve_funnel_id_from_value( $args[1] ?? null );
				if ( $funnel_id <= 0 ) {
					$funnel_id = self::resolve_funnel_id_from_step( $step_id );
				}

				if ( ! self::matches_selected_funnel( $config, $funnel_id ) || ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'      => $step_id,
						'funnel_id'    => $funnel_id,
						'current_step' => $current_step,
						'step'         => $step_id > 0 ? self::resolve_step_payload( $step_id ) : null,
						'funnel'       => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'ty_funnel_ended':
				$funnel_id = self::resolve_funnel_id_from_value( $args[0] ?? null );
				if ( $funnel_id <= 0 || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id' => $funnel_id,
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
						'order_id'  => (int) ( $args[1] ?? 0 ),
					]
				);

			case 'import_completed':
				$module_id = self::resolve_funnel_id_from_value( $args[0] ?? null );
				$funnel_id = $module_id;
				if ( $funnel_id <= 0 ) {
					$funnel_id = self::resolve_funnel_id_from_step( self::resolve_step_id_from_value( $args[1] ?? null ) );
				}

				if ( $funnel_id > 0 && ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'module_id' => $module_id,
						'step'      => self::normalize_payload_value( $args[1] ?? [] ),
						'builder'   => (string) ( $args[2] ?? '' ),
						'slug'      => (string) ( $args[3] ?? '' ),
						'funnel_id' => $funnel_id,
						'funnel'    => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'template_import_remote':
				$module_id = self::resolve_funnel_id_from_value( $args[0] ?? null );
				$funnel_id = $module_id;
				if ( $funnel_id > 0 && ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'module_id' => $module_id,
						'builder'   => (string) ( $args[1] ?? '' ),
						'slug'      => (string) ( $args[2] ?? '' ),
						'step'      => self::normalize_payload_value( $args[3] ?? [] ),
						'funnel_id' => $funnel_id,
						'funnel'    => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);
		}

		return false;
	}

	private static function resolve_generic_trigger_payload( string $event, array $args, array $extra = [] ): array {
		return array_merge(
			[
				'event'      => $event,
				'event_time' => current_time( 'mysql' ),
				'args'       => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
			],
			$extra
		);
	}

	private static function action_get_funnel_single( array $config, array $input ): array {
		$funnel_id = self::resolve_funnel_id_for_action( $config, $input );
		if ( $funnel_id <= 0 ) {
			return self::action_error( 'Funnel ID is required', $input );
		}

		$funnel = self::resolve_funnel_payload( $funnel_id );
		if ( ! $funnel ) {
			return self::action_error( 'Funnel not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'funnel' => $funnel,
				]
			)
		);
	}

	private static function action_get_step_single( array $config, array $input ): array {
		$step_id = self::resolve_step_id_for_action( $config, $input );
		if ( $step_id <= 0 ) {
			return self::action_error( 'Step ID is required', $input );
		}

		$step = self::resolve_step_payload( $step_id );
		if ( ! $step ) {
			return self::action_error( 'Step not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'step' => $step,
				]
			)
		);
	}

	private static function action_get_next_step( array $config, array $input ): array {
		$step_id = self::resolve_step_id_for_action( $config, $input );
		if ( $step_id <= 0 ) {
			return self::action_error( 'Step ID is required', $input );
		}

		$current_step = self::resolve_step_payload( $step_id );
		if ( ! $current_step ) {
			return self::action_error( 'Step not found', $input );
		}

		$next_step_id = (int) ( $current_step['next_step_id'] ?? 0 );
		if ( $next_step_id <= 0 ) {
			return self::action_error( 'Next step not found', $input );
		}

		$next_step = self::resolve_step_payload( $next_step_id );
		if ( ! $next_step ) {
			return self::action_error( 'Next step not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'current_step' => $current_step,
					'next_step'    => $next_step,
				]
			)
		);
	}

	private static function action_add_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		if ( $accepted_args < 1 ) {
			$accepted_args = 1;
		}
		if ( $accepted_args > 99 ) {
			$accepted_args = 99;
		}

		add_action(
			$hook_name,
			static function () {
			},
			10,
			$accepted_args
		);

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_do_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		do_action( $hook_name, $arg_1, $arg_2 );

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'arg_1'      => self::normalize_payload_value( $arg_1 ),
					'arg_2'      => self::normalize_payload_value( $arg_2 ),
					'triggered'  => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_add_filter( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		if ( $accepted_args < 1 ) {
			$accepted_args = 1;
		}
		if ( $accepted_args > 99 ) {
			$accepted_args = 99;
		}

		$has_return_value = array_key_exists( 'return_value', $config );
		$return_value     = $config['return_value'] ?? null;

		$callback = static function ( $value = null ) use ( $has_return_value, $return_value ) {
			if ( $has_return_value ) {
				return $return_value;
			}
			return $value;
		};

		if ( function_exists( 'add_filter' ) ) {
			add_filter( $hook_name, $callback, 10, $accepted_args );
		} else {
			add_action( $hook_name, $callback, 10, $accepted_args );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_apply_filters( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$value = $config['value'] ?? null;
		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		$filtered = apply_filters( $hook_name, $value, $arg_1, $arg_2 );

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'           => $hook_name,
					'value'          => self::normalize_payload_value( $value ),
					'arg_1'          => self::normalize_payload_value( $arg_1 ),
					'arg_2'          => self::normalize_payload_value( $arg_2 ),
					'filtered_value' => self::normalize_payload_value( $filtered ),
					'event_time'     => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_remove_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$removed = false;
		if ( function_exists( 'remove_all_actions' ) ) {
			remove_all_actions( $hook_name );
			$removed = true;
		} elseif ( function_exists( 'remove_all_filters' ) ) {
			remove_all_filters( $hook_name );
			$removed = true;
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'removed'    => $removed,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_has_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$priority = false;
		if ( function_exists( 'has_action' ) ) {
			$priority = has_action( $hook_name );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'exists'     => false !== $priority,
					'priority'   => false === $priority ? null : (int) $priority,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_current_filter( array $input ): array {
		$hook_name = function_exists( 'current_filter' ) ? (string) current_filter() : '';

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function resolve_funnel_payload( int $funnel_id ): ?array {
		if ( $funnel_id <= 0 ) {
			return null;
		}

		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getFunnelPayload' ) ) {
			$funnel = \FunnelkitTestStore::getFunnelPayload( $funnel_id );
			if ( is_array( $funnel ) ) {
				return $funnel;
			}
		}

		$funnel_object = null;
		if ( class_exists( '\WFFN_Funnel' ) ) {
			$candidate = new \WFFN_Funnel( $funnel_id );
			if ( is_object( $candidate ) && method_exists( $candidate, 'get_id' ) && (int) $candidate->get_id() > 0 ) {
				$funnel_object = $candidate;
			}
		}

		if ( ! $funnel_object ) {
			return null;
		}

		$step_refs = method_exists( $funnel_object, 'get_steps' ) ? (array) $funnel_object->get_steps() : [];
		$steps     = [];

		foreach ( $step_refs as $step_ref ) {
			$step_id = 0;
			if ( is_array( $step_ref ) ) {
				$step_id = (int) ( $step_ref['id'] ?? 0 );
			} elseif ( is_numeric( $step_ref ) ) {
				$step_id = (int) $step_ref;
			}

			if ( $step_id <= 0 ) {
				continue;
			}

			$step_payload = self::resolve_step_payload( $step_id );
			if ( $step_payload ) {
				$steps[] = $step_payload;
			}
		}

		$status = '';
		if ( function_exists( '\WFFN_Core' ) ) {
			$core = WFFN_Core();
			if ( is_object( $core ) && method_exists( $core, 'get_dB' ) ) {
				$db = $core->get_dB();
				if ( is_object( $db ) && method_exists( $db, 'get_meta' ) ) {
					$status_meta = $db->get_meta( $funnel_id, 'status' );
					$status = (string) $status_meta;
					if ( '1' === $status ) {
						$status = 'live';
					} elseif ( '0' === $status ) {
						$status = 'draft';
					}
				}
			}
		}

		return [
			'funnel_id'          => $funnel_id,
			'funnel_title'       => method_exists( $funnel_object, 'get_title' ) ? (string) $funnel_object->get_title() : '',
			'funnel_description' => method_exists( $funnel_object, 'get_desc' ) ? (string) $funnel_object->get_desc() : '',
			'funnel_status'      => $status,
			'total_steps'        => count( $steps ),
			'steps'              => $steps,
			'created_at'         => method_exists( $funnel_object, 'get_date_added' ) ? (string) $funnel_object->get_date_added() : '',
			'updated_at'         => method_exists( $funnel_object, 'get_last_update_date' ) ? (string) $funnel_object->get_last_update_date() : '',
		];
	}

	private static function resolve_step_payload( int $step_id ): ?array {
		if ( $step_id <= 0 ) {
			return null;
		}

		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getStepPayload' ) ) {
			$step = \FunnelkitTestStore::getStepPayload( $step_id );
			if ( is_array( $step ) ) {
				return $step;
			}
		}

		$step_post = get_post( $step_id );
		if ( ! is_object( $step_post ) ) {
			return null;
		}

		$post_type    = (string) ( $step_post->post_type ?? '' );
		$step_type    = self::resolve_step_type_from_post_type( $post_type );
		$funnel_id    = self::resolve_funnel_id_from_step( $step_id );
		$next_step_id = self::resolve_next_step_id( $funnel_id, $step_id );

		return [
			'step_id'      => $step_id,
			'funnel_id'    => $funnel_id,
			'step_type'    => $step_type,
			'step_title'   => (string) ( $step_post->post_title ?? '' ),
			'step_status'  => (string) ( $step_post->post_status ?? '' ),
			'step_url'     => get_permalink( $step_id ),
			'next_step_id' => $next_step_id,
			'created_at'   => (string) ( $step_post->post_date ?? '' ),
			'updated_at'   => (string) ( $step_post->post_modified ?? '' ),
		];
	}

	private static function resolve_next_step_id( int $funnel_id, int $step_id ): int {
		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getNextStepId' ) ) {
			$next = (int) \FunnelkitTestStore::getNextStepId( $step_id );
			if ( $next > 0 ) {
				return $next;
			}
		}

		if ( $funnel_id > 0 && class_exists( '\WFFN_Funnel' ) ) {
			$funnel = new \WFFN_Funnel( $funnel_id );
			if ( is_object( $funnel ) && method_exists( $funnel, 'get_next_step_id' ) ) {
				$next = $funnel->get_next_step_id( $step_id );
				if ( is_array( $next ) ) {
					$next_id = (int) ( $next['id'] ?? 0 );
					if ( $next_id > 0 ) {
						return $next_id;
					}
				} elseif ( is_numeric( $next ) ) {
					$next_id = (int) $next;
					if ( $next_id > 0 ) {
						return $next_id;
					}
				}
			}
		}

		return 0;
	}

	private static function resolve_funnel_id_from_step( int $step_id ): int {
		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getFunnelIdFromStep' ) ) {
			$funnel_id = (int) \FunnelkitTestStore::getFunnelIdFromStep( $step_id );
			if ( $funnel_id > 0 ) {
				return $funnel_id;
			}
		}

		return (int) get_post_meta( $step_id, '_bwf_in_funnel', true );
	}

	private static function resolve_step_type_from_post_type( string $post_type ): string {
		if ( class_exists( '\WFFN_Common' ) && method_exists( '\WFFN_Common', 'get_step_type' ) ) {
			$type = (string) \WFFN_Common::get_step_type( $post_type );
			if ( '' !== $type ) {
				return $type;
			}
		}

		$map = [
			'wfacp_checkout' => 'wc_checkout',
			'wfocu_offer'    => 'wc_upsells',
			'wffn_landing'   => 'landing',
			'wffn_ty'        => 'wc_thankyou',
			'wffn_optin'     => 'optin',
			'wffn_oty'       => 'optin_ty',
			'cartflows_step' => 'checkout',
		];

		return (string) ( $map[ $post_type ] ?? $post_type );
	}

	private static function resolve_funnel_id_from_value( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				return (int) $value->get_id();
			}
			if ( isset( $value->id ) ) {
				return (int) $value->id;
			}
			if ( isset( $value->ID ) ) {
				return (int) $value->ID;
			}
		}

		if ( is_array( $value ) ) {
			return (int) ( $value['funnel_id'] ?? ( $value['id'] ?? 0 ) );
		}

		return 0;
	}

	private static function resolve_step_id_from_value( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				return (int) $value->get_id();
			}
			if ( isset( $value->step_id ) ) {
				return (int) $value->step_id;
			}
			if ( isset( $value->id ) ) {
				return (int) $value->id;
			}
		}

		if ( is_array( $value ) ) {
			return (int) ( $value['step_id'] ?? ( $value['id'] ?? 0 ) );
		}

		return 0;
	}

	private static function matches_selected_funnel( array $config, int $funnel_id ): bool {
		$selected_funnel = self::resolve_selected_id( $config, [ 'funnel_id' ] );
		if ( null === $selected_funnel ) {
			return true;
		}

		return $funnel_id > 0 && $selected_funnel === $funnel_id;
	}

	private static function matches_selected_step( array $config, int $step_id ): bool {
		$selected_step = self::resolve_selected_id( $config, [ 'step_id' ] );
		if ( null === $selected_step ) {
			return true;
		}

		return $step_id > 0 && $selected_step === $step_id;
	}

	private static function resolve_selected_id( array $config, array $keys ): ?int {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $config ) ) {
				continue;
			}

			$value = $config[ $key ];
			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			if ( '' === $value || null === $value || 'any' === $value ) {
				return null;
			}

			return (int) $value;
		}

		return null;
	}

	private static function resolve_funnel_id_for_action( array $config, array $input ): int {
		$candidates = [
			$config['funnel_id'] ?? 0,
			$input['funnel_id'] ?? 0,
			is_array( $input['funnel'] ?? null ) ? ( $input['funnel']['funnel_id'] ?? ( $input['funnel']['id'] ?? 0 ) ) : 0,
			is_array( $input['step'] ?? null ) ? ( $input['step']['funnel_id'] ?? 0 ) : 0,
			is_array( $input['current_step'] ?? null ) ? ( $input['current_step']['funnel_id'] ?? 0 ) : 0,
			is_array( $input['next_step'] ?? null ) ? ( $input['next_step']['funnel_id'] ?? 0 ) : 0,
		];

		foreach ( $candidates as $candidate ) {
			$funnel_id = (int) $candidate;
			if ( $funnel_id > 0 ) {
				return $funnel_id;
			}
		}

		return 0;
	}

	private static function resolve_step_id_for_action( array $config, array $input ): int {
		$candidates = [
			$config['step_id'] ?? 0,
			$input['step_id'] ?? 0,
			is_array( $input['step'] ?? null ) ? ( $input['step']['step_id'] ?? ( $input['step']['id'] ?? 0 ) ) : 0,
			is_array( $input['current_step'] ?? null ) ? ( $input['current_step']['step_id'] ?? ( $input['current_step']['id'] ?? 0 ) ) : 0,
			is_array( $input['next_step'] ?? null ) ? ( $input['next_step']['step_id'] ?? ( $input['next_step']['id'] ?? 0 ) ) : 0,
		];

		foreach ( $candidates as $candidate ) {
			$step_id = (int) $candidate;
			if ( $step_id > 0 ) {
				return $step_id;
			}
		}

		return 0;
	}

	private static function normalize_payload_value( $value, int $depth = 0 ) {
		if ( $depth >= 4 ) {
			return null;
		}

		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$normalized = [];
			$count = 0;
			foreach ( $value as $key => $item ) {
				if ( $count >= 20 ) {
					break;
				}
				$normalized[ $key ] = self::normalize_payload_value( $item, $depth + 1 );
				++$count;
			}

			return $normalized;
		}

		if ( is_object( $value ) ) {
			$base = [
				'class' => get_class( $value ),
			];

			if ( method_exists( $value, 'get_id' ) ) {
				$base['id'] = (int) $value->get_id();
			} elseif ( isset( $value->ID ) ) {
				$base['id'] = (int) $value->ID;
			} elseif ( isset( $value->id ) ) {
				$base['id'] = (int) $value->id;
			}

			if ( method_exists( $value, 'toArray' ) ) {
				$data = $value->toArray();
				if ( is_array( $data ) ) {
					$base['data'] = self::normalize_payload_value( $data, $depth + 1 );
				}
			}

			return $base;
		}

		return null;
	}

	public static function query_funnels( $q ): array {
		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Funnel',
			],
		];

		$q      = is_array( $q ) ? $q : [];
		$search = trim( (string) ( $q['search'] ?? '' ) );

		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getFunnels' ) ) {
			$funnels = (array) \FunnelkitTestStore::getFunnels();
			foreach ( $funnels as $funnel ) {
				$options[] = [
					'name'  => (string) ( $funnel['id'] ?? '' ),
					'label' => (string) ( $funnel['title'] ?? '' ),
				];
			}

			return $options;
		}

		if ( function_exists( '\WFFN_Core' ) ) {
			$core = WFFN_Core();
			if ( is_object( $core ) && isset( $core->admin ) && is_object( $core->admin ) && method_exists( $core->admin, 'get_funnels' ) ) {
				$funnels = $core->admin->get_funnels(
					[
						'search_filter' => true,
						'limit'         => 100,
						's'             => $search,
					]
				);

				if ( is_array( $funnels ) ) {
					foreach ( $funnels as $funnel ) {
						$id    = (int) ( $funnel['id'] ?? 0 );
						$title = (string) ( $funnel['name'] ?? '' );
						if ( $id <= 0 ) {
							continue;
						}
						$options[] = [
							'name'  => (string) $id,
							'label' => '' !== $title ? $title : 'Funnel #' . $id,
						];
					}
				}
			}
		}

		return $options;
	}

	public static function query_steps( $q ): array {
		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Step',
			],
		];

		$q         = is_array( $q ) ? $q : [];
		$funnel_id = (int) ( $q['funnel_id'] ?? 0 );

		if ( class_exists( '\FunnelkitTestStore' ) && method_exists( '\FunnelkitTestStore', 'getSteps' ) ) {
			$steps = (array) \FunnelkitTestStore::getSteps( $funnel_id > 0 ? $funnel_id : null );
			foreach ( $steps as $step ) {
				$step_type = (string) ( $step['step_type'] ?? '' );
				$label = (string) ( $step['title'] ?? '' );
				if ( '' !== $step_type ) {
					$label .= ' (' . $step_type . ')';
				}

				$options[] = [
					'name'  => (string) ( $step['id'] ?? '' ),
					'label' => $label,
				];
			}

			return $options;
		}

		if ( function_exists( '\WFFN_Core' ) ) {
			$core = WFFN_Core();
			if ( is_object( $core ) && isset( $core->admin ) && is_object( $core->admin ) && method_exists( $core->admin, 'get_funnels' ) ) {
				$funnels = $core->admin->get_funnels(
					[
						'limit'           => 100,
						'need_steps_data' => true,
						'context'         => 'listing',
					]
				);

				$funnel_items = [];
				if ( is_array( $funnels ) && isset( $funnels['items'] ) && is_array( $funnels['items'] ) ) {
					$funnel_items = $funnels['items'];
				}

				$seen = [];
				foreach ( $funnel_items as $funnel ) {
					$current_funnel_id = (int) ( $funnel['id'] ?? 0 );
					if ( $funnel_id > 0 && $current_funnel_id !== $funnel_id ) {
						continue;
					}

					$steps_data = is_array( $funnel['steps_data'] ?? null ) ? $funnel['steps_data'] : [];
					foreach ( $steps_data as $step_data ) {
						$step_id = (int) ( $step_data['id'] ?? 0 );
						if ( $step_id <= 0 || isset( $seen[ $step_id ] ) ) {
							continue;
						}
						$seen[ $step_id ] = true;

						$post = get_post( $step_id );
						$label = is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
						if ( '' === $label ) {
							$label = 'Step #' . $step_id;
						}

						$step_type = (string) ( $step_data['type'] ?? '' );
						if ( '' !== $step_type ) {
							$label .= ' (' . $step_type . ')';
						}

						$options[] = [
							'name'  => (string) $step_id,
							'label' => $label,
						];
					}
				}
			}
		}

		return $options;
	}

	private static function is_funnelkit_available(): bool {
		return class_exists( '\WFFN_Funnel' ) || function_exists( '\WFFN_Core' ) || defined( 'WFFN_VERSION' ) || class_exists( '\FunnelkitTestStore' );
	}

	private static function action_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function action_error( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}
}

