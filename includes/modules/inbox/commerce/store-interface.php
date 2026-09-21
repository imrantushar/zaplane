<?php

namespace Zaplane\Modules\Inbox\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A shop the inbox can sell from: find products and place cash-on-delivery
 * orders for a customer in a conversation.
 */
interface StoreInterface {

	public static function slug(): string;

	public static function label(): string;

	public static function available(): bool;

	/**
	 * Published products matching a search, most relevant first.
	 *
	 * @return array<int,array<string,mixed>> Each: id, name, price, price_text,
	 *         in_stock, url, image.
	 */
	public static function search( string $query, int $limit = 6 ): array;

	/**
	 * @return array<string,mixed>|null Same shape as one search() result.
	 */
	public static function product( int $id ): ?array;

	/**
	 * Place a cash-on-delivery order.
	 *
	 * @param array<int,array{product_id:int,qty:int}> $items
	 * @param array<string,string> $customer name, phone, email, address, city, note.
	 * @param array<string,mixed>  $context  conversation_id, wp_user_id.
	 * @return array<string,mixed>|\WP_Error id, number, total_text, admin_url.
	 */
	public static function create_order( array $items, array $customer, array $context );
}
