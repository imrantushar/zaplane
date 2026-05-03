<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gamipress;

class GamipressTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Gamipress::class;
    }
    
    protected function makeWpPost(array $data)
    {
        global $zaplane_wp_posts;

        $zaplane_wp_posts = $zaplane_wp_posts ?? [];

        $id = $data['ID'] ?? rand(1000, 9999);

        $post = (object) array_merge([
            'ID' => $id,
        ], $data);

        $zaplane_wp_posts[$id] = $post;

        return $post;
    }

    public function test_user_earns_rank(): void
    {
        $node = $this->makeTriggerNode('user_earns_rank', [
            'rank_type' => 'course_rank',
            'rank_id'   => 'gold',
        ]);

        $rank = new \WP_Post([
            'ID'        => 10,
            'post_type' => 'course_rank',
            'post_name' => 'gold',
        ]);

        $result = Gamipress::resolve_trigger(
            $node,
            [1, $rank]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('gold', $result['rank']);
    }

    public function test_user_earns_points(): void
    {
        $node = $this->makeTriggerNode('user_earns_points', [
            'achievement_type' => 'quiz',
        ]);

        $result = Gamipress::resolve_trigger(
            $node,
            [1, 50, 150, 2, 99, 'quiz']
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(50, $result['new_points']);
        $this->assertEquals('quiz', $result['points_type']);
    }

    public function test_user_earns_specific_achievement_type(): void
    {
        $node = $this->makeTriggerNode('user_earns_specific_achievement_type', [
            'achievement_type' => 'badge',
        ]);

        $this->makeWpPost([
            'ID'        => 25,
            'post_type' => 'badge',
            'post_name' => 'first-login',
        ]);

        $result = Gamipress::resolve_trigger(
            $node,
            [1, 25]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('badge', $result['achievement_type']);
    }

    public function test_user_gains_achievement(): void
    {
        $node = $this->makeTriggerNode('user_gains_achievement', [
            'achievement_type' => 'badge',
            'achievement_id'   => '25',
        ]);

        $this->makeWpPost([
            'ID'        => 25,
            'post_type' => 'badge',
            'post_name' => 'first-login',
        ]);

        $result = Gamipress::resolve_trigger(
            $node,
            [1, 25]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(25, $result['achievement_id']);
    }

    public function test_user_achievement_revoked(): void
    {
        $node = $this->makeTriggerNode('user_achievement_revoked');

        $this->makeWpPost([
            'ID'          => 100,
            'post_title'  => 'Gold Badge',
            'post_type'   => 'badge',
            'post_parent' => 0,
            'post_author' => 1,
            'post_content'=> 'Test badge content',
        ]);

        $this->makeWpPost([
            'ID'          => 101,
            'post_title'  => 'Gold Badge Child',
            'post_type'   => 'badge_award',
            'post_parent' => 100,
            'post_author' => 1,
            'post_content'=> 'Child content',
        ]);

        $result = Gamipress::resolve_trigger(
            $node,
            [1, 101]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(101, $result['post_id']);
    }
}