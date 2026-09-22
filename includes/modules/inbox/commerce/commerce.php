<?php

namespace Zaplane\Modules\Inbox\Commerce;

use WP_Error;
use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Services\Conversations;
use Zaplane\Modules\Inbox\Services\Outbound;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selling inside a conversation: the shop in use, product cards, and
 * cash-on-delivery orders tied back to the conversation they came from.
 */
class Commerce {

	/** Orders the assistant may place in one conversation per day. */
	public const AI_ORDER_LIMIT = 3;

	/**
	 * The shop to sell from, or null when none is active.
	 *
	 * @return class-string<StoreInterface>|null
	 */
	public static function store(): ?string {
		$stores = [ StoreengineStore::class, WoocommerceStore::class ];

		/**
		 * Filter the shops the inbox can sell from, in order of preference.
		 *
		 * @param array<int,string> $stores Classes implementing StoreInterface.
		 */
		foreach ( (array) apply_filters( 'zaplane/inbox/stores', $stores ) as $class ) {
			if ( is_string( $class ) && is_subclass_of( $class, StoreInterface::class ) && $class::available() ) {
				return $class;
			}
		}
		return null;
	}

	/**
	 * Send one product as a card. The widget draws the card; text channels get
	 * the name, price and link in the message itself.
	 *
	 * @return \Zaplane\Modules\Inbox\Models\Message|WP_Error
	 */
	public static function send_product( Conversation $conversation, int $product_id, string $sender_type, int $sender_id = 0, string $text = '', int $option_id = 0 ) {
		$store   = self::store();
		$product = $store ? $store::product( $product_id, $option_id ) : null;
		if ( ! $product ) {
			return new WP_Error( 'zaplane_inbox_no_product', __( 'That product is not available.', 'zaplane' ) );
		}

		// The body keeps a plain-text copy for previews, search and the
		// assistant's context; Messenger and WhatsApp send the card itself
		// (picture, name, price, button) with the note before it.
		$note = trim( $text );
		$body = $note;
		if ( 'web' !== $conversation->channel ) {
			$body = trim( $body . "\n\n" . self::product_line( $product ) );
		}

		return Outbound::send( $conversation, $body, [
			'sender_type' => $sender_type,
			'sender_id'   => $sender_id,
			'attachments' => [ self::card( $product ) ],
			'meta'        => [ 'product_note' => $note ],
		] );
	}

	/**
	 * @param array<string,mixed> $product
	 * @return array<string,mixed>
	 */
	public static function card( array $product ): array {
		return [
			'type'       => 'product',
			'product_id'   => (int) $product['id'],
			'option_id'    => (int) ( $product['option_id'] ?? 0 ),
			'option_label' => (string) ( $product['option_label'] ?? '' ),
			'name'         => (string) $product['name'],
			'price_text'   => (string) $product['price_text'],
			'compare_text' => (string) ( $product['compare_text'] ?? '' ),
			'in_stock'     => (bool) $product['in_stock'],
			'url'          => (string) $product['url'],
			'image'        => (string) $product['image'],
		];
	}

	/**
	 * The product card on a message, if it carries one.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function card_of( $message ): ?array {
		foreach ( (array) ( $message->attachments ?? [] ) as $a ) {
			if ( is_array( $a ) && 'product' === ( $a['type'] ?? '' ) && ! empty( $a['name'] ) ) {
				return $a;
			}
		}
		return null;
	}

	/**
	 * "৳1,200 · Large · Out of stock": the line under a card's name.
	 */
	public static function card_subtitle( array $card ): string {
		$price = (string) ( $card['price_text'] ?? '' );
		if ( ! empty( $card['compare_text'] ) ) {
			/* translators: 1: sale price, 2: regular price. */
			$price = sprintf( __( '%1$s (was %2$s)', 'zaplane' ), $price, $card['compare_text'] );
		}
		return implode( ' · ', array_filter( [
			$price,
			(string) ( $card['option_label'] ?? '' ),
			empty( $card['in_stock'] ) ? __( 'Out of stock', 'zaplane' ) : '',
		] ) );
	}

