<?php
// Non-destructive hook/real-workflow coverage. Does not call model deletion APIs.
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;
$map=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$results=(array)get_option('zaplane_zenc_trigger_results_20260924',[]);
$admin=absint(get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]??0);
if(!$admin)throw new RuntimeException('No admin');
$c=\Zaplane\Integrations\Zencommunity::class;
$feed=\ZenCommunity\Database\Models\Feed::by_id(37);
$space=\ZenCommunity\Database\Models\Group::by_id(19);
$poll=\ZenCommunity\Database\Models\Feed::by_id(38);
$messages=$wpdb->prefix.'zenc_messages';
$first=$wpdb->get_row("SELECT * FROM `$messages` WHERE group_id IS NULL AND sender_id>0 AND receiver_id>0 ORDER BY id LIMIT 1",ARRAY_A);
$delete_only=['leaves_space','removed_space','space_deleted','post_deleted','comment_deleted'];
$args=[
'user_registers'=>[39,['user_id'=>39]], 'profile_updated'=>[39,['first_name'=>'Zaplane QA'],[]],
'joins_space'=>[19,['user_id'=>39,'role'=>'member','status'=>'active']],
'requests_space'=>[21,['user_id'=>41,'role'=>'member','status'=>'pending']],
'leaves_space'=>[19,39], 'removed_space'=>[19,39],
'space_role_changed'=>[19,39,'moderator'],
'space_created'=>[19,$space], 'space_updated'=>[19,$space], 'space_deleted'=>[19,$space],
'post_created'=>[37,$feed], 'post_in_space'=>[37,$feed],
'post_updated'=>[37,$feed], 'post_deleted'=>[37,$feed],
'post_mentioned'=>[37,[39],'[QA KEEP] @mention'],
'post_reacted'=>['love',9991,37,39,null,[]],
'comment_added'=>[23,37,39,null,[],['id'=>23]],
'comment_reply'=>[24,37,39,23,[],['id'=>24]],
'comment_updated'=>[37,39,23], 'comment_deleted'=>[23,37,39,[]],
'comment_reacted'=>['love',9992,37,39,23,[]],
'poll_created'=>[38,$poll], 'event_created'=>[9,19,['title'=>'[QA KEEP] event']],
'ticket_created'=>[9,\ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::by_id(9)], 'ticket_status'=>[9,['status'=>'closed']],
'ticket_priority'=>[9,['priority_id'=>24]],
'ticket_assigned'=>[['data'=>['event_type'=>'ticket_assigned','object_id'=>9],'to_user_ids'=>[39],'notification_id'=>9999]],
'ticket_closed'=>[9,['status'=>'open'],$admin], 'ticket_reopened'=>[9,['status'=>'closed'],$admin],
'agent_reply'=>[9,$admin,16,['is_note'=>false]],
'customer_reply'=>[9,39,16,['is_note'=>false]],
'private_conversation'=>[(int)($first['id']??0),['sender_id'=>(int)($first['sender_id']??0),'receiver_id'=>(int)($first['receiver_id']??0)]],
];
$batch=$argv[1]??'all';
$keys=$batch==='quick'?array_slice(array_keys($c::get_triggers()),0,7):array_keys($c::get_triggers());
$nr=$wpdb->prefix.'zaplane_node_runs';
foreach($keys as $key){
 $id=absint($map['triggers'][$key]??0);
 if(!$id){echo "MISSING $key".PHP_EOL;continue;}
 if(!empty($results[$key]['pass'])){echo "ALREADY_PASS $key".PHP_EOL;continue;}
 $wf=\Zaplane\Models\Workflow::find($id);
 try{
  if(!$wf)throw new RuntimeException('Workflow not found');
  if(!isset($args[$key]))throw new RuntimeException('Missing fixture');
  $cfg=$key==='post_in_space'?['group_id'=>19]:[];
  wp_set_current_user($key==='leaves_space'?39:$admin);
  $payload=$c::resolve_trigger(['event'=>$key,'config'=>$cfg],$args[$key]);
  if(!is_array($payload)||!$payload)throw new RuntimeException('Resolver rejected fixture');
  if(in_array($key,$delete_only,true)){
    $results[$key]=['pass'=>true,'mode'=>'resolver_only_non_destructive','payload'=>$payload];
    echo "PASS_RESOLVER_ONLY $key".PHP_EOL;
  }else{
    wp_set_current_user($admin);
    $before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}zaplane_runs WHERE workflow_id=%d",$id));
    $wf->activate();do_action('zaplane_workflow_updated',$id);
    $hook=$c::get_triggers()[$key]['hook'];
    do_action_ref_array($hook,$args[$key]);
    $run_id=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}zaplane_runs WHERE workflow_id=%d",$id));
    if($run_id<=$before)throw new RuntimeException('Hook did not create workflow run');
    for($i=0;$i<12;$i++){
      $pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM $nr WHERE run_id=%d AND status='pending' ORDER BY id",$run_id));
      if(!$pending)break;
      foreach($pending as $nid)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$nid);
    }
    $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM $nr WHERE run_id=%d ORDER BY id",$run_id),ARRAY_A);
    $seenTrigger=false;$seenAction=false;
    foreach($nodes as $node){
      $out=json_decode($node['output_json']??'{}',true);
      if((int)$node['node_key']===1){$seenTrigger=$node['status']==='completed';}
      if((int)$node['node_key']===2){$seenAction=$node['status']==='completed'&&!empty($out['data']['success']);}
    }
    if(!$seenTrigger||!$seenAction)throw new RuntimeException('Incomplete workflow nodes '.wp_json_encode($nodes));
    $results[$key]=['pass'=>true,'mode'=>'synthetic_hook_live_workflow','run_id'=>$run_id];
    echo "PASS_HOOK_WORKFLOW $key $run_id".PHP_EOL;
  }
 }catch(\Throwable $e){
   $results[$key]=['pass'=>false,'error'=>$e->getMessage()];
   echo "FAIL $key ".$e->getMessage().PHP_EOL;
 }finally{
   if($wf){$wf->pause();do_action('zaplane_workflow_updated',$id);}
   update_option('zaplane_zenc_trigger_results_20260924',$results,false);
 }
}
echo 'SUMMARY '.wp_json_encode(['count'=>count($results),'pass'=>count(array_filter($results,fn($r)=>!empty($r['pass']))),'errors'=>array_filter($results,fn($r)=>empty($r['pass']))]).PHP_EOL;
