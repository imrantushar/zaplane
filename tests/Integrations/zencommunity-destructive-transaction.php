<?php
// Runs real saved destructive workflows under InnoDB ROLLBACK; existing data survives.
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
wp_set_current_user($admin);
$map=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$results=(array)get_option('zaplane_zenc_destructive_results_20260924',[]);
$cases=[
 'remove_user_space'=>['group_id'=>19,'user_id'=>39,'table'=>'zenc_group_members','column'=>'id','where'=>'group_id=19 AND user_id=39'],
 'delete_post'=>['feed_id'=>37,'table'=>'zenc_feeds','column'=>'id','where'=>'id=37'],
 'delete_comment'=>['feed_id'=>37,'comment_id'=>24,'user_id'=>39,'table'=>'zenc_comments','column'=>'id','where'=>'id=24'],
 'cancel_rsvp'=>['event_id'=>9,'user_id'=>39,'table'=>'zenc_event_rsvps','column'=>'id','where'=>'event_id=9 AND user_id=39']
];
foreach($cases as $key=>$c){
 $id=(int)($map['actions'][$key]??0);
 if(!empty($results[$key]['pass'])){echo "ALREADY_PASS $key".PHP_EOL;continue;}
 $table=$wpdb->prefix.$c['table'];$where=$c['where'];
 $initial=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where");
 if(!$initial){echo "FAIL $key missing preserved fixture".PHP_EOL;continue;}
 $engine=$wpdb->get_var($wpdb->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s",$table));
 if($engine!=='InnoDB'){echo "FAIL $key non-transactional table".PHP_EOL;continue;}
 $started=$wpdb->query('START TRANSACTION');
 if($started===false){echo "FAIL $key no transaction".PHP_EOL;continue;}
 $passed=false;$detail='';$runid=0;
 try{
  $wf=\Zaplane\Models\Workflow::find($id);
  if(!$wf||!$wf->activeVersion())throw new RuntimeException('Missing QA workflow');
  $graph=$wf->activeVersion()->getGraph();
  $updates=array_diff_key($c,array_flip(['table','column','where']));
  $graph['nodes'][1]['data']['config']=array_merge($graph['nodes'][1]['data']['config']??[],$updates);
  $req=new WP_REST_Request('PUT','/zaplane/v1/workflows/'.$id);
  $req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode($graph));
  $res=rest_do_request($req);
  if($res->get_status()>=400)throw new RuntimeException('Graph update '.wp_json_encode($res->get_data()));
  $wf=\Zaplane\Models\Workflow::find($id);
  $wf->activate();do_action('zaplane_workflow_updated',$id);
  $runid=\Zaplane\Framework\Core\Automation::get_instance()->run_workflow($id,['__wp_user_id'=>$admin,'qa'=>'rolled_back']);
  if(!$runid)throw new RuntimeException('No Zaplane run started');
  $nr=$wpdb->prefix.'zaplane_node_runs';
  for($i=0;$i<12;$i++){
   $pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$nr` WHERE run_id=%d AND status='pending'",$runid));
   if(!$pending)break;
   foreach($pending as $nodeid)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$nodeid);
  }
  $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM `$nr` WHERE run_id=%d ORDER BY id",$runid),ARRAY_A);
  $success=false;
  foreach($nodes as $node){if((int)$node['node_key']===2){
   $out=json_decode($node['output_json']??'{}',true);
   $success=$node['status']==='completed'&&!empty($out['data']['success']);
  }}
  if(!$success)throw new RuntimeException('Real workflow action failed '.wp_json_encode($nodes));
  $during=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where");
  if($during!==0)throw new RuntimeException('Database row still present after action');
  $passed=true;$detail='Action completed; row removed inside transaction';
 }catch(\Throwable $ex){$detail=$ex->getMessage();}
 finally{
  $wpdb->query('ROLLBACK');
  \Zaplane\Framework\Classes\Query::flush_trigger_map();
 }
 $restored=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where");
 $persistStatus=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}zaplane_workflows WHERE id=%d",$id));
 $passed=$passed&&$restored===$initial&&$persistStatus==='draft';
 $results[$key]=['pass'=>$passed,'mode'=>'real_workflow_transaction_rollback','run_id_rolled_back'=>$runid,'preserved_count'=>$restored,'detail'=>$detail,'status'=>$persistStatus];
 update_option('zaplane_zenc_destructive_results_20260924',$results,false);
 echo ($passed?'PASS ':'FAIL ').$key.' '.wp_json_encode($results[$key]).PHP_EOL;
}
echo 'SUMMARY '.wp_json_encode($results).PHP_EOL;
