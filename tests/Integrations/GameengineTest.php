<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gameengine;
use Zaplane\Tests\WPMocks;

/**
 * GameEngine integration: payload shapes, context gates (checkout, transfer,
 * expiry, payout, referral), config filters, the 28-action dispatch and the
 * Pro capability gate.
 *
 * The GameEngine managers are doubled in tests/mocks/gameengine.php; Pro is
 * off by default (no GAMEENGINE_PRO_VERSION) so the disabled/requires_addon
 * metadata can be asserted, and the Pro tests open the gate themselves.
 */
class GameengineTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Gameengine::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		// A marketplace coupon post for the coupon_generated trigger. The
		// priority get_post/get_post_meta doubles read the globals below,
		// not the WPMocks store, so seed both.
		$coupon = (object) [
			'ID'          => 50,
			'post_title'  => 'GE-TEST123',
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
			'post_author' => 2,
		];

		WPMocks::setPost( 50, (array) $coupon );
		$GLOBALS['zaplane_wp_posts'][50] = $coupon;
		$GLOBALS['zaplane_post_meta'][50] = [
			'discount_type' => [ 'percent' ],
			'coupon_amount' => [ '10' ],
		];

		// Starting balances on the default point type: user 2 is the
		// workflow's usual subject, user 1 (the current member) funds
		// transfers and payouts.
		\GameEngine\Classes\PointsManager::set_total( 2, 500, 1 );
		\GameEngine\Classes\PointsManager::set_total( 1, 1000, 1 );
	}

	protected function getTriggerTests(): array {
		return [
			'points_awarded'          => [ 2, 50, 'achievement_unlock', 15, 1 ],
			'points_deducted'         => [ 2, 25, 'reward_redeem', 16, 1 ],
			'points_balance_changed'  => [ 2, 40, 'daily_login', 17, 1 ],
			'achievement_earned'      => [ 2, 3, 7 ],
			'specific_achievement'    => [ 2, 3, 7 ],
			'level_reached'           => [ 2, 2, 8 ],
			'specific_level'          => [ 2, 2, 8 ],
			'rank_changed'            => [ 2, 'Bronze', 'Silver' ],
			'reward_redeemed'         => [ 2, 4, 500 ],
			'coupon_generated'        => [ 50, null, false ],
			'points_used_at_checkout' => [ 2, 100, 'wc_points_payment', 18, 1 ],
			'points_transferred'      => [ 2, 30, 'transfer_sent', 19, 1 ],
			'points_expired'          => [ 2, 75, 'expired', 20, 1 ],
			'payout_requested'        => [ 2, 1000, 'payout_request', 21, 1 ],
			'payout_rejected'         => [ 2, 1000, 'payout_refund', 22, 1 ],
			'referral_completed'      => [ 1, 2 ],
			'affiliate_reward'        => [ 2, 50, 'referral_signup', 23, 1 ],
		];
	}

	protected function getActionTests(): array {
		return [
			'award_points'       => [ 'user_id' => 2, 'points' => 10, 'reason' => 'Sample award', 'context' => 'manual_adjustment' ],
			'deduct_points'      => [ 'user_id' => 2, 'points' => 5, 'reason' => 'Sample deduction' ],
			'set_balance'        => [ 'user_id' => 2, 'points' => 120 ],
			'get_balance'        => [ 'user_id' => 2 ],
			'get_transactions'   => [ 'user_id' => 2, 'limit' => 5 ],
			'award_achievement'  => [ 'user_id' => 2, 'achievement_id' => 3 ],
			'revoke_achievement' => [ 'user_id' => 2, 'achievement_id' => 3 ],
			'get_achievements'   => [ 'user_id' => 2 ],
			'assign_level'       => [ 'user_id' => 2, 'level_id' => 2 ],
			'change_level'       => [ 'user_id' => 2, 'level_id' => 2 ],
			'get_level'          => [ 'user_id' => 2 ],
			'assign_rank'        => [ 'user_id' => 2, 'rank_id' => 2 ],
			'get_rank'           => [ 'user_id' => 2 ],
			'unlock_content'     => [ 'post_id' => 2 ],
			'lock_content'       => [
				'post_id'        => 2,
				'restrict_type'  => 'points',
				'restrict_value' => '100',
				'message'        => 'This page costs 100 points.',
			],
			'generate_coupon'    => [ 'user_id' => 2, 'amount' => 10, 'discount_type' => 'percent', 'points_cost' => 0 ],
			'redeem_reward'      => [ 'user_id' => 2, 'reward_id' => 4 ],
			'transfer_points'    => [ 'sender_id' => 1, 'receiver_id' => 2, 'points' => 30 ],
			'create_payout'      => [ 'user_id' => 2, 'points' => 100, 'method' => 'paypal', 'account' => 'me@example.com' ],
			'update_payout'      => [ 'payout_id' => 9, 'status' => 'approved' ],
			'approve_payout'     => [ 'payout_id' => 9 ],
			'reject_payout'      => [ 'payout_id' => 9 ],
			'get_payout'         => [ 'payout_id' => 9 ],
			'get_profile'        => [ 'user_id' => 2 ],
			'get_leaderboard'    => [ 'limit' => 5 ],
			'get_leaderboard_position' => [ 'user_id' => 2 ],
			'trigger_event'      => [ 'trigger_key' => 'user_register', 'user_id' => 2 ],
			'get_activity_logs'  => [ 'user_id' => 2, 'limit' => 5 ],
		];
	}

	/* ------------------------------------------------------------------
	 * Triggers: payload shape and context gates
	 * ------------------------------------------------------------------ */

	public function test_points_payload_reads_log_row_and_user(): void {
		global $wpdb;
		$wpdb->setTable( 'row', [
			'points'      => 50,
			'description' => 'Unlocked: First Steps',
			'created_at'  => '2026-01-01 00:00:00',
		] );

		$result = Gameengine::resolve_trigger(
			$this->makeTriggerNode( 'points_awarded' ),
			[ 2, 50, 'achievement_unlock', 15, 1 ]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 50, $result['points'] );
		$this->assertSame( 'increase', $result['direction'] );
		$this->assertSame( 'achievement_unlock', $result['context'] );
		$this->assertSame( 'Unlocked: First Steps', $result['description'] );
		$this->assertSame( '2026-01-01 00:00:00', $result['created_at'] );
		$this->assertSame( 'points', $result['point_type'] );
		$this->assertSame( 500, $result['balance'] );
		$this->assertSame( 15, $result['log_id'] );
		$this->assertSame( 'johndoe', $result['user']['user_login'] );
		$this->assertSame( 'john@example.com', $result['user']['user_email'] );
	}

	public function test_deducted_direction_follows_negative_log_entry(): void {
		global $wpdb;
		$wpdb->setTable( 'row', [
			'points'      => -25,
			'description' => 'Redeemed reward: Sticker Pack',
			'created_at'  => '2026-01-01 00:00:00',
		] );

		$result = Gameengine::resolve_trigger(
			$this->makeTriggerNode( 'points_deducted' ),
			[ 2, 25, 'reward_redeem', 16, 1 ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'decrease', $result['direction'] );
		$this->assertSame( 'Redeemed reward: Sticker Pack', $result['description'] );
	}

	public function test_point_type_and_minimum_filters(): void {
		$node = $this->makeTriggerNode( 'points_awarded', [ 'point_type' => '2', 'min_points' => 60 ] );

		// Right amount, wrong point type.
		$this->assertFalse( Gameengine::resolve_trigger( $node, [ 2, 100, 'manual_adjustment', 0, 1 ] ) );
		// Right type, too few points.
		$this->assertFalse( Gameengine::resolve_trigger( $node, [ 2, 30, 'manual_adjustment', 0, 2 ] ) );
		// Both match.
		$result = Gameengine::resolve_trigger( $node, [ 2, 80, 'manual_adjustment', 0, 2 ] );
		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['point_type_id'] );
		$this->assertSame( 'coins', $result['point_type'] );

		// The same filters now guard the balance-changed trigger too.
		$balance = $this->makeTriggerNode( 'points_balance_changed', [ 'min_points' => 50 ] );
		$this->assertFalse( Gameengine::resolve_trigger( $balance, [ 2, 40, 'daily_login', 0, 1 ] ) );
		$this->assertIsArray( Gameengine::resolve_trigger( $balance, [ 2, 60, 'daily_login', 0, 1 ] ) );
	}

	public function test_checkout_trigger_only_accepts_payment_contexts(): void {
		$node = $this->makeTriggerNode( 'points_used_at_checkout' );

		foreach ( [ 'wc_points_payment', 'wc_partial_payment', 'se_points_payment', 'se_partial_payment' ] as $context ) {
			$result = Gameengine::resolve_trigger( $node, [ 2, 100, $context, 18, 1 ] );
			$this->assertIsArray( $result, "Context '{$context}' should fire the checkout trigger" );
			$this->assertSame( $context, $result['context'] );
		}

		$this->assertFalse( Gameengine::resolve_trigger( $node, [ 2, 100, 'manual_adjustment', 18, 1 ] ) );
	}

	public function test_transfer_trigger_gates_on_context_and_direction(): void {
		global $wpdb;
		$wpdb->setTable( 'row', [
			'points'      => -30,
			'description' => 'Transferred 30 points to user #7',
			'created_at'  => '2026-01-01 00:00:00',
		] );

		$any = $this->makeTriggerNode( 'points_transferred' );
		$sent = Gameengine::resolve_trigger( $any, [ 2, 30, 'transfer_sent', 19, 1 ] );
		$this->assertIsArray( $sent );
		$this->assertSame( 'sent', $sent['direction'] );
		$this->assertSame( 7, $sent['other_user_id'] );

		$received = Gameengine::resolve_trigger( $any, [ 2, 30, 'transfer_received', 20, 1 ] );
		$this->assertIsArray( $received );
		$this->assertSame( 'received', $received['direction'] );

		// A configured direction rejects the other leg.
		$sent_only = $this->makeTriggerNode( 'points_transferred', [ 'direction' => 'sent' ] );
		$this->assertFalse( Gameengine::resolve_trigger( $sent_only, [ 2, 30, 'transfer_received', 20, 1 ] ) );

		// Non-transfer contexts never fire it.
		$this->assertFalse( Gameengine::resolve_trigger( $any, [ 2, 30, 'manual_adjustment', 0, 1 ] ) );
	}

	public function test_expiry_payout_and_affiliate_context_gates(): void {
		$expired = $this->makeTriggerNode( 'points_expired' );
		$this->assertIsArray( Gameengine::resolve_trigger( $expired, [ 2, 75, 'expired', 20, 1 ] ) );
		$this->assertFalse( Gameengine::resolve_trigger( $expired, [ 2, 75, 'reward_redeem', 0, 1 ] ) );

		global $wpdb;
		$wpdb->setTable( 'row', [
			'points'      => -1000,
			'description' => 'Payout request for 10.00 $ via paypal.',
			'created_at'  => '2026-01-01 00:00:00',
		] );

		$payout = $this->makeTriggerNode( 'payout_requested' );
		$result = Gameengine::resolve_trigger( $payout, [ 2, 1000, 'payout_request', 21, 1 ] );
		$this->assertIsArray( $result );
		$this->assertSame( 'paypal', $result['method'] );
		$this->assertSame( 'decrease', $result['direction'] );

		$min = $this->makeTriggerNode( 'payout_requested', [ 'min_points' => 2000 ] );
		$this->assertFalse( Gameengine::resolve_trigger( $min, [ 2, 1000, 'payout_request', 21, 1 ] ) );
		$this->assertFalse( Gameengine::resolve_trigger( $payout, [ 2, 1000, 'manual_adjustment', 0, 1 ] ) );

		$rejected = $this->makeTriggerNode( 'payout_rejected' );
		$this->assertIsArray( Gameengine::resolve_trigger( $rejected, [ 2, 1000, 'payout_refund', 22, 1 ] ) );
		$this->assertFalse( Gameengine::resolve_trigger( $rejected, [ 2, 1000, 'payout_request', 0, 1 ] ) );

		$affiliate = $this->makeTriggerNode( 'affiliate_reward' );
		$this->assertIsArray( Gameengine::resolve_trigger( $affiliate, [ 2, 50, 'referral_signup', 23, 1 ] ) );
		$this->assertFalse( Gameengine::resolve_trigger( $affiliate, [ 2, 50, 'achievement_unlock', 0, 1 ] ) );
	}

	public function test_specific_achievement_and_level_config_gates(): void {
		$achievement = $this->makeTriggerNode( 'specific_achievement', [ 'achievement_id' => 3 ] );
		$result      = Gameengine::resolve_trigger( $achievement, [ 2, 3, 7 ] );
		$this->assertIsArray( $result );
		$this->assertSame( 3, $result['achievement_id'] );
		$this->assertSame( 7, $result['user_achievement_id'] );
		$this->assertFalse( Gameengine::resolve_trigger( $achievement, [ 2, 7, 9 ] ) );

		$level = $this->makeTriggerNode( 'specific_level', [ 'level_id' => 2 ] );
		$result = Gameengine::resolve_trigger( $level, [ 2, 2, 8 ] );
		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['level_id'] );
		$this->assertSame( 8, $result['user_level_id'] );
		$this->assertFalse( Gameengine::resolve_trigger( $level, [ 2, 1, 9 ] ) );

		// The catch-all variants accept any id.
		$this->assertIsArray( Gameengine::resolve_trigger( $this->makeTriggerNode( 'achievement_earned' ), [ 2, 7, 9 ] ) );
		$this->assertIsArray( Gameengine::resolve_trigger( $this->makeTriggerNode( 'level_reached' ), [ 2, 1, 9 ] ) );
	}

	public function test_level_reached_counts_position_in_ladder(): void {
		global $wpdb;
		$wpdb->setTable( 'results', [
			[ 'id' => 1 ],
			[ 'id' => 2 ],
			[ 'id' => 3 ],
		] );

		$result = Gameengine::resolve_trigger(
			$this->makeTriggerNode( 'level_reached' ),
			[ 2, 2, 8 ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['level_number'] );
	}

	public function test_reward_redeemed_config_gate(): void {
		$node = $this->makeTriggerNode( 'reward_redeemed', [ 'reward_id' => 4 ] );

		$result = Gameengine::resolve_trigger( $node, [ 2, 4, 500 ] );
		$this->assertIsArray( $result );
		$this->assertSame( 500, $result['points_spent'] );
		$this->assertSame( 500, $result['balance'] );
		$this->assertFalse( Gameengine::resolve_trigger( $node, [ 2, 9, 500 ] ) );

		// Empty config accepts every reward.
		$this->assertIsArray( Gameengine::resolve_trigger( $this->makeTriggerNode( 'reward_redeemed' ), [ 2, 9, 100 ] ) );
	}

	public function test_coupon_generated_gates_on_post_type_and_prefix(): void {
		$node = $this->makeTriggerNode( 'coupon_generated', [ 'prefix' => 'GE-' ] );

		$result = Gameengine::resolve_trigger( $node, [ 50, null, false ] );
		$this->assertIsArray( $result );
		$this->assertSame( 'GE-TEST123', $result['code'] );
		$this->assertSame( 'percent', $result['discount_type'] );
		$this->assertSame( '10', $result['discount_amount'] );
		$this->assertSame( 2, $result['user_id'] );

		// Wrong prefix.
		$this->assertFalse(
			Gameengine::resolve_trigger( $this->makeTriggerNode( 'coupon_generated', [ 'prefix' => 'SAVE-' ] ), [ 50, null, false ] )
		);

		// A regular post never fires it.
		$this->assertFalse( Gameengine::resolve_trigger( $node, [ 1, null, false ] ) );
	}

	public function test_rank_changed_and_referral_payloads(): void {
		$rank = Gameengine::resolve_trigger( $this->makeTriggerNode( 'rank_changed' ), [ 2, 'Bronze', 'Silver' ] );
		$this->assertIsArray( $rank );
		$this->assertSame( 'Bronze', $rank['old_rank'] );
		$this->assertSame( 'Silver', $rank['new_rank'] );
		$this->assertSame( 'Silver', $rank['rank'] );
		$this->assertFalse( Gameengine::resolve_trigger( $this->makeTriggerNode( 'rank_changed' ), [ 2, 'Bronze', '' ] ) );

		$referral = Gameengine::resolve_trigger( $this->makeTriggerNode( 'referral_completed' ), [ 1, 2 ] );
		$this->assertIsArray( $referral );
		$this->assertSame( 1, $referral['referrer']['user_id'] );
		$this->assertSame( 2, $referral['referee']['user_id'] );
		// Missing referee: the payload needs two real members.
		$this->assertFalse( Gameengine::resolve_trigger( $this->makeTriggerNode( 'referral_completed' ), [ 1, 0 ] ) );
	}

	public function test_sample_outputs_cover_every_trigger_and_action(): void {
		foreach ( array_keys( Gameengine::get_triggers() ) as $event ) {
			$sample = Gameengine::get_trigger_sample_output( $event );
			$this->assertNotEmpty( $sample, "Trigger '{$event}' is missing a sample output" );
			$this->assertArrayHasKey( 'success', $sample, "Trigger '{$event}' sample is missing 'success'" );
		}

		foreach ( array_keys( Gameengine::get_actions() ) as $event ) {
			$sample = Gameengine::get_action_sample_output( $event );
			$this->assertNotEmpty( $sample, "Action '{$event}' is missing a sample output" );
			$this->assertArrayHasKey( 'success', $sample, "Action '{$event}' sample is missing 'success'" );
		}
	}

	/* ------------------------------------------------------------------
	 * Actions: real execution against the manager doubles
	 * ------------------------------------------------------------------ */

	public function test_every_action_reports_a_success_flag(): void {
		// Catches a registered action whose execute_node() dispatch has no
		// action_* method: that path would echo the input instead.
		foreach ( $this->getActionTests() as $event => $config ) {
			$result = Gameengine::execute_node( $this->makeActionNode( $event, $config ), [] );

			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'success', $result['data'], "Action '{$event}' did not report a success flag" );
		}
	}

	public function test_award_and_deduct_points_move_the_balance(): void {
		$awarded = Gameengine::execute_node( $this->makeActionNode( 'award_points', [
			'user_id' => 2,
			'points'  => 50,
			'context' => 'manual_adjustment',
			'reason'  => 'Bonus',
		] ), [] );

		$this->assertSame( 'main', $awarded['port'] );
		$this->assertTrue( $awarded['data']['success'] );
		$this->assertSame( 550, $awarded['data']['balance'] );
		$this->assertSame( 50, $awarded['data']['points'] );
		$this->assertSame( 1, $awarded['data']['point_type_id'] );

		$deducted = Gameengine::execute_node( $this->makeActionNode( 'deduct_points', [
			'user_id' => 2,
			'points'  => 20,
		] ), [] );

		$this->assertTrue( $deducted['data']['success'] );
		$this->assertSame( 530, $deducted['data']['balance'] );

		$logs = \GameEngine\Classes\PointsManager::get_logs();
		$last = end( $logs );
		$this->assertSame( -20, $last['points'] );
		$this->assertSame( 'manual_adjustment', $last['context'] );
	}

	public function test_set_balance_adjusts_to_an_exact_value(): void {
		$result = Gameengine::execute_node( $this->makeActionNode( 'set_balance', [
			'user_id' => 2,
			'points'  => 120,
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertSame( 500, $result['data']['previous'] );
		$this->assertSame( -380, $result['data']['adjusted'] );
		$this->assertSame( 120, $result['data']['balance'] );
	}

	public function test_lock_and_unlock_content_write_restrictions(): void {
		$locked = Gameengine::execute_node( $this->makeActionNode( 'lock_content', [
			'post_id'        => 2,
			'restrict_type'  => 'points',
			'restrict_value' => '100',
			'message'        => 'Members only.',
		] ), [] );

		$this->assertTrue( $locked['data']['success'] );
		$this->assertSame( 'points', $locked['data']['restrict_type'] );
		// The shared meta store, since the priority get_post_meta double
		// serves from its own fixture.
		$this->assertSame( 'points', WPMocks::getPostMeta( 2, '_gameengine_restrict_type', true ) );
		$this->assertSame( '100', WPMocks::getPostMeta( 2, '_gameengine_restrict_value', true ) );
		$this->assertSame( 'Members only.', WPMocks::getPostMeta( 2, '_gameengine_restrict_message', true ) );

		$unlocked = Gameengine::execute_node( $this->makeActionNode( 'unlock_content', [ 'post_id' => 2 ] ), [] );

		$this->assertTrue( $unlocked['data']['success'] );
		$this->assertSame( 'none', WPMocks::getPostMeta( 2, '_gameengine_restrict_type', true ) );
	}

	public function test_achievement_award_list_and_revoke(): void {
		$awarded = Gameengine::execute_node( $this->makeActionNode( 'award_achievement', [
			'user_id'        => 2,
			'achievement_id' => 3,
		] ), [] );

		$this->assertTrue( $awarded['data']['success'] );
		$this->assertFalse( $awarded['data']['already_held'] );

		// Awarding it again is reported as already held, not an error.
		$again = Gameengine::execute_node( $this->makeActionNode( 'award_achievement', [
			'user_id'        => 2,
			'achievement_id' => 3,
		] ), [] );
		$this->assertTrue( $again['data']['success'] );
		$this->assertTrue( $again['data']['already_held'] );

		$list = Gameengine::execute_node( $this->makeActionNode( 'get_achievements', [ 'user_id' => 2 ] ), [] );
		$this->assertTrue( $list['data']['success'] );
		$this->assertSame( 3, $list['data']['achievements'][0]['achievement_id'] );
		$this->assertSame( 'First Steps', $list['data']['achievements'][0]['title'] );

		$revoked = Gameengine::execute_node( $this->makeActionNode( 'revoke_achievement', [
			'user_id'        => 2,
			'achievement_id' => 3,
		] ), [] );
		$this->assertTrue( $revoked['data']['success'] );

		$missing = Gameengine::execute_node( $this->makeActionNode( 'revoke_achievement', [
			'user_id'        => 2,
			'achievement_id' => 3,
		] ), [] );
		$this->assertFalse( $missing['data']['success'] );
		$this->assertSame( 'main', $missing['port'] );
	}

	public function test_level_assignment_get_and_rank_aliases(): void {
		$assigned = Gameengine::execute_node( $this->makeActionNode( 'assign_level', [
			'user_id'  => 2,
			'level_id' => 2,
		] ), [] );

		$this->assertTrue( $assigned['data']['success'] );
		$this->assertSame( 2, $assigned['data']['level_id'] );
		$this->assertSame( 2, $assigned['data']['rank_id'] );
		$this->assertFalse( $assigned['data']['already_held'] );

		$again = Gameengine::execute_node( $this->makeActionNode( 'change_level', [
			'user_id'  => 2,
			'level_id' => 2,
		] ), [] );
		$this->assertTrue( $again['data']['success'] );
		$this->assertTrue( $again['data']['already_held'] );

		$current = Gameengine::execute_node( $this->makeActionNode( 'get_level', [ 'user_id' => 2 ] ), [] );
		$this->assertSame( 2, $current['data']['level_id'] );
		$this->assertSame( 'Apprentice', $current['data']['level'] );

		$rank = Gameengine::execute_node( $this->makeActionNode( 'assign_rank', [
			'user_id' => 2,
			'rank_id' => 1,
		] ), [] );
		$this->assertTrue( $rank['data']['success'] );
		$this->assertSame( 1, $rank['data']['rank_id'] );

		$rank_read = Gameengine::execute_node( $this->makeActionNode( 'get_rank', [ 'user_id' => 2 ] ), [] );
		$this->assertSame( 2, $rank_read['data']['rank_id'] );
		$this->assertSame( 'Apprentice', $rank_read['data']['rank'] );
	}

	public function test_redeem_reward_spends_points(): void {
		$result = Gameengine::execute_node( $this->makeActionNode( 'redeem_reward', [
			'user_id'   => 2,
			'reward_id' => 4,
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertSame( 0, $result['data']['remaining_points'] );
		$this->assertSame( 9, $result['data']['remaining_stock'] );

		// A user who cannot afford it gets a soft failure on the main port.
		$result = Gameengine::execute_node( $this->makeActionNode( 'redeem_reward', [
			'user_id'   => 999,
			'reward_id' => 4,
		] ), [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertFalse( $result['data']['success'] );
	}

	public function test_get_transactions_maps_log_rows(): void {
		global $wpdb;
		$wpdb->setTable( 'results', [
			[
				'id'            => 9,
				'points'        => 50,
				'point_type_id' => 1,
				'context'       => 'achievement_unlock',
				'description'   => 'Unlocked: First Steps',
				'created_at'    => '2026-01-01 00:00:00',
			],
		] );

		$result = Gameengine::execute_node( $this->makeActionNode( 'get_transactions', [
			'user_id' => 2,
			'limit'   => 5,
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertCount( 1, $result['data']['transactions'] );
		$this->assertSame( 9, $result['data']['transactions'][0]['log_id'] );
		$this->assertSame( 50, $result['data']['transactions'][0]['points'] );
		$this->assertSame( 'achievement_unlock', $result['data']['transactions'][0]['context'] );
	}

	public function test_validation_failures_stay_on_the_main_port(): void {
		$no_points = Gameengine::execute_node( $this->makeActionNode( 'award_points', [
			'user_id' => 2,
			'points'  => 0,
		] ), [] );

		$this->assertSame( 'main', $no_points['port'] );
		$this->assertFalse( $no_points['data']['success'] );
		$this->assertNotEmpty( $no_points['data']['error'] );

		$no_post = Gameengine::execute_node( $this->makeActionNode( 'unlock_content', [ 'post_id' => 999 ] ), [] );
		$this->assertSame( 'main', $no_post['port'] );
		$this->assertFalse( $no_post['data']['success'] );
	}

	/* ------------------------------------------------------------------
	 * Pro capability gate
	 * ------------------------------------------------------------------ */

	public function test_pro_capabilities_are_marked_disabled_without_pro(): void {
		$triggers = Gameengine::get_triggers();

		$this->assertTrue( ! empty( $triggers['payout_requested']['disabled'] ) );
		$this->assertSame( 'GameEngine Pro', $triggers['payout_requested']['requires_addon'] );
		$this->assertNotEmpty( $triggers['points_transferred']['disabled_reason'] );
		$this->assertArrayNotHasKey( 'disabled', $triggers['points_awarded'] );

		$actions = Gameengine::get_actions();
		$this->assertTrue( ! empty( $actions['transfer_points']['disabled'] ) );
		$this->assertArrayNotHasKey( 'disabled', $actions['award_points'] );

		// And the gate holds at execution time.
		$result = Gameengine::execute_node( $this->makeActionNode( 'transfer_points', [
			'sender_id'   => 1,
			'receiver_id' => 2,
			'points'      => 10,
		] ), [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertFalse( $result['data']['success'] );
		$this->assertStringContainsString( 'Pro', $result['data']['error'] );
	}

	public function test_transfers_move_points_between_members_when_pro_is_active(): void {
		$this->activate_pro();

		$result = Gameengine::execute_node( $this->makeActionNode( 'transfer_points', [
			'sender_id'   => 1,
			'receiver_id' => 2,
			'points'      => 30,
			'message'     => 'Thanks!',
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertSame( 970, $result['data']['sender_balance'] );
		$this->assertSame( 530, $result['data']['receiver_balance'] );
		$this->assertGreaterThan( 0, $result['data']['transfer_row'] );

		// Too few points is a soft failure with the legs untouched.
		$short = Gameengine::execute_node( $this->makeActionNode( 'transfer_points', [
			'sender_id'   => 1,
			'receiver_id' => 2,
			'points'      => 999999,
		] ), [] );
		$this->assertFalse( $short['data']['success'] );
		$this->assertSame( 970, ( new \GameEngine\Classes\PointsManager() )->get_grand_total( 1 ) );
	}

	public function test_payout_lifecycle_runs_when_pro_is_active(): void {
		global $wpdb;
		$this->activate_pro();

		$created = Gameengine::execute_node( $this->makeActionNode( 'create_payout', [
			'user_id' => 2,
			'points'  => 100,
			'method'  => 'paypal',
			'account' => 'me@example.com',
		] ), [] );

		$this->assertTrue( $created['data']['success'] );
		$this->assertSame( 'pending', $created['data']['status'] );
		$this->assertSame( 400, ( new \GameEngine\Classes\PointsManager() )->get_grand_total( 2 ) );

		// Approval moves a pending request along.
		$wpdb->setTable( 'row', [ 'id' => 9, 'user_id' => 2, 'points' => 100, 'amount' => 10, 'status' => 'pending' ] );
		$approved = Gameengine::execute_node( $this->makeActionNode( 'approve_payout', [ 'payout_id' => 9 ] ), [] );
		$this->assertTrue( $approved['data']['success'] );
		$this->assertSame( 'pending', $approved['data']['previous_status'] );
		$this->assertSame( 'approved', $approved['data']['status'] );

		// Rejection refunds the deducted points through Pro_Helper.
		$wpdb->setTable( 'row', [ 'id' => 9, 'user_id' => 2, 'points' => 100, 'amount' => 10, 'status' => 'approved' ] );
		$rejected = Gameengine::execute_node( $this->makeActionNode( 'update_payout', [
			'payout_id' => 9,
			'status'    => 'rejected',
		] ), [] );

		$this->assertTrue( $rejected['data']['success'] );
		$this->assertTrue( $rejected['data']['refunded'] );
		$this->assertSame( 'rejected', $rejected['data']['status'] );
		$this->assertSame( 500, ( new \GameEngine\Classes\PointsManager() )->get_grand_total( 2 ) );
		$this->assertCount( 1, \Zaplane_Ge_ProHelper_Stub::$refunds );
	}

	public function test_generate_coupon_runs_when_pro_is_active(): void {
		$this->activate_pro();

		$result = Gameengine::execute_node( $this->makeActionNode( 'generate_coupon', [
			'user_id'       => 2,
			'amount'        => 10,
			'discount_type' => 'percent',
			'points_cost'   => 50,
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertStringStartsWith( 'GE-', $result['data']['code'] );
		$this->assertSame( 450, $result['data']['balance'] );
	}

	public function test_reject_payout_refunds_and_get_payout_reads_the_row(): void {
		global $wpdb;
		$this->activate_pro();

		$wpdb->setTable( 'row', [
			'id'         => 7,
			'user_id'    => 2,
			'points'     => 80,
			'amount'     => 8,
			'method'     => 'bank',
			'status'     => 'approved',
			'notes'      => 'Hold on to this one',
			'created_at' => '2026-01-01 09:00:00',
		] );

		$read = Gameengine::execute_node( $this->makeActionNode( 'get_payout', [ 'payout_id' => 7 ] ), [] );

		$this->assertTrue( $read['data']['success'] );
		$this->assertSame( 7, $read['data']['payout_id'] );
		$this->assertSame( 2, $read['data']['user_id'] );
		$this->assertSame( 'approved', $read['data']['status'] );
		$this->assertSame( 80, $read['data']['points'] );
		$this->assertSame( 'bank', $read['data']['method'] );

		$rejected = Gameengine::execute_node( $this->makeActionNode( 'reject_payout', [ 'payout_id' => 7 ] ), [] );

		$this->assertTrue( $rejected['data']['success'] );
		$this->assertSame( 'approved', $rejected['data']['previous_status'] );
		$this->assertSame( 'rejected', $rejected['data']['status'] );
		$this->assertTrue( $rejected['data']['refunded'] );
		$this->assertSame( 580, ( new \GameEngine\Classes\PointsManager() )->get_grand_total( 2 ) );
		$this->assertCount( 1, \Zaplane_Ge_ProHelper_Stub::$refunds );
	}

	public function test_profile_leaderboard_and_activity_log_reads(): void {
		( new \GameEngine\Classes\LevelsManager() )->award( 2, 2, 'zaplane' );
		( new \GameEngine\Classes\AchievementsManager() )->award( 2, 3, 'zaplane' );

		$profile = Gameengine::execute_node( $this->makeActionNode( 'get_profile', [ 'user_id' => 2 ] ), [] );

		$this->assertTrue( $profile['data']['success'] );
		$this->assertSame( 2, $profile['data']['user']['user_id'] );
		$this->assertSame( 500, $profile['data']['balance'] );
		$this->assertSame( 2, $profile['data']['level_id'] );
		$this->assertSame( 'Apprentice', $profile['data']['level'] );
		$this->assertSame( 'Apprentice', $profile['data']['rank'] );
		$this->assertSame( 1, $profile['data']['achievement_count'] );
		$this->assertSame( 1, $profile['data']['point_type_balances'][0]['point_type_id'] );
		$this->assertSame( 500, $profile['data']['point_type_balances'][0]['balance'] );

		\GameEngine\Classes\LeaderboardManager::$rows = [ [
			'position'           => 1,
			'user_id'            => 2,
			'name'               => 'testuser',
			'total_points'       => 500,
			'achievements_count' => 1,
			'top_level'          => 'Apprentice',
		] ];

		$board = Gameengine::execute_node( $this->makeActionNode( 'get_leaderboard', [ 'limit' => 5 ] ), [] );

		$this->assertTrue( $board['data']['success'] );
		$this->assertSame( 1, $board['data']['count'] );
		$this->assertSame( 1, $board['data']['entries'][0]['position'] );
		$this->assertSame( 500, $board['data']['entries'][0]['total_points'] );

		\GameEngine\Classes\LeaderboardManager::$position = [
			'position'     => 4,
			'total_points' => 500,
		];

		$place = Gameengine::execute_node( $this->makeActionNode( 'get_leaderboard_position', [ 'user_id' => 2 ] ), [] );

		$this->assertTrue( $place['data']['success'] );
		$this->assertTrue( $place['data']['placed'] );
		$this->assertSame( 4, $place['data']['position'] );

		// A member with no positive standing simply does not place — that is a
		// result the workflow can branch on, not a failure.
		\GameEngine\Classes\LeaderboardManager::$position = null;
		$absent = Gameengine::execute_node( $this->makeActionNode( 'get_leaderboard_position', [ 'user_id' => 2 ] ), [] );
		$this->assertTrue( $absent['data']['success'] );
		$this->assertFalse( $absent['data']['placed'] );

		global $wpdb;
		$wpdb->setTable( 'results', [ [
			'id'             => 512,
			'user_id'        => 2,
			'trigger_key'    => 'user_register',
			'status'         => 'success',
			'points_awarded' => 50,
			'message'        => 'Awarded 50 points.',
			'created_at'     => '2026-01-01 09:00:00',
		] ] );

		$logs = Gameengine::execute_node( $this->makeActionNode( 'get_activity_logs', [ 'limit' => 5 ] ), [] );

		$this->assertTrue( $logs['data']['success'] );
		$this->assertSame( 1, $logs['data']['count'] );
		$this->assertSame( 'user_register', $logs['data']['logs'][0]['trigger_key'] );
		$this->assertSame( 50, $logs['data']['logs'][0]['points_awarded'] );
		$this->assertSame( 'success', $logs['data']['logs'][0]['status'] );
	}

	public function test_trigger_event_runs_the_registered_event_for_the_member(): void {
		$result = Gameengine::execute_node( $this->makeActionNode( 'trigger_event', [
			'trigger_key' => 'user_register',
			'user_id'     => 2,
		] ), [] );

		$this->assertTrue( $result['data']['success'] );
		$this->assertSame( 'user_register', $result['data']['trigger_key'] );
		$this->assertSame( 'User Registration', $result['data']['label'] );
		$this->assertSame( 500, $result['data']['balance_before'] );
		$this->assertSame( 500, $result['data']['balance_after'] );
		$this->assertSame( 0, $result['data']['balance_delta'] );

		$runs = \GameEngine\Classes\Triggers::$runs;
		$this->assertCount( 1, $runs );
		$this->assertSame( 'user_register', $runs[0]['trigger_key'] );
		$this->assertSame( 2, $runs[0]['user_id'] );
		$this->assertSame( [ 2 ], $runs[0]['hook_args'] );

		// An event GameEngine does not know cannot be run.
		$unknown = Gameengine::execute_node( $this->makeActionNode( 'trigger_event', [
			'trigger_key' => 'not_an_event',
			'user_id'     => 2,
		] ), [] );

		$this->assertFalse( $unknown['data']['success'] );
		$this->assertCount( 1, \GameEngine\Classes\Triggers::$runs );
	}

	/* ------------------------------------------------------------------
	 * Manifest plumbing: dynamic queries behind the schema selects
	 * ------------------------------------------------------------------ */

	public function test_dynamic_query_references_resolve(): void {
		$queries = Gameengine::get_dynamic_queries();
		$schemas = [];

		foreach ( array_keys( Gameengine::get_triggers() ) as $event ) {
			$schemas[] = Gameengine::get_trigger_config_schema( $event );
		}
		foreach ( array_keys( Gameengine::get_actions() ) as $event ) {
			$schemas[] = Gameengine::get_action_config_schema( $event );
		}

		foreach ( $schemas as $schema ) {
			foreach ( $schema as $field ) {
				if ( empty( $field['dynamic']['query'] ) ) {
					continue;
				}
				$this->assertArrayHasKey(
					$field['dynamic']['query'],
					$queries,
					"Schema field '{$field['key']}' references an unknown query"
				);
				$this->assertSame( 'gameengine', $field['dynamic']['integration'] );
			}
		}

		// Every registered query answers with value/label pairs.
		$this->assertSame(
			[ [ 'value' => 1, 'label' => 'Points' ], [ 'value' => 2, 'label' => 'Coins' ] ],
			Gameengine::query_point_types()
		);

		// A type with no plural_name still has to read as something — the
		// label falls back to its name, the way GameEngine's own
		// get_point_type_label() does, instead of rendering blank.
		$saved_types = \GameEngine\Classes\PointsManager::$point_types;
		\GameEngine\Classes\PointsManager::$point_types = [ [
			'id'          => 7,
			'name'        => 'Bits',
			'plural_name' => '',
			'slug'        => 'bits',
		] ];
		$this->assertSame( [ [ 'value' => 7, 'label' => 'Bits' ] ], Gameengine::query_point_types() );
		\GameEngine\Classes\PointsManager::$point_types = $saved_types;

		global $wpdb;
		$wpdb->setTable( 'results', [ [ 'id' => 3, 'title' => 'First Steps' ] ] );
		$this->assertSame( [ [ 'value' => 3, 'label' => 'First Steps' ] ], Gameengine::query_achievements() );

		$wpdb->setTable( 'results', [ [ 'id' => 2, 'title' => 'Apprentice' ] ] );
		$this->assertSame( [ [ 'value' => 2, 'label' => 'Apprentice' ] ], Gameengine::query_levels() );

		$wpdb->setTable( 'results', [ [ 'id' => 4, 'title' => 'Sticker Pack', 'cost_points' => 500 ] ] );
		$this->assertSame( 'Sticker Pack (500)', Gameengine::query_rewards()[0]['label'] );

		$wpdb->setTable( 'results', [ [ 'id' => 5, 'user_id' => 2, 'points' => 1000, 'status' => 'pending' ] ] );
		$this->assertStringContainsString( 'pending', Gameengine::query_payouts()[0]['label'] );

		$GLOBALS['zaplane_get_posts'] = [ (object) [ 'ID' => 7, 'post_title' => 'Secret Page' ] ];
		$this->assertSame( [ [ 'value' => 7, 'label' => 'Secret Page' ] ], Gameengine::query_posts() );
		unset( $GLOBALS['zaplane_get_posts'] );

		// The trigger_event select lists GameEngine's own registry keys.
		$this->assertSame(
			[ [ 'value' => 'user_register', 'label' => 'User Registration' ] ],
			Gameengine::query_events()
		);
	}

	/** Open the Pro gate for this (isolated) test process. */
	private function activate_pro(): void {
		if ( ! defined( 'GAMEENGINE_PRO_VERSION' ) ) {
			define( 'GAMEENGINE_PRO_VERSION', '1.4.2' );
		}
		if ( ! class_exists( 'GameEngine\Pro\Pro_Helper' ) ) {
			class_alias( 'Zaplane_Ge_ProHelper_Stub', 'GameEngine\Pro\Pro_Helper' );
		}
	}
}