	/**
	 * @param array<string,mixed> $product
	 */
	public static function product_line( array $product ): string {
		$name = $product['name'] . ( ! empty( $product['option_label'] ) ? ' (' . $product['option_label'] . ')' : '' );
		return sprintf( '%1$s — %2$s%3$s', $name, $product['price_text'], $product['url'] ? "\n" . $product['url'] : '' );
	}

	/**
	 * Parse "12:2, 15" (product id, optional quantity) into order items.
	 *
	 * @return array<int,array{product_id:int,qty:int}>
	 */
	public static function parse_items( string $spec ): array {
		$items = [];
		foreach ( preg_split( '/[,;\n]+/', $spec ) as $part ) {
			// "12", "12:2", or with a price option/variation "12/34:2".
			if ( ! preg_match( '/^\s*#?(\d+)\s*(?:[\/@]\s*(\d+))?\s*(?:[:x×*]\s*(\d+))?\s*$/u', $part, $m ) ) {
				continue;
			}
			$id            = (int) $m[1];
			$option        = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
			$qty           = isset( $m[3] ) ? max( 1, min( 99, (int) $m[3] ) ) : 1;
			$key           = $id . '/' . $option;
			$items[ $key ] = [
				'product_id' => $id,
				'option_id'  => $option,
				'qty'        => ( $items[ $key ]['qty'] ?? 0 ) + $qty,
			];
		}
		return array_values( $items );
	}

