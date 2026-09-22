<?php
/**
 * Live Inbox checks against a real WordPress install, inside a transaction
 * that is always rolled back (InnoDB, no persistent object cache).
 *
 * Run all:  bash wp-content/plugins/zaplane/tests/inbox-live/run.sh
 * Run one:  wp eval-file wp-content/plugins/zaplane/tests/inbox-live/knowledge.php
 */

use Zaplane\Modules\Inbox\Models\{Contact, Conversation, Identity, Message};

function zt_ok( string $label, $pass ): void {
	echo ( $pass ? 'PASS ' : 'FAIL ' ) . $label . "\n";
}

/** Run $fn inside a transaction that is rolled back afterwards. */
function zt_run( callable $fn ): void {
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );
	try {
		wp_set_current_user( 1 );
		$fn();
	} catch ( \Throwable $e ) {
		echo 'ERROR ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
	} finally {
		$wpdb->query( 'ROLLBACK' );
	}
}

/** A contact + conversation (and identity for social channels). */
function zt_conversation( string $channel = 'web', string $handler = 'human', array $extra = [] ): Conversation {
	$contact  = Contact::create( [ 'name' => 'ZZ Test' ] );
	$identity = null;
	if ( 'web' !== $channel ) {
		$identity = Identity::create( [ 'contact_id' => $contact->id, 'channel' => $channel, 'account_id' => $extra['account'] ?? 'ACC', 'external_id' => $extra['customer'] ?? 'CUST_' . wp_rand() ] );
	}
	return Conversation::create( [
		'contact_id'       => $contact->id,
		'identity_id'      => $identity ? $identity->id : 0,
		'channel'          => $channel,
		'account_id'       => $extra['account'] ?? '',
		'status'           => 'open',
		'handler'          => $handler,
		'ai_enabled'       => 'bot' === $handler,
		'unread_count'     => 0,
		'meta'             => [],
		'last_customer_at' => current_time( 'mysql' ),
	] );
}

function zt_message( Conversation $c, string $body, string $type = 'contact', array $extra = [] ): Message {
	return Message::create( array_merge( [
		'conversation_id' => $c->id,
		'direction'       => 'contact' === $type ? 'in' : 'out',
		'sender_type'     => $type,
		'body'            => $body,
		'channel'         => $c->channel,
		'created_at'      => current_time( 'mysql' ),
	], $extra ) );
}

/** Newest message in a conversation. */
function zt_last( Conversation $c ): ?Message {
	return Message::where( 'conversation_id', $c->id )->orderBy( 'id', 'desc' )->fresh()->first();
}

/** Capture calls to Meta's Graph API and answer them successfully. */
function zt_mock_meta( array &$calls ): void {
	add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$calls ) {
		if ( false === strpos( $url, 'graph.facebook.com' ) ) {
			return $pre;
		}
		$calls[] = [
			'method' => $args['method'] ?? 'POST',
			'url'    => $url,
			'body'   => json_decode( (string) ( $args['body'] ?? '' ), true ),
		];
		return [
			'headers'  => [],
			'body'     => '{"success":true,"message_id":"m_' . count( $calls ) . '","messages":[{"id":"wamid.' . count( $calls ) . '"}]}',
			'response' => [ 'code' => 200 ],
			'cookies'  => [],
		];
	}, 10, 3 );
}

/** Pretend Messenger/WhatsApp have working connections. */
function zt_fake_channel_credentials(): void {
	\Zaplane\Modules\Inbox\Settings::save( [ 'channels' => [
		'messenger' => [ 'enabled' => true, 'connection_id' => 1 ],
		'whatsapp'  => [ 'enabled' => true, 'connection_id' => 1 ],
	] ] );
	$ref = new ReflectionProperty( \Zaplane\Modules\Inbox\Channels\MetaChannel::class, 'credentials' );
	$ref->setAccessible( true );
	$ref->setValue( null, [
		'messenger' => [ 'page_access_token' => 'T' ],
		'whatsapp'  => [ 'access_token' => 'W', 'phone_number_id' => 'PN1' ],
	] );
}

/** Seed a small knowledge base under $key. @return array<string,int> ids by name */
function zt_knowledge( string $key ): array {
	global $wpdb;
	$t   = \Zaplane\Models\Knowledge::getTable();
	$ids = [];
	$add = function ( $name, $title, $content, $source, $ref = null ) use ( $wpdb, $t, $key, &$ids ) {
		$wpdb->insert( $t, [ 'business_key' => $key, 'title' => $title, 'content' => $content, 'source' => $source, 'ref_id' => $ref ] );
		$ids[ $name ] = (int) $wpdb->insert_id;
	};
	$add( 'refund', 'What is your return policy?', 'You can return any item within 30 days for a full refund.', 'faq' );
	$add( 'bn_refund', 'ফেরত নীতি কী', 'আপনি ৩০ দিনের মধ্যে ফেরত দিতে পারবেন।', 'faq' );
	$add( 'hours', 'Opening hours', 'We are open 9am to 9pm every day.', 'faq' );
	$add( 'pay', 'Payment methods accepted', 'bKash, Nagad and cash on delivery.', 'faq' );
	$page = wp_insert_post( [ 'post_title' => 'Shipping and delivery times', 'post_content' => 'x', 'post_status' => 'publish', 'post_type' => 'page' ] );
	$add( 'shipping', 'Shipping and delivery times', 'We deliver across Bangladesh. Dhaka 1-2 days, Chattogram 2-3 days.', 'page', 'page_' . $page );
	$ids['page'] = (int) $page;
	return $ids;
}
