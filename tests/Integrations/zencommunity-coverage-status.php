<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
$r=(array)get_option('zaplane_zenc_action_results_20260924',[]);
$m=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
foreach($r as $k=>$v) echo ($v['pass']?'PASS ':'FAIL ').$k.' '.($v['run_id']??$v['error']??'').PHP_EOL;
echo 'COUNTS '.wp_json_encode(['triggers'=>count($m['triggers']??[]),'actions'=>count($m['actions']??[]),'passes'=>count(array_filter($r,fn($x)=>!empty($x['pass']))),'fails'=>count(array_filter($r,fn($x)=>empty($x['pass'])))]).PHP_EOL;
$t=(array)get_option('zaplane_zenc_trigger_results_20260924',[]);
echo 'TRIGGER_COUNTS '.wp_json_encode(['passes'=>count(array_filter($t,fn($x)=>!empty($x['pass']))),'workflow_runs'=>count(array_filter($t,fn($x)=>($x['mode']??'')==='synthetic_hook_live_workflow')),'resolver_only'=>count(array_filter($t,fn($x)=>($x['mode']??'')==='resolver_only_non_destructive'))]).PHP_EOL;
global $wpdb;
$ids=array_merge(array_values($m['triggers']??[]),array_values($m['actions']??[]));
$states=[];$pending=[];
foreach($ids as $id){$wf=\Zaplane\Models\Workflow::find((int)$id);$status=$wf?$wf->status:'missing';$states[$status]=($states[$status]??0)+1;}
$nr=$wpdb->prefix.'zaplane_node_runs';$runs=$wpdb->prefix.'zaplane_runs';
$pending=$wpdb->get_results("SELECT r.workflow_id,n.run_id,n.id AS node_id FROM `$nr` n JOIN `$runs` r ON r.id=n.run_id WHERE r.workflow_id BETWEEN 257 AND 322 AND n.status='pending' ORDER BY n.id LIMIT 30",ARRAY_A);
echo 'WORKFLOW_STATES '.wp_json_encode($states).PHP_EOL;
echo 'PENDING_QA_NODES '.wp_json_encode($pending).PHP_EOL;
