<?php
/**
 * The first website message waits for name + email: the email check (typos,
 * throwaway inboxes, domains without mail) and the two-step code.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Services\EmailCheck;
use Zaplane\Modules\Inbox\Settings;

// zt_ok takes the label first.
function zt_ok_rev( $pass, string $label ): void {
	zt_ok( $label, $pass );
}

zt_run( function () {
	foreach ([['me@gmail.com',true],['me@gmial.com',false],['x@mailinator.com',false],['x@no-such-domain-zq9x7.com',false],['bad',false],['a@kodezen.com',true]] as [$e,$want]) {
	  $r=EmailCheck::check($e); zt_ok_rev($r['ok']===$want,"check $e ".json_encode($r));
	}
	zt_ok_rev(( EmailCheck::check('me@gmial.com')['suggestion']??'')==='me@gmail.com','suggestion');

	$opt=get_option(Settings::OPTION,[]);
	$set=function($w)use($opt){ $o=$opt; $o['widget']=array_merge($o['widget']??[],['enabled'=>true,'ask_email'=>true],$w); update_option(Settings::OPTION,$o); };
	$call=function($method,$route,$params,$token=''){ $r=new WP_REST_Request($method,'/zaplane/v1/inbox/widget/'.$route); if($token)$r->set_header('X-Zaplane-Visitor',$token); foreach($params as $k=>$v)$r->set_param($k,$v); $res=rest_do_request($r); return [$res->get_status(),$res->get_data()]; };
	wp_set_current_user(0);

	// Mode: real check, no code
	$set(['check_email'=>true,'verify_email'=>false]);
	[$s,$d]=$call('POST','session',[]); $tok=$d['token']; zt_ok_rev($d['needs_contact']===true,'session needs_contact');
	[$s,$d]=$call('POST','messages',['body'=>'hello'],$tok); zt_ok_rev($s===400 && ($d['data']['need']??'')==='details','no details -> need details '.json_encode($d));
	[$s,$d]=$call('POST','contact',['prechat'=>true,'name'=>'T','email'=>'t@gmial.com'],$tok); zt_ok_rev($s===400 && ($d['data']['suggestion']??'')==='t@gmail.com','prechat typo');
	[$s,$d]=$call('POST','contact',['prechat'=>true,'name'=>'T','email'=>'t@gmail.com'],$tok); zt_ok_rev($s===200 && $d['status']==='ok','prechat ok');
	[$s,$d]=$call('POST','messages',['body'=>'hello','name'=>'T','email'=>'t@gmial.com'],$tok); zt_ok_rev($s===400,'message with typo refused');
	[$s,$d]=$call('POST','messages',['body'=>'hello','name'=>'Tess','email'=>'tess.zq@gmail.com'],$tok); zt_ok_rev($s===200,'message sent '.json_encode($d));
	[$s,$d]=$call('GET','messages',[],$tok); zt_ok_rev($d['needs_contact']===false,'after: no longer needs');
	[$s,$d]=$call('POST','messages',['body'=>'second'],$tok); zt_ok_rev($s===200,'second message w/o details');

	// Mode: code
	$set(['check_email'=>true,'verify_email'=>true]);
	$sent=null; add_filter('pre_wp_mail',function($n,$a)use(&$sent){ if(preg_match('/(\d{6})/',$a['message'],$m))$sent=$m[1]; return true; },10,2);
	[$s,$d]=$call('POST','session',[]); $tok=$d['token'];
	[$s,$d]=$call('POST','contact',['prechat'=>true,'name'=>'C','email'=>'code.zq@gmail.com'],$tok); zt_ok_rev($d['status']==='code_sent' && $sent,'code sent');
	[$s,$d]=$call('POST','messages',['body'=>'hi','name'=>'C','email'=>'code.zq@gmail.com'],$tok); zt_ok_rev(($d['data']['need']??'')==='code','needs code');
	[$s,$d]=$call('POST','messages',['body'=>'hi','name'=>'C','email'=>'code.zq@gmail.com','code'=>'000000'],$tok); zt_ok_rev($s===400 && ($d['data']['need']??'')==='code','wrong code');
	[$s,$d]=$call('POST','messages',['body'=>'hi','name'=>'C','email'=>'other.zq@gmail.com','code'=>$sent],$tok); zt_ok_rev($s===400,'code for other email refused');
	[$s,$d]=$call('POST','messages',['body'=>'hi','name'=>'C','email'=>'code.zq@gmail.com','code'=>$sent],$tok); zt_ok_rev($s===200,'right code sends');
	$conv=Zaplane\Modules\Inbox\Services\Visitors::conversation(explode('.',$tok)[0]);
	$c=Zaplane\Modules\Inbox\Models\Contact::where('id',(int)$conv->contact_id)->fresh()->first();
	zt_ok_rev(Zaplane\Modules\Inbox\Services\VisitorContact::verified($c),'contact verified');
	[$s,$d]=$call('GET','messages',[],$tok); zt_ok_rev($d['needs_contact']===false,'code: no longer needs');

	// ask_email off: no gate
	$set(['ask_email'=>false]);
	[$s,$d]=$call('POST','session',[]); $tok=$d['token']; zt_ok_rev($d['needs_contact']===false,'off: no gate');
	[$s,$d]=$call('POST','messages',['body'=>'free'],$tok); zt_ok_rev($s===200,'off: sends');
} );
