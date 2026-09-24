<?php
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$names=['zenc_feeds','zenc_feed_comments','zenc_group_members','zenc_event_rsvps','zaplane_workflows','zaplane_workflow_versions','zaplane_runs','zaplane_node_runs'];
foreach($names as $name){$table=$wpdb->prefix.$name;$r=$wpdb->get_row($wpdb->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s",$table),ARRAY_A);echo 'ENGINE '.wp_json_encode($r).PHP_EOL;}
echo 'ZENC_TABLE_ENGINES '.wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME",$wpdb->esc_like($wpdb->prefix.'zenc_').'%'),ARRAY_A)).PHP_EOL;
