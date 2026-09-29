<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
$e=(array)get_option('zaplane_zenc_action_entities_20260924',[]);
echo 'ENTITIES '.wp_json_encode($e).PHP_EOL;
$feed=\ZenCommunity\Database\Models\Feed::by_id((int)($e['create_post']['feed_id']??0));
echo 'POST '.wp_json_encode($feed).PHP_EOL;
global $wpdb;
foreach(['zenc_messages','zenc_tickets','zenc_group_members'] as $s){$t=$wpdb->prefix.$s;echo 'TABLE '.$s.' '.wp_json_encode($wpdb->get_results("SELECT * FROM `$t` ORDER BY id DESC LIMIT 3",ARRAY_A)).PHP_EOL;}
