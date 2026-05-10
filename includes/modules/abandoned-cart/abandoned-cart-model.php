<?php

namespace Zaplane\Modules\AbandonedCart;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartModel extends Model {

	protected static string $table = 'abandonned_cart';

	public function get_cart_contents(): array {
		if ( ! $this->cart ) {
			return [];
		}
		$data = json_decode( $this->cart, true );
		return is_array( $data ) ? $data : [];
	}

	public function get_formatted_total(): string {
		return wc_price( $this->total ?? 0, [ 'currency' => $this->currency ] );
	}

	public function to_list_item(): array {
		return [
			'id'           => $this->id,
			'full_name'    => $this->full_name,
			'email'        => $this->email,
			'status'       => $this->status,
			'total'        => $this->total,
			'currency'     => $this->currency,
			'subtotal'     => $this->subtotal,
			'shipping'     => $this->shipping,
			'tax'          => $this->tax,
			'discounts'    => $this->discounts,
			'fees'         => $this->fees,
			'checkout_key' => $this->checkout_key,
			'cart_hash'    => $this->cart_hash,
			'is_optout'    => $this->is_optout,
			'user_id'      => $this->user_id,
			'contact_id'   => $this->contact_id,
			'order_id'     => $this->order_id,
			'click_counts' => $this->click_counts,
			'note'         => $this->note,
			'provider'     => $this->provider,
			'cart'         => $this->get_cart_contents(),
			'abandoned_at' => $this->abandoned_at,
			'recovered_at' => $this->recovered_at,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		];
	}
}
