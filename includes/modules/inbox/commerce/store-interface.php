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
	 *         in_stock, url, image, option_id, option_label, and `options`
	 *         (the product's prices or variations: id, label, price,
	 *         price_text, compare_text, in_stock) when it has more than one.
	 */
	public static function search( string $query, int $limit = 6 ): array;

	/**
	 * One product, priced with the chosen option (its first when 0 or unknown).
	 *
	 * @return array<string,mixed>|null Same shape as one search() result.
	 */
	public static function product( int $id, int $option_id = 0 ): ?array;

	/**
	 * Place a cash-on-delivery order.
	 *
	 * @param array<int,array{product_id:int,qty:int,option_id?:int}> $items
	 * @param array<string,string> $customer name, phone, email, address, city, note.
	 * @param array<string,mixed>  $context  conversation_id, wp_user_id.
	 * @return array<string,mixed>|\WP_Error id, number, total_text, admin_url.
	 */
	public static function create_order( array $items, array $customer, array $context );
}
