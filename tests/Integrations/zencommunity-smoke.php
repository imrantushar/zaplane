<?php
require 'C:/Users/dell/Local Sites/kodezen/app/public/wp-load.php';

$class = \Zaplane\Integrations\Zencommunity::class;
echo 'INTEGRATION_CLASS=' . ( class_exists( $class ) ? 'YES' : 'NO' ) . PHP_EOL;
if ( ! class_exists( $class ) ) {
	exit( 2 );
}
echo 'ZEN_MODEL=' . ( class_exists( \ZenCommunity\Database\Models\Feed::class ) ? 'YES' : 'NO' ) . PHP_EOL;
echo 'TRIGGERS=' . count( $class::get_triggers() ) . PHP_EOL;
echo 'ACTIONS=' . count( $class::get_actions() ) . PHP_EOL;
echo 'DYNAMIC=' . implode( ',', array_keys( $class::get_dynamic_queries() ) ) . PHP_EOL;
$tests = [
	'post_reacted' => [ 'love', 31, 32, 33, null, [] ],
	'comment_reacted' => [ 'love', 31, 32, 33, 5, [] ],
	'comment_reply' => [ 51, 52, 53, 54, [], [ 'id' => 51 ] ],
	'comment_added' => [ 51, 52, 53, null, [], [ 'id' => 51 ] ],
	'requests_space' => [ 9, [ 'user_id' => 10, 'status' => 'pending', 'role' => 'member' ] ],
	'joins_space' => [ 9, [ 'user_id' => 10, 'status' => 'active', 'role' => 'member' ] ],
];
foreach ( $tests as $event => $args ) {
	$value = $class::resolve_trigger( [ 'event' => $event ], $args );
	echo 'TRIGGER_' . $event . '=' . ( is_array( $value ) ? 'PASS' : 'FAIL' ) . PHP_EOL;
}
try {
	$class::execute_node( [ 'data' => [ 'event' => 'unknown_action' ] ], [] );
	echo 'INVALID_ACTION=FAIL' . PHP_EOL;
} catch ( \RuntimeException $e ) {
	echo 'INVALID_ACTION=PASS' . PHP_EOL;
}

