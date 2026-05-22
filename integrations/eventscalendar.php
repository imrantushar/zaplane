<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Eventscalendar extends IntegrationBase {

    public static function get_slug(): string { return 'eventscalendar'; }

	public static function get_name(): string {
		return 'The Events Calendar';
	}

	public static function get_icon(): string {
		return 'events-calendar.svg';
	}

    public static function get_triggers(): array {
        return [
            'attendEvent'          => [
                'label' => 'User attended an event',
                'hook'  => 'event_tickets_checkin'
            ],
             'attendeeRegistered'  => [
                'label' => 'Attendee registered for an event',
                'hook' => 'event_tickets_rsvp_attendee_created'
            ],
             'newAttendee'         => [
                'label' => 'New attendee registered',
                'hook' => 'event_tickets_rsvp_tickets_generated_for_product'
            ],
            'attendeeRegisteredWc' => [
                'label' => 'Attendee registered via WooCommerce',
                'hook' => 'tribe_tickets_attendee_repository_create_attendee_for_ticket_after_create'
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {

            case 'attendEvent':
                $attendee_id = $args[0] ?? 0;
                if ( ! $attendee_id ) {
                    return false;
                }
                $attendee = get_post( $attendee_id );
                if ( ! $attendee ) {
                    return false;
                }
                $user_id  = get_post_meta( $attendee_id, '_tribe_tickets_meta_user_id', true )
                         ?: get_post_meta( $attendee_id, '_tribe_rsvp_user_id', true );
                $event_id = ! empty( $args[1] ) ? $args[1]
                         : ( get_post_meta( $attendee_id, '_tribe_rsvp_event', true )
                         ?: get_post_meta( $attendee_id, '_tribe_tickets_checkin_event_id', true ) );
                $user     = $user_id ? get_user_by( 'id', $user_id ) : null;
                $event    = $event_id ? get_post( $event_id ) : null;
                return [
                    'attendee_id'   => (int) $attendee_id,
                    'attendee_name' => $attendee->post_title,
                    'event_id'      => $event_id ? (int) $event_id : '',
                    'event_title'   => $event ? $event->post_title : '',
                    'event_url'     => $event ? get_permalink( $event->ID ) : '',
                    'user_id'       => $user ? (int) $user->ID : '',
                    'user_email'    => $user ? $user->user_email : '',
                    'display_name'  => $user ? $user->display_name : '',
                    'checked_in_at' => current_time( 'mysql' ),
                ];

            case 'attendeeRegistered':
                return [
                    'attendee_id' => $args[0] ?? '',
                    'post_id'     => $args[1] ?? '',
                    'order_id'    => $args[2] ?? '',
                    'product_id'  => $args[3] ?? '',
                ];

            case 'newAttendee':
                return [
                    'product_id' => $args[0] ?? '',
                    'order_id'   => $args[1] ?? '',
                    'attendees'  => $args[2] ?? [],
                ];

            case 'attendeeRegisteredWc':
                return [
                    'attendee_id'   => $args[0] ?? '',
                    'ticket'        => $args[1] ?? '',
                    'order'         => $args[2] ?? '',
                    'attendee_data' => $args[3] ?? [],
                ];
        }

        return false;
    }
}
