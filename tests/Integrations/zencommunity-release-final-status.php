<?php
require __DIR__.'/zencommunity-cli-bootstrap.php';
$m=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$a=(array)get_option('zaplane_zenc_action_results_20260924',[]);
$t=(array)get_option('zaplane_zenc_trigger_results_20260924',[]);
$d=(array)get_option('zaplane_zenc_destructive_results_20260924',[]);
$dt=(array)get_option('zaplane_zenc_destructive_trigger_results_20260924',[]);
$ids=array_merge(array_values($m['actions']??[]),array_values($m['triggers']??[]),[323,324]);
$statuses=[];foreach($ids as $id){$wf=\Zaplane\Models\Workflow::find((int)$id);$statuses[$wf->status??'missing']=($statuses[$wf->status??'missing']??0)+1;}
echo 'FINAL '.wp_json_encode(['actions_persisted'=>count(array_filter($a,fn($x)=>!empty($x['pass']))),'actions_rollback'=>count(array_filter($d,fn($x)=>!empty($x['pass']))),'triggers'=>count(array_filter($t,fn($x)=>!empty($x['pass']))),'destructive_model_triggers'=>count(array_filter($dt,fn($x)=>!empty($x['pass']))),'workflow_statuses'=>$statuses]).PHP_EOL;
