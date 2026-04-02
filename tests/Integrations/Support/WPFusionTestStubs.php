<?php

namespace {
	if ( ! class_exists( 'WPFusionUserStub' ) ) {
		class WPFusionUserStub {
			public array $contact_ids = [];
			public array $user_ids = [];
			public array $tag_actions = [];
			public array $imports = [];

			public function apply_tags( $tags, $user_id = false ) {
				$this->tag_actions[] = [
					'action'  => 'apply',
					'user_id' => $user_id,
					'tags'    => (array) $tags,
				];

				return true;
			}

			public function remove_tags( $tags, $user_id = false ) {
				$this->tag_actions[] = [
					'action'  => 'remove',
					'user_id' => $user_id,
					'tags'    => (array) $tags,
				];

				return true;
			}

			public function get_contact_id( $user_id, $force_update = false ) {
				unset( $force_update );

				return $this->contact_ids[ (string) $user_id ] ?? false;
			}

			public function get_user_id( $contact_id ) {
				return $this->user_ids[ (string) $contact_id ] ?? false;
			}

			public function import_user( $contact_id, $send_notification = false, $role = false ) {
				$user_id = $this->user_ids[ (string) $contact_id ] ?? 999;

				$this->imports[] = [
					'contact_id'        => $contact_id,
					'user_id'           => $user_id,
					'send_notification' => $send_notification,
					'role'              => $role,
				];

				return $user_id;
			}
		}
	}

	if ( ! class_exists( 'WPFusionCrmStub' ) ) {
		class WPFusionCrmStub {
			public string $name = 'WP Fusion Test CRM';
			public string $slug = 'wp-fusion-test';
			public array $contacts_by_email = [];
			public array $contacts = [];
			public int $next_contact_id = 1000;
			public array $last_add_data = [];
			public array $last_update = [];

			public function get_contact_id( $email ) {
				return $this->contacts_by_email[ strtolower( (string) $email ) ] ?? false;
			}

			public function add_contact( $data ) {
				$contact_id = 'cid_' . ++$this->next_contact_id;
				$email      = strtolower( (string) ( $data['user_email'] ?? $data['email'] ?? '' ) );
				$this->last_add_data = $data;

				$this->contacts[ $contact_id ] = $data;
				if ( '' !== $email ) {
					$this->contacts_by_email[ $email ] = $contact_id;
				}

				return $contact_id;
			}

			public function update_contact( $contact_id, $data ) {
				$current = $this->contacts[ (string) $contact_id ] ?? [];
				$updated = array_merge( $current, $data );
				$email   = strtolower( (string) ( $updated['user_email'] ?? $updated['email'] ?? '' ) );
				$this->last_update = [
					'contact_id' => (string) $contact_id,
					'data'       => $data,
				];

				$this->contacts[ (string) $contact_id ] = $updated;
				if ( '' !== $email ) {
					$this->contacts_by_email[ $email ] = (string) $contact_id;
				}

				return true;
			}
		}
	}

	if ( ! class_exists( 'WPFusionSettingsStub' ) ) {
		class WPFusionSettingsStub {
			public array $available_tags = [];

			public function get_available_tags_flat( $grouped = true, $refresh = false ) {
				unset( $grouped, $refresh );

				return $this->available_tags;
			}
		}
	}

	if ( ! class_exists( 'WPFusionTestDouble' ) ) {
		class WPFusionTestDouble {
			private static ?self $instance = null;

			public WPFusionUserStub $user;
			public WPFusionCrmStub $crm;
			public WPFusionSettingsStub $settings;
			public array $options = [];

			public function __construct() {
				$this->user     = new WPFusionUserStub();
				$this->crm      = new WPFusionCrmStub();
				$this->settings = new WPFusionSettingsStub();
			}

			public static function instance(): self {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			public static function reset(): void {
				self::$instance = new self();
			}

			public function getOption( string $key, $default = false ) {
				return $this->options[ $key ] ?? $default;
			}

			public function setOption( string $key, $value ): void {
				$this->options[ $key ] = $value;
			}
		}
	}

	if ( ! function_exists( 'wp_fusion' ) ) {
		function wp_fusion() {
			return WPFusionTestDouble::instance();
		}
	}

	if ( ! function_exists( 'wpf_get_option' ) ) {
		function wpf_get_option( $key, $default = false ) {
			return WPFusionTestDouble::instance()->getOption( (string) $key, $default );
		}
	}
}
