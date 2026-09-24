<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
wp_set_current_user($admin);global $wpdb;
$title='[Zaplane QA KEEP] ZenCommunity failure must halt chain 2026-09-24';
$wf=\Zaplane\Models\Workflow::where('title',$title)->first();
$mode=$argv[1]??'setup';
if($mode==='setup'){
 if(!$wf){
  $req=new WP_REST_Request('POST','/zaplane/v1/workflows');
  $req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode(['title'=>$title]));
  $res=rest_do_request($req);
  if($res->get_status()>=400)throw new RuntimeException('Cannot create workflow');
  $wf=\Zaplane\Models\Workflow::find((int)$res->get_data()['id']);
 }
 $graph=['nodes'=>[
 ['id'=>'1','type'=>'trigger','position'=>['x'=>0,'y'=>0],'data'=>['app'=>'manual','event'=>'run_manually','label'=>'Run manually','config'=>[]]],
 ['id'=>'2','type'=>'action','position'=>['x'=>390,'y'=>0],'data'=>['app'=>'zencommunity','event'=>'send_notification','label'=>'Intentionally invalid recipient','config'=>['user_id'=>0,'message'=>'Expected rejection','notification_type'=>'custom']]],
 ['id'=>'3','type'=>'action','position'=>['x'=>780,'y'=>0],'data'=>['app'=>'zencommunity','event'=>'send_notification','label'=>'Must never execute','config'=>['user_id'=>39,'message'=>'Do NOT send: failure regression','notification_type'=>'custom']]],
 ],'edges'=>[
 ['id'=>'e1-2','source'=>'1','target'=>'2','sourceHandle'=>'main'],
 ['id'=>'e2-3','source'=>'2','target'=>'3','sourceHandle'=>'main']
 ],'integration_icons'=>['zencommunity']];
 $req=new WP_REST_Request('PUT','/zaplane/v1/workflows/'.$wf->id);
 $req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode($graph));
 $res=rest_do_request($req);
 if($res->get_status()>=400)throw new RuntimeException('Cannot save graph '.wp_json_encode($res->get_data()));
 echo 'SETUP '.wp_json_encode(['id'=>$wf->id,'rest'=>$res->get_data(),'status'=>$wf->status]).PHP_EOL;
 exit;
}
if(!$wf)throw new RuntimeException('Run setup first');
$id=(int)$wf->id;
$graph=$wf->activeVersion()->getGraph();
if(count($graph['nodes']??[])!==3||($graph['nodes'][1]['data']['config']['user_id']??-1)!==0)throw new RuntimeException('Persisted failure graph is incorrect');
$wf->activate();do_action('zaplane_workflow_updated',$id);
try {
 $run=\Zaplane\Framework\Core\Automation::get_instance()->run_workflow($id,['__wp_user_id'=>$admin]);
 $nr=$wpdb->prefix.'zaplane_node_runs';
 for($i=0;$i<12;$i++){
  $pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$nr` WHERE run_id=%d AND status='pending'",$run));
  if(!$pending)break;
  foreach($pending as $node)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$node);
 }
 $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM `$nr` WHERE run_id=%d ORDER BY id",$run),ARRAY_A);
 $runstatus=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$run));
 $passed=$runstatus==='failed'&&count($nodes)===2&&$nodes[1]['status']==='failed';
 echo 'FAILURE_TEST '.wp_json_encode(['pass'=>$passed,'run_id'=>$run,'status'=>$runstatus,'nodes'=>$nodes]).PHP_EOL;
 if(!$passed)throw new RuntimeException('Invalid action did not stop the chain');
} finally {$wf->pause();do_action('zaplane_workflow_updated',$id);}
