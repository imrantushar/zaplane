<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ReviewActionsTrait {
    private static function action_get_reviews_all(array $config, array $input): array {
        $pagination = self::get_pagination_args($config);
        $result = self::query_reviews([
            'status' => 'approve',
            'post_type' => 'product',
            'number' => $pagination['limit'],
            'paged' => $pagination['page'],
        ]);

        $items = array_map(function($comment) {
            return [
                'review_id' => $comment->comment_ID,
                'product_id' => $comment->comment_post_ID,
                'author' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'rating' => (int) get_comment_meta($comment->comment_ID, 'rating', true),
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
            ];
        }, $result['items']);

        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_top_selling_products_report(array $config, array $input): array {
        $limit = isset($config['limit']) ? (int) $config['limit'] : 10;
        if ($limit <= 0) {
            $limit = 10;
        }
        $result = self::query_products([
            'limit' => $limit,
            'orderby' => 'total_sales',
            'order' => 'DESC',
            'status' => 'publish',
        ]);
        $items = array_map(function($product) {
            $payload = self::build_product_payload($product);
            $payload['total_sales'] = (int) $product->get_total_sales();
            return $payload;
        }, $result['items']);
        return self::respond(['count' => count($items), 'items' => $items]);
    }

}
