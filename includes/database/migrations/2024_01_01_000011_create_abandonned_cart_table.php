<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAbandonnedCartTable extends Migration {

	public function up(): void {
		Schema::create( 'abandonned_cart', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'checkout_key', 192 )->nullable();
			$table->string( 'cart_hash', 192 )->nullable();
			$table->tinyInteger( 'is_optout' )->default( 0 );
			$table->string( 'full_name', 192 )->nullable();
			$table->string( 'email', 192 )->nullable();
			$table->string( 'provider', 100 )->default( 'woo' );
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->unsignedBigInteger( 'click_counts' )->default( 0 );
			$table->unsignedBigInteger( 'contact_id' )->nullable();
			$table->unsignedBigInteger( 'order_id' )->nullable();
			$table->unsignedBigInteger( 'automation_id' )->nullable();
			$table->unsignedBigInteger( 'checkout_page_id' )->nullable();
			$table->string( 'status', 30 )->default( 'draft' );
			$table->decimal( 'subtotal', 10, 2 )->nullable();
			$table->decimal( 'shipping', 10, 2 )->nullable();
			$table->decimal( 'tax', 10, 2 )->nullable();
			$table->decimal( 'discounts', 10, 2 )->nullable();
			$table->decimal( 'fees', 10, 2 )->nullable();
			$table->decimal( 'total', 10, 2 )->nullable();
			$table->string( 'currency', 50 )->nullable();
			$table->longText( 'cart' )->nullable();
			$table->text( 'note' )->nullable();
			$table->timestamp( 'abandoned_at' )->nullable();
			$table->timestamp( 'recovered_at' )->nullable();
			$table->timestamps();

			$table->index( 'status' );
			$table->index( 'checkout_key' );
			$table->index( 'email' );
		} );
	}

	public function down(): void {
		Schema::drop( 'abandonned_cart' );
	}
}
