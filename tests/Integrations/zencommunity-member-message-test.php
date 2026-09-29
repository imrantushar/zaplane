<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/zencommunity-cli-bootstrap.php';
global $wpdb;$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
wp_set_current_user($admin);
$title='[Zaplane QA KEEP] Member-triggered admin message';
$wf=\Zaplane\Models\Workflow::where('title',$title)->first();
$mode=$argv[1]??'setup';
if($mode==='setup'){
 if(!$wf){$r=new WP_REST_Request('POST','/zaplane/v1/workflows');$r->set_header('Content-Type','application/json');
 $r->set_body(wp_json_encode(['title'=>$title]));$res=rest_do_request($r);
 if($res->get_status()>=400)throw new RuntimeException('Create failed');$wf=\Zaplane\Models\Workflow::find((int)$res->get_data()['id']);}
 $graph=['nodes'=>[
 ['id'=>'1','type'=>'trigger','position'=>['x'=>0,'y'=>0],'data'=>['app'=>'zencommunity','event'=>'user_registers','hook'=>'zencommunity/profile/created','label'=>'QA member event','config'=>[]]],
 ['id'=>'2','type'=>'action','position'=>['x'=>390,'y'=>0],'data'=>['app'=>'zencommunity','event'=>'send_private_message','label'=>'Admin replies','config'=>['sender_id'=>$admin,'receiver_id'=>39,'message'=>'[QA KEEP] Admin-owned workflow from member trigger']]],
 ],'edges'=>[['id'=>'e1-2','source'=>'1','target'=>'2','sourceHandle'=>'main']],'integration_icons'=>['zencommunity']];
 $r=new WP_REST_Request('PUT','/zaplane/v1/workflows/'.$wf->id);$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($graph));
 $res=rest_do_request($r);if($res->get_status()>=400)throw new RuntimeException('Save failed');
 echo 'CREATED_MESSAGE_QA '.$wf->id.PHP_EOL;exit;
}
if(!$wf)throw new RuntimeException('No saved QA workflow');
$id=(int)$wf->id;$wf->activate();do_action('zaplane_workflow_updated',$id);
try{
 $before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}zaplane_runs WHERE workflow_id=%d",$id));
 wp_set_current_user(39);do_action('zencommunity/profile/created',39,['user_id'=>39]);wp_set_current_user(0);
 $run=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}zaplane_runs WHERE workflow_id=%d",$id));
 if($run<=$before)throw new RuntimeException('No workflow run');
 $nr=$wpdb->prefix.'zaplane_node_runs';
 for($i=0;$i<10;$i++){$pending=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$nr` WHERE run_id=%d AND status='pending'",$run));
 if(!$pending)break;foreach($pending as $n)\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run((int)$n);}
 $nodes=$wpdb->get_results($wpdb->prepare("SELECT node_key,status,output_json FROM `$nr` WHERE run_id=%d ORDER BY id",$run),ARRAY_A);
 $out=json_decode($nodes[1]['output_json']??'{}',true);
 $msgid=(int)($out['data']['message_id']??0);
 $msg=$wpdb->get_row($wpdb->prepare("SELECT id,sender_id,receiver_id,group_id FROM {$wpdb->prefix}zenc_messages WHERE id=%d",$msgid),ARRAY_A);
 $pass=$nodes[1]['status']==='completed'&&!empty($out['data']['success'])&&(int)($msg['sender_id']??0)===$admin&&(int)($msg['receiver_id']??0)===39&&get_current_user_id()===0;
 echo 'MEMBER_TO_ADMIN_MESSAGE '.wp_json_encode(['pass'=>$pass,'run_id'=>$run,'message'=>$msg,'actor_after'=>get_current_user_id()]).PHP_EOL;
 if(!$pass)throw new RuntimeException('Message routing failed');
}finally{$wf->pause();do_action('zaplane_workflow_updated',$id);wp_set_current_user($admin);}
