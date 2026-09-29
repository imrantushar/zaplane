<?php
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$id=(int)((array)get_option('zaplane_zenc_coverage_matrix_20260924',[]))['triggers']['user_registers'];
$wf=\Zaplane\Models\Workflow::find($id);$wf->activate();do_action('zaplane_workflow_updated',$id);
$hook='zencommunity/profile/created';
$node=$wf->activeVersion()->getGraph()['nodes'][0];
echo 'DEBUG_BEFORE '.wp_json_encode(['resolved'=>\Zaplane\Integrations\Zencommunity::resolve_trigger($node['data'],[39,['user_id'=>39]]),'active'=>\Zaplane\Framework\Classes\Query::get_active_workflows_for_event($hook),'has'=>has_action($hook),'listeners'=>get_option('zaplane_listeners_active')]).PHP_EOL;
do_action($hook,39,['user_id'=>39]);
echo 'DEBUG_AFTER '.wp_json_encode(['runs'=>$wpdb->get_results($wpdb->prepare("SELECT id,status FROM {$wpdb->prefix}zaplane_runs WHERE workflow_id=%d ORDER BY id DESC LIMIT 8",$id),ARRAY_A),'error'=>$wpdb->last_error]).PHP_EOL;
$wf->pause();do_action('zaplane_workflow_updated',$id);
