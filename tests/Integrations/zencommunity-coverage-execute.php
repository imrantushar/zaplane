<?php
// CLI-only, idempotent action workflow executor; no deletions.
if ( PHP_SAPI !== 'cli' ) { exit; }
require __DIR__ . '/zencommunity-cli-bootstrap.php';
$admin = absint(get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0] ?? 0);
if (!$admin) {throw new RuntimeException('Missing admin');}
wp_set_current_user($admin);
global $wpdb;
$map=(array)get_option('zaplane_zenc_coverage_matrix_20260924',[]);
$results=(array)get_option('zaplane_zenc_action_results_20260924',[]);
$entities=(array)get_option('zaplane_zenc_action_entities_20260924',[]);
$batch=$argv[1]??'basic';
$batches=[
'basic'=>['add_user_space','update_profile','create_post','update_post','add_comment','add_reply','add_reaction','send_notification','create_poll','create_event','update_event','set_rsvp','send_private_message','send_group_message','create_ticket'],
'advanced'=>['change_community_role','change_space_role','approve_space_request','reject_space_request','assign_ticket','change_ticket_status','change_ticket_priority','reply_ticket','note_ticket','close_ticket','reopen_ticket','block_space_user','unblock_space_user','reject_user','approve_user'],
];
function zcx_rest_save(int $id,array $changes):void {
 $wf=\Zaplane\Models\Workflow::find($id);
 $version=$wf?$wf->activeVersion():null;
 if(!$version){throw new RuntimeException('No workflow version: '.$id);}
 $graph=$version->getGraph(); $graph['nodes'][1]['data']['config']=array_merge(
  $graph['nodes'][1]['data']['config']??[],$changes);
 $request=new WP_REST_Request('PUT','/zaplane/v1/workflows/'.$id);
 $request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($graph));
 $response=rest_do_request($request);
 if($response->get_status()>=400){throw new RuntimeException('Graph update: '.wp_json_encode($response->get_data()));}
}
function zcx_run(int $id,int $admin):array {
 global $wpdb;
 $wf=\Zaplane\Models\Workflow::find($id);
 if(!$wf){throw new RuntimeException('Missing workflow '.$id);}
 $wf->activate();do_action('zaplane_workflow_updated',$id);
 try{
  $run_id=\Zaplane\Framework\Core\Automation::get_instance()->run_workflow($id,
   ['__wp_user_id'=>$admin,'qa'=>'preserve']);
  if(!$run_id){throw new RuntimeException('No live run started');}
  $nr=$wpdb->prefix.'zaplane_node_runs';
  for($iteration=0;$iteration<12;$iteration++){
   $ids=$wpdb->get_col($wpdb->prepare(
    "SELECT id FROM {$nr} WHERE run_id=%d AND status='pending' ORDER BY id",$run_id));
   if(!$ids){break;}
   foreach($ids as $nid){\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$nid);}
  }
  $nodes=$wpdb->get_results($wpdb->prepare(
   "SELECT node_key,status,output_json FROM {$nr} WHERE run_id=%d ORDER BY id",$run_id),ARRAY_A);
  $out=null;
  foreach($nodes as $node){
   if((int)$node['node_key']===2){
    $out=json_decode($node['output_json']??'{}',true);
    if($node['status']!=='completed'||empty($out['data']['success'])){
     throw new RuntimeException('Action failed: '.wp_json_encode($node));
    }
   } elseif($node['status']!=='completed'){
    throw new RuntimeException('Trigger failed: '.wp_json_encode($node));
   }
  }
  if(!$out){throw new RuntimeException('No action node executed');}
  return ['run_id'=>(int)$run_id,'data'=>$out['data']];
 }finally{
  $wf=\Zaplane\Models\Workflow::find($id);
  if($wf){$wf->pause();do_action('zaplane_workflow_updated',$id);}
 }
}
function zcx_pending(int $admin,int $space,string $username):int {
 $uid=username_exists($username);
 if(!$uid){
  $uid=wp_create_user($username,wp_generate_password(32),$username.'@example.invalid');
  if(is_wp_error($uid)){throw new RuntimeException($uid->get_error_message());}
 }
 $uid=absint($uid);
 $profile=\ZenCommunity\Database\Models\Profile::class;
 if(!$profile::exists($uid)){$profile::create($uid,['username'=>$username,'first_name'=>'Zaplane QA',
  'last_name'=>'Join Request','status'=>'active']);}
 $membership=$GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
  "SELECT status FROM {$GLOBALS['wpdb']->prefix}zenc_group_members WHERE group_id=%d AND user_id=%d",
  $space,$uid));
 if(!$membership){\ZenCommunity\Database\Models\Group::add_member($uid,$space,'member','pending');}
 return $uid;
}
if(!isset($batches[$batch])){throw new RuntimeException('Unknown batch');}
foreach($batches[$batch] as $key){
 $id=absint($map['actions'][$key]??0);
 if(!$id){echo 'MISSING '.$key.PHP_EOL;continue;}
 if(!empty($results[$key]['pass'])){echo 'ALREADY_PASS '.$key.' '.$results[$key]['run_id'].PHP_EOL;continue;}
 try{
  $changes=[];
  if(in_array($key,['create_post','create_poll'],true)){$changes['user_id']=$admin;}
  if($key==='create_post'){$changes['title']='[QA KEEP] Action create post';}
  if($key==='update_post'){$changes=['feed_id'=>$entities['create_post']['feed_id']??0,
   'title'=>'[QA KEEP] Action update post'];}
  if($key==='add_comment'){$changes=['feed_id'=>$entities['create_post']['feed_id']??0,
   'user_id'=>39,'content'=>'[QA KEEP] Action add comment'];}
  if($key==='add_reply'){$changes=['feed_id'=>$entities['create_post']['feed_id']??0,
   'comment_id'=>$entities['add_comment']['comment_id']??0,'user_id'=>39,
   'content'=>'[QA KEEP] Action reply to comment'];}
  if($key==='add_reaction'){$changes=['feed_id'=>$entities['create_post']['feed_id']??0,
   'user_id'=>39,'reaction_type'=>'love'];}
  if($key==='create_event'){$changes=['title'=>'[QA KEEP] Action create event'];}
  if($key==='update_event'){$changes=['event_id'=>$entities['create_event']['event_id']??0,
   'title'=>'[QA KEEP] Action update event'];}
  if($key==='set_rsvp'){$changes=['event_id'=>$entities['create_event']['event_id']??0,
   'user_id'=>39,'status'=>'going'];}
  if($key==='create_ticket'){$changes=['user_id'=>39,'title'=>'[QA KEEP] Action create support ticket'];}
  if($key==='change_community_role'){$changes=['user_id'=>39,'role'=>'support_agent'];}
  if($key==='change_space_role'){$changes=['group_id'=>19,'user_id'=>39,'role'=>'moderator'];}
  if(in_array($key,['approve_space_request','reject_space_request'],true)){
   $uid=zcx_pending($admin,21,'zaplane_qa_'.($key==='approve_space_request'?'approved':'rejected').'_0924');
   $changes=['group_id'=>21,'user_id'=>$uid];
  }
  if($key==='assign_ticket'){$changes=['ticket_id'=>$entities['create_ticket']['ticket_id']??0,
   'agent_id'=>39];}
  if(in_array($key,['change_ticket_status','change_ticket_priority','reply_ticket',
   'note_ticket','close_ticket','reopen_ticket'],true)){
   $changes['ticket_id']=$entities['create_ticket']['ticket_id']??0;
  }
  if($key==='change_ticket_status'){$changes['status']='in-progress';}
  if($key==='change_ticket_priority'){$changes['priority_id']=17;}
  if($key==='reply_ticket'){$changes=['ticket_id'=>$entities['create_ticket']['ticket_id']??0,
    'user_id'=>$admin,'content'=>'[QA KEEP] Support agent replied'];}
  if($key==='note_ticket'){$changes['content']='[QA KEEP] Internal support note';}
  if(in_array($key,['block_space_user','unblock_space_user'],true)){
   $changes=['group_id'=>19,'user_id'=>39];
  }
  if(in_array($key,['reject_user','approve_user'],true)){$changes=['user_id'=>39];}
  if($changes){zcx_rest_save($id,$changes);}
  $out=zcx_run($id,$admin);
  $results[$key]=['pass'=>true,'run_id'=>$out['run_id'],'output'=>$out['data']];
  $entities[$key]=$out['data'];
  update_option('zaplane_zenc_action_results_20260924',$results,false);
  update_option('zaplane_zenc_action_entities_20260924',$entities,false);
  echo 'PASS '.$key.' '.wp_json_encode($out).PHP_EOL;
 }catch(\Throwable $e){
  $results[$key]=['pass'=>false,'error'=>$e->getMessage()];
  update_option('zaplane_zenc_action_results_20260924',$results,false);
  echo 'FAIL '.$key.' '.$e->getMessage().PHP_EOL;
 }
}
echo 'SUMMARY '.wp_json_encode(array_map(static fn($r)=>(bool)($r['pass']??false),$results)).PHP_EOL;

