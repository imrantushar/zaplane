<?php
/**
 * Products with several prices: choosing one, sending it as a card on every
 * channel, and ordering the chosen price.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Commerce\Commerce;
use Zaplane\Modules\Inbox\Channels\{Messenger, Whatsapp};
use Zaplane\Modules\Inbox\Models\Identity;

zt_run( function () {
	$store = Commerce::store();
	if ( ! $store ) {
		zt_ok( 'a shop is active (StoreEngine or WooCommerce)', false );
		return;
	}

	// Find a product with more than one price.
	$multi = null;
	foreach ( $store::search( '', 20 ) as $p ) {
		if ( count( $p['options'] ) > 1 ) {
			$multi = $p;
			break;
		}
	}
	zt_ok( 'a product with several prices lists them as options', null !== $multi && '' !== $multi['options'][1]['label'] && '' !== $multi['options'][1]['price_text'] );
	if ( ! $multi ) {
		return;
	}
	$second = $multi['options'][1];

	$picked = $store::product( (int) $multi['id'], (int) $second['id'] );
	zt_ok( 'choosing an option prices the product with it', $second['id'] === $picked['option_id'] && $second['price_text'] === $picked['price_text'] && $second['label'] === $picked['option_label'] );
	zt_ok( 'an unknown option falls back to the first', $multi['options'][0]['id'] === $store::product( (int) $multi['id'], 999999 )['option_id'] );

	// Admin REST: the chosen option rides on the card.
	$cv = zt_conversation( 'web', 'human' );
	$r  = new WP_REST_Request( 'POST', "/zaplane/v1/inbox/conversations/{$cv->id}/product" );
	$r->set_param( 'product_id', $multi['id'] );
	$r->set_param( 'option_id', $second['id'] );
	$res  = rest_do_request( $r );
	$card = $res->get_data()['message']['attachments'][0] ?? [];
	zt_ok( 'sent card carries the option, its price and label', 200 === $res->get_status() && $second['id'] === $card['option_id'] && $second['price_text'] === $card['price_text'] && $second['label'] === $card['option_label'] );

	// Workflow item spec with an option: "id/option:qty".
	$items = Commerce::parse_items( "{$multi['id']}/{$second['id']}:2, {$multi['id']}:1, {$multi['id']}/{$second['id']}" );
	zt_ok( 'items "12/34:2" keep the option; same option adds up', 2 === count( $items ) && $second['id'] === $items[0]['option_id'] && 3 === $items[0]['qty'] && 0 === $items[1]['option_id'] );

	// The order uses the chosen price.
	$order = Commerce::place_order( $cv, [ [ 'product_id' => (int) $multi['id'], 'option_id' => (int) $second['id'], 'qty' => 2 ] ], [ 'name' => 'ZZ Buyer', 'phone' => '01700000000', 'address' => 'Road 1' ], 'agent', 1 );
	zt_ok( 'the order is charged the chosen price', ! is_wp_error( $order ) && abs( (float) preg_replace( '/[^\d.]/', '', $order['total_text'] ) - 2 * $second['price'] ) < 0.01 );

	// Messenger: a generic template with picture, title, price and a button; the note goes first.
	$calls = [];
	zt_mock_meta( $calls );
	zt_fake_channel_credentials();
	$cm = zt_conversation( 'messenger', 'human', [ 'customer' => 'PS_PROD' ] );
	$im = Identity::where( 'id', $cm->identity_id )->fresh()->first();
	$sent = Commerce::send_product( $cm, (int) $multi['id'], 'agent', 1, 'Here it is!', (int) $second['id'] );
	zt_ok( 'the stored message keeps a text copy (list preview, search)', false !== strpos( (string) $sent->body, $second['label'] ) && false !== strpos( (string) $sent->body, 'Here it is!' ) );
	$calls = [];
	$out   = Messenger::deliver( $cm, $im, $sent );
	$tpl   = $calls[1]['body']['message']['attachment']['payload']['elements'][0] ?? [];
	zt_ok( 'Messenger: note first, then the card', 'sent' === $out['status'] && 2 === count( $calls ) && 'Here it is!' === $calls[0]['body']['message']['text'] );
	zt_ok( '…a generic template: title, "price · option" subtitle, View product button', $multi['name'] === $tpl['title'] && 0 === strpos( $tpl['subtitle'], $second['price_text'] ) && false !== strpos( $tpl['subtitle'], $second['label'] ) && 'web_url' === $tpl['buttons'][0]['type'] && $multi['url'] === $tpl['buttons'][0]['url'] );

	// WhatsApp: an interactive "View product" message with the picture on top.
	$cw   = zt_conversation( 'whatsapp', 'human', [ 'customer' => '8801700000055', 'account' => 'PN1' ] );
	$iw   = Identity::where( 'id', $cw->identity_id )->fresh()->first();
	$sent = Commerce::send_product( $cw, (int) $multi['id'], 'agent', 1, '', (int) $second['id'] );
	$calls = [];
	Whatsapp::deliver( $cw, $iw, $sent );
	$b = $calls[0]['body'];
	zt_ok( 'WhatsApp: a cta_url message, name in bold, price in the body', 'interactive' === $b['type'] && 'cta_url' === $b['interactive']['type'] && false !== strpos( $b['interactive']['body']['text'], '*' . $multi['name'] . '*' ) && false !== strpos( $b['interactive']['body']['text'], $second['price_text'] ) && $multi['url'] === $b['interactive']['action']['parameters']['url'] );

	$pic = Whatsapp::product_message( [ 'name' => 'Kurta', 'price_text' => '৳1,200', 'in_stock' => true, 'url' => 'https://x.test/k', 'image' => 'https://x.test/k.jpg' ] );
	zt_ok( '…with the picture as its header when there is one', 'image' === $pic['interactive']['header']['type'] && 'https://x.test/k.jpg' === $pic['interactive']['header']['image']['link'] );
	$tpl = Messenger::product_template( [ 'name' => 'Kurta', 'price_text' => '৳1,200', 'compare_text' => '৳1,500', 'in_stock' => false, 'url' => 'https://x.test/k', 'image' => 'https://x.test/k.jpg' ] );
	zt_ok( 'sale and stock show in the subtitle', '৳1,200 (was ৳1,500) · Out of stock' === $tpl['payload']['elements'][0]['subtitle'] && 'https://x.test/k.jpg' === $tpl['payload']['elements'][0]['image_url'] );

	// A card the channel refuses falls back to text so the customer still gets it.
	remove_all_filters( 'pre_http_request' );
	$tries = [];
	add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$tries ) {
		$body    = json_decode( (string) $args['body'], true );
		$tries[] = $body;
		$ok      = empty( $body['message']['attachment'] );
		return [ 'headers' => [], 'body' => $ok ? '{"message_id":"m_ok"}' : '{"error":{"message":"bad image"}}', 'response' => [ 'code' => $ok ? 200 : 400 ], 'cookies' => [] ];
	}, 10, 3 );
	zt_fake_channel_credentials();
	$sent = Commerce::send_product( $cm, (int) $multi['id'], 'agent', 1 );
	$out  = Messenger::deliver( $cm, $im, $sent );
	zt_ok( 'card refused → sent as text with the link', 'sent' === $out['status'] && false !== strpos( (string) end( $tries )['message']['text'], $multi['url'] ) );
} );
