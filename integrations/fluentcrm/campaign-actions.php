<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait CampaignActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    // protected static function action_create_comment(array $config): array
    // {
    //     $comment_id = wp_insert_comment([
    //         'comment_post_ID'      => $config['post_id'],
    //         'comment_author'       => $config['author_name'],
    //         'comment_author_email' => $config['author_email'],
    //         'comment_content'      => $config['content'],
    //         'comment_approved'     => 1,
    //     ]);

    //     if (is_wp_error($comment_id)) {
    //         return static::error($comment_id->get_error_message());
    //     }

    //     return static::success(['comment_id' => $comment_id]);
    // }
}