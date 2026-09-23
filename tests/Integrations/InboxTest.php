<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Inbox;

/**
 * Inbox: the contract only. Every action works on conversations and
 * messages in the database, and is covered end to end by the inbox-live
 * suite (tests/inbox-live) against a real site.
 */
class InboxTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Inbox::class;
	}

	public function test_it_offers_the_reply_and_routing_steps_the_inbox_workflows_use(): void {
		$actions = Inbox::get_actions();
		foreach ( [ 'send_reply', 'add_note', 'assign', 'set_status', 'forward_to_human' ] as $event ) {
			$this->assertArrayHasKey( $event, $actions );
		}
		$this->assertArrayHasKey( 'message_received', Inbox::get_triggers() );
	}
}
