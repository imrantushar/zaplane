<?php
require __DIR__.'/zencommunity-cli-bootstrap.php';
$c=\Zaplane\Integrations\Zencommunity::class;
$failed=[];
foreach($c::get_dynamic_queries() as $name=>$fn){
 try{$rows=call_user_func($fn,[]);
 $ok=is_array($rows)&&array_reduce($rows,static fn($carry,$row)=>$carry&&is_array($row)&&isset($row['value'],$row['label']),true);
 echo ($ok?'PASS ':'FAIL ').$name.' '.count($rows).PHP_EOL;
 if(!$ok)$failed[]=$name;
 }catch(\Throwable $e){echo 'FAIL '.$name.' '.$e->getMessage().PHP_EOL;$failed[]=$name;}
}
echo 'SELECTORS '.wp_json_encode(['total'=>count($c::get_dynamic_queries()),'failed'=>$failed]).PHP_EOL;
if($failed)exit(1);
