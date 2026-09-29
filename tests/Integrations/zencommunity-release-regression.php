<?php
// Member-triggered admin-owned automation, under cookie-less worker execution.
// Negative-chain regression lives in zencommunity-error-route-test.php.
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$map=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$member=39;$nr=$wpdb->prefix.'zaplane_node_runs';$runs=$wpdb->prefix.'zaplane_runs';
$id=(int)$map['triggers']['user_registers'];
$wf=\Zaplane\Models\Workflow::find($id);
if(!$wf||!user_can($admin,'manage_options')||user_can($member,'manage_options'))throw new RuntimeException('Invalid QA identities');
$wf->activate();do_action('zaplane_workflow_updated',$id);
try{
 $before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM `$runs` WHERE workflow_id=%d",$id));
 wp_set_current_user($member);
 do_action('zencommunity/profile/created',39,['user_id'=>39]);
 $run=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM `$runs` WHERE workflow_id=%d",$id));
 if($run<=$before)throw new RuntimeException('No member-triggered run');
 wp_set_current_user(0);
 for($i=0;$i<12;$i++){
  $pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$nr` WHERE run_id=%d AND status='pending'",$run));
  if(!$pending)break;
  foreach($pending as $nid)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$nid);
 }
 $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM `$nr` WHERE run_id=%d ORDER BY id",$run),ARRAY_A);
 $status=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM `$runs` WHERE id=%d",$run));
 $pass=$status==='completed'&&count($nodes)===2&&$nodes[1]['status']==='completed'&&!empty(json_decode($nodes[1]['output_json'],true)['data']['success'])&&get_current_user_id()===0;
 echo 'MEMBER_BACKGROUND_TRIGGER '.wp_json_encode(['pass'=>$pass,'run_id'=>$run,'status'=>$status,'actor_restored'=>get_current_user_id()===0]).PHP_EOL;
 if(!$pass)throw new RuntimeException('Member-triggered workflow failed');
}finally{$wf->pause();do_action('zaplane_workflow_updated',$id);wp_set_current_user($admin);}
