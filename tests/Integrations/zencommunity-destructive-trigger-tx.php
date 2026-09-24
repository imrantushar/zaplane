<?php
// Real ZenCommunity destructive lifecycle events; all DB mutations rolled back.
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$member=39;wp_set_current_user($admin);
$map=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$results=(array)get_option('zaplane_zenc_destructive_trigger_results_20260924',[]);
$cases=[
 'leaves_space'=>['table'=>'zenc_group_members','where'=>'group_id=19 AND user_id=39'],
 'removed_space'=>['table'=>'zenc_group_members','where'=>'group_id=19 AND user_id=39'],
 'post_deleted'=>['table'=>'zenc_feeds','where'=>'id=37'],
 'comment_deleted'=>['table'=>'zenc_comments','where'=>'id=24'],
 'space_deleted'=>['table'=>'zenc_groups','where'=>null]
];
$nr=$wpdb->prefix.'zaplane_node_runs';$runs=$wpdb->prefix.'zaplane_runs';
foreach($cases as $key=>$c){
 if(!empty($results[$key]['pass'])){echo 'ALREADY_PASS '.$key.PHP_EOL;continue;}
 $id=(int)($map['triggers'][$key]??0);
 $table=$wpdb->prefix.$c['table'];$where=$c['where'];
 $initial=$where?(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where"):0;
 if($where&&!$initial)throw new RuntimeException("Missing QA record for $key");
 if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('Cannot start transaction');
 $passed=false;$detail='';$runid=0;$newgroup=0;
 try{
  wp_set_current_user($key==='leaves_space'?$member:$admin);
  $wf=\Zaplane\Models\Workflow::find($id);
  if(!$wf)throw new RuntimeException('Missing trigger workflow');
  $wf->activate();do_action('zaplane_workflow_updated',$id);
  $before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM `$runs` WHERE workflow_id=%d",$id));
  switch($key){
   case 'leaves_space':case 'removed_space':
    \ZenCommunity\Database\Models\Group::remove_member(39,19);break;
   case 'post_deleted':
    \ZenCommunity\Database\Models\Feed::delete(37);break;
   case 'comment_deleted':
    \ZenCommunity\Database\Models\Feed::delete_comment(37,39,24);break;
   case 'space_deleted':
    $category=(int)$wpdb->get_var("SELECT category_id FROM {$wpdb->prefix}zenc_groups WHERE id=19");
    if(!$category)throw new RuntimeException('QA category unavailable');
    $newgroup=\ZenCommunity\Database\Models\Group::create([
      'name'=>'[QA TX] Space deletion trigger','slug'=>'zaplane-qa-tx-deletion-'.wp_rand(100000,999999),
      'privacy'=>'public','status'=>'published'],$category);
    if(!$newgroup)throw new RuntimeException('No transactional group');
    \ZenCommunity\Database\Models\Group::delete((int)$newgroup);break;
  }
  $runid=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM `$runs` WHERE workflow_id=%d",$id));
  if($runid<=$before)throw new RuntimeException('Real model event did not fire Zaplane workflow');
  wp_set_current_user(0);
  for($i=0;$i<12;$i++){
   $pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$nr` WHERE run_id=%d AND status='pending'",$runid));
   if(!$pending)break;
   foreach($pending as $node)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$node);
  }
  $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM `$nr` WHERE run_id=%d ORDER BY id",$runid),ARRAY_A);
  $status=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM `$runs` WHERE id=%d",$runid));
  if($status!=='completed'||count($nodes)!==2||$nodes[1]['status']!=='completed'||empty(json_decode($nodes[1]['output_json'],true)['data']['success']))
    throw new RuntimeException('Workflow was not completed '.wp_json_encode($nodes));
  if($where){
   $during=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where");
   if($during!==0)throw new RuntimeException('Model did not remove the entity');
  }
  $passed=true;$detail='Real model event -> workflow -> notification passed in transaction';
 }catch(\Throwable $e){$detail=$e->getMessage();}
 finally{
  $wpdb->query('ROLLBACK');
  wp_set_current_user($admin);
  \Zaplane\Framework\Classes\Query::flush_trigger_map();
 }
 $restored=$where?(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $where"):(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE id=%d",$newgroup));
 $expected=$where?$initial:0;
 $status=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}zaplane_workflows WHERE id=%d",$id));
 $passed=$passed&&$restored===$expected&&$status==='paused';
 $results[$key]=['pass'=>$passed,'mode'=>'real_model_workflow_rollback','run_id_rolled_back'=>$runid,'restored_count'=>$restored,'detail'=>$detail];
 update_option('zaplane_zenc_destructive_trigger_results_20260924',$results,false);
 echo ($passed?'PASS ':'FAIL ').$key.' '.wp_json_encode($results[$key]).PHP_EOL;
}
echo 'SUMMARY '.wp_json_encode($results).PHP_EOL;