	/**
	 * Place a cash-on-delivery order for the conversation's contact.
	 *
	 * @param array<int,array{product_id:int,qty:int}> $items
	 * @param array<string,string> $customer Overrides for the contact's details, plus address/city/note.
	 * @param string $placed_by `agent`, `ai` or `workflow`.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function place_order( Conversation $conversation, array $items, array $customer, string $placed_by, int $user_id = 0 ) {
		$store = self::store();
		if ( ! $store ) {
			return new WP_Error( 'zaplane_inbox_no_store', __( 'No shop is active on this site.', 'zaplane' ) );
		}
		if ( empty( $items ) ) {
			return new WP_Error( 'zaplane_inbox_order_empty', __( 'Add at least one product.', 'zaplane' ) );
		}

		if ( 'ai' === $placed_by && self::orders_today( $conversation ) >= self::AI_ORDER_LIMIT ) {
			return new WP_Error( 'zaplane_inbox_order_limit', __( 'The assistant has already placed the most orders allowed for this conversation today. A teammate will help.', 'zaplane' ) );
		}

		$contact  = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		$customer = array_map( 'strval', $customer ) + [
			'name'  => $contact ? (string) $contact->name : '',
			'phone' => $contact ? (string) $contact->phone : '',
			'email' => $contact ? (string) $contact->email : '',
		];
		foreach ( [ 'name', 'phone', 'email' ] as $key ) {
			if ( '' === trim( $customer[ $key ] ?? '' ) && $contact ) {
				$customer[ $key ] = (string) $contact->{$key};
			}
		}
		$customer = array_map( 'sanitize_text_field', $customer );

		if ( '' === $customer['phone'] || '' === trim( $customer['address'] ?? '' ) ) {
			return new WP_Error( 'zaplane_inbox_order_details', __( 'A phone number and a delivery address are needed for a cash-on-delivery order.', 'zaplane' ) );
		}

		$result = $store::create_order( $items, $customer, [
			'conversation_id' => (int) $conversation->id,
			'wp_user_id'      => $contact ? (int) $contact->wp_user_id : 0,
			'placed_by'       => $placed_by,
		] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Keep what we learned about the customer.
		if ( $contact ) {
			foreach ( [ 'name', 'phone' ] as $key ) {
				if ( empty( $contact->{$key} ) && '' !== $customer[ $key ] ) {
					$contact->{$key} = $customer[ $key ];
				}
			}
			if ( empty( $contact->email ) && is_email( $customer['email'] ) ) {
				$contact->email = strtolower( $customer['email'] );
			}
			$contact->save();
		}

		$meta             = is_array( $conversation->meta ) ? $conversation->meta : [];
		$meta['orders']   = array_slice( array_merge( (array) ( $meta['orders'] ?? [] ), [ [
			'id'        => (int) $result['id'],
			'number'    => (string) $result['number'],
			'total'     => (string) $result['total_text'],
			'store'     => $store::slug(),
			'placed_by' => $placed_by,
			'at'        => Conversations::now(),
		] ] ), -20 );
		$conversation->meta = $meta;
		$conversation->save();

		Conversations::add_tags( $conversation, [ __( 'ordered', 'zaplane' ) ] );
		Conversations::system_note( $conversation, sprintf(
			/* translators: 1: order number, 2: order total, 3: who placed it. */
			__( 'Order #%1$s placed (%2$s, cash on delivery) by %3$s.', 'zaplane' ),
			$result['number'],
			$result['total_text'],
			'ai' === $placed_by ? __( 'the assistant', 'zaplane' ) : ( 'workflow' === $placed_by ? __( 'a workflow', 'zaplane' ) : ( $user_id ? get_the_author_meta( 'display_name', $user_id ) : __( 'the team', 'zaplane' ) ) )
		) );

		/**
		 * An order was placed from a conversation.
		 *
		 * @param array<string,mixed> $payload
		 */
		do_action( 'zaplane/inbox/order_created', Conversations::payload( $conversation, [
			'order_id'     => (int) $result['id'],
			'order_number' => (string) $result['number'],
			'order_total'  => (string) $result['total_text'],
			'store'        => $store::slug(),
			'placed_by'    => $placed_by,
		] ) );

		return $result;
	}

	private static function orders_today( Conversation $conversation ): int {
		$meta  = is_array( $conversation->meta ) ? $conversation->meta : [];
		$today = substr( Conversations::now(), 0, 10 );
		$n     = 0;
		foreach ( (array) ( $meta['orders'] ?? [] ) as $order ) {
			if ( 'ai' === ( $order['placed_by'] ?? '' ) && 0 === strpos( (string) ( $order['at'] ?? '' ), $today ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Map chat-collected details onto a shop address.
	 *
	 * @param array<string,string> $customer
	 * @return array<string,string>
	 */
	public static function address_fields( array $customer ): array {
		$name  = trim( (string) ( $customer['name'] ?? '' ) );
		$parts = preg_split( '/\s+/', $name, 2 );

		return array_filter( [
			'first_name' => $parts[0] ?? '',
			'last_name'  => $parts[1] ?? '',
			'phone'      => (string) ( $customer['phone'] ?? '' ),
			'email'      => is_email( (string) ( $customer['email'] ?? '' ) ) ? (string) $customer['email'] : '',
			'address_1'  => (string) ( $customer['address'] ?? '' ),
			'city'       => (string) ( $customer['city'] ?? '' ),
			'country'    => (string) ( $customer['country'] ?? '' ),
		], 'strlen' );
	}

	/**
	 * @param array<string,string> $customer
	 * @param array<string,mixed>  $context
	 */
	public static function order_note( array $customer, array $context ): string {
		$note = sprintf(
			/* translators: %d: conversation id. */
			__( 'Placed from Zaplane Inbox conversation #%d.', 'zaplane' ),
			(int) ( $context['conversation_id'] ?? 0 )
		);
		if ( ! empty( $customer['note'] ) ) {
			$note .= ' ' . __( 'Customer note:', 'zaplane' ) . ' ' . $customer['note'];
		}
		return $note;
	}
}
