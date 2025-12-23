<?php
namespace Zaplane\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Workflows {

	/**
	 * Get table name
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_workflows';
	}

	/**
	 * CREATE workflow
	 */
	public static function create( array $data ) {
		global $wpdb;

		$defaults = [
			'user_id'   => get_current_user_id(),
			'title'     => '',
			'name'      => '',
			'status'    => 'draft',
			'flow_json' => null,
		];

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			self::table(),
			[
				'user_id'   => (int) $data['user_id'],
				'title'     => sanitize_text_field( $data['title'] ),
				'name'      => sanitize_text_field( $data['name'] ),
				'status'    => $data['status'],
				'flow_json' => $data['flow_json'],
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * READ single workflow by ID
	 */
	public static function get_all( ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table()
			)
		);
	}

	/**
	 * READ single workflow by ID
	 */
	public static function get( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * READ workflows by user
	 */
	public static function get_by_user( $user_id, $status = null ) {
		global $wpdb;

		$sql  = "SELECT * FROM " . self::table() . " WHERE user_id = %d";
		$args = [ $user_id ];

		if ( $status ) {
			$sql  .= " AND status = %s";
			$args[] = $status;
		}

		$sql .= " ORDER BY created_at DESC";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$args )
		);
	}

	/**
	 * UPDATE workflow
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		$allowed = [ 'title', 'name', 'status', 'flow_json' ];
		$update  = [];
		$format  = [];

		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[] = $field === 'flow_json' ? '%s' : '%s';
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		return $wpdb->update(
			self::table(),
			$update,
			[ 'id' => (int) $id ],
			$format,
			[ '%d' ]
		);
	}

	/**
	 * DELETE workflow
	 */
	public static function delete( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::table(),
			[ 'id' => (int) $id ],
			[ '%d' ]
		);
	}

    public static function count( $user_id = null, $status = null ) {
        global $wpdb;

        $sql  = "SELECT COUNT(*) FROM " . self::table() . " WHERE 1=1";
        $args = [];

        if ( $user_id !== null ) {
            $sql   .= " AND user_id = %d";
            $args[] = (int) $user_id;
        }

        if ( $status !== null ) {
            $sql   .= " AND status = %s";
            $args[] = $status;
        }

        if ( ! empty( $args ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
        }

        return (int) $wpdb->get_var( $sql );
    }
}
