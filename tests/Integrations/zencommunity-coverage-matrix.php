<?php
// CLI only. Preserves all existing and generated records; never deletes.
if ( PHP_SAPI !== 'cli' ) { exit; }
require __DIR__ . '/zencommunity-cli-bootstrap.php';
$c = \Zaplane\Integrations\Zencommunity::class;
$admins = get_users( [ 'role'=>'administrator', 'number'=>1, 'fields'=>'ID' ] );
$admin = absint( $admins[0] ?? 0 );
if ( ! $admin ) { throw new RuntimeException( 'Missing administrator' ); }
wp_set_current_user( $admin );
$test_user = 39; $space = 19; $feed = 33; $comment = 22; $event_id = 8; $ticket = 8;
$test_prefix = '[Zaplane QA COVERAGE 2026-09-24] ';
$state = (array) get_option( 'zaplane_zenc_coverage_matrix_20260924', [] );
$state += [ 'triggers'=>[], 'actions'=>[], 'errors'=>[] ];
$defaults = [ 'user_id'=>$test_user, 'group_id'=>$space, 'feed_id'=>$feed,
 'comment_id'=>$comment, 'event_id'=>$event_id, 'ticket_id'=>$ticket,
 'role'=>'member', 'agent_id'=>$admin, 'sender_id'=>$admin, 'receiver_id'=>$test_user,
 'type_id'=>23, 'product_id'=>6, 'priority_id'=>24, 'status'=>'going',
 'title'=>'[QA KEEP] ZenCommunity integration coverage', 'content'=>'QA: preserved test record.',
 'message'=>'QA: preserved test message.', 'notification_type'=>'custom',
 'reaction_type'=>'like', 'first_name'=>'Zaplane', 'last_name'=>'QA',
 'display_name'=>'Zaplane QA member', 'bio'=>'Testing integration workflows.',
 'options'=>"A\nB", 'expires_at'=>gmdate('Y-m-d H:i:s',time()+7*DAY_IN_SECONDS),
 'start_at'=>gmdate('Y-m-d H:i:s',time()+2*DAY_IN_SECONDS),
 'end_at'=>gmdate('Y-m-d H:i:s',time()+2*DAY_IN_SECONDS+7200),
 'description'=>'Preserved QA integration event.', 'location'=>'Test Space' ];
function zcm_node( int $id, string $type, string $app, string $event, array $config ): array {
 return [ 'id'=>(string)$id, 'type'=>$type, 'position'=>['x'=>($id-1)*390,'y'=>120],
  'data'=>['app'=>$app,'event'=>$event,'label'=>ucwords(str_replace('_',' ',$event)),
   'hook'=>($type==='trigger' && $app==='zencommunity'
     ? (\Zaplane\Integrations\Zencommunity::get_triggers()[$event]['hook']??'') : ''),
   'config'=>$config] ];
}
function zcm_rest( string $method, string $route, array $args ): array {
 $req = new WP_REST_Request($method,$route);
 $req->set_header('Content-Type','application/json'); $req->set_body(wp_json_encode($args));
 $res = rest_do_request($req);
 if($res->get_status()>=400){throw new RuntimeException($route.' HTTP '.$res->get_status().' '.wp_json_encode($res->get_data()));}
 return (array)$res->get_data();
}
function zcm_create( string $title, array $nodes ): int {
 $old = \Zaplane\Models\Workflow::where('title',$title)->first();
 if($old){ return (int)$old->id; }
 $new = zcm_rest('POST','/zaplane/v1/workflows',['title'=>$title]); $id=absint($new['id']??0);
 if(!$id){throw new RuntimeException('Missing workflow ID');}
 zcm_rest('PUT','/zaplane/v1/workflows/'.$id,['nodes'=>$nodes,'edges'=>[
  ['id'=>'e1-2','source'=>'1','target'=>'2','sourceHandle'=>'main']
 ],'integration_icons'=>['zencommunity']]);
 return $id;
}
$all = [ 'triggers'=>$c::get_triggers(),'actions'=>$c::get_actions() ];
foreach($all as $kind=>$items){
 foreach($items as $key=>$meta){
  if(!empty($state[$kind][$key])) {continue;}
  try {
   $title=$test_prefix.strtoupper($kind==='triggers'?'TRIGGER':'ACTION').' '.$key;
   if($kind==='triggers'){
    $cfg=$key==='post_in_space'?['group_id'=>$space]:[];
    $nodes=[zcm_node(1,'trigger','zencommunity',$key,$cfg),
     zcm_node(2,'action','zencommunity','send_notification',[
      'user_id'=>$test_user,'message'=>'QA coverage: '.$key,'notification_type'=>'custom'])];
   }else{
    $cfg=[];
    foreach($c::get_action_config_schema($key) as $field){
     if(isset($defaults[$field['key']])) {$cfg[$field['key']]=$defaults[$field['key']];}
    }
    $nodes=[zcm_node(1,'trigger','manual','run_manually',[]),
     zcm_node(2,'action','zencommunity',$key,$cfg)];
   }
   $id=zcm_create($title,$nodes);
   $state[$kind][$key]=$id;
   update_option('zaplane_zenc_coverage_matrix_20260924',$state,false);
   echo 'CREATED '.strtoupper($kind).' '.$key.' '.$id.PHP_EOL;
  }catch(\Throwable $e){
   $state['errors'][$kind.'/'.$key]=$e->getMessage();
   update_option('zaplane_zenc_coverage_matrix_20260924',$state,false);
   echo 'ERROR '.$kind.'/'.$key.' '.$e->getMessage().PHP_EOL;
  }
 }
}
$ids=array_merge(array_values($state['triggers']),array_values($state['actions']));
echo 'RESULT '.wp_json_encode(['trigger_workflows'=>count($state['triggers']),
 'action_workflows'=>count($state['actions']),'errors'=>$state['errors'],
 'unique_ids'=>count(array_unique($ids))]).PHP_EOL;

