<?php

require_once __DIR__.'/../libs/discover.inc';

$list=DiscoverNetwork(3,1,'192.168.112.100');
var_export($list);
exit;


$d=DiscoverDevice('http://192.168.112.61:8000/serverxml.xml,http://192.168.112.61:8080/description.xml',OPT_USE_CACHE| OPT_MINIMIZED | OPT_EVENTS );
foreach($d[D_SERVICES] as $sn=>$s){
	if(!empty($s[S_EVENTS]))
		echo "$sn => ".$s[S_EVENTS]."\n";
	;
}
exit;
echo time();exit ();
function convertLocales(){
	$files=['Configurator','FritzCallmon','FritzHomeAuto','FritzLog','FritzStatus',
			'GenericRpc','MediaRpc','SamsungTVRemote','',''];

	$p='C:\IP-Symcon\modules\RpcTools\%s\locale.json';
	foreach($files as $part){
		if(empty($part))continue;
		$file=sprintf($p,$part);
		if(file_exists($file)){
			echo "Convert : $file\n";
			$a=json_decode(file_get_contents($file),true);
			file_put_contents($file, json_encode($a,JSON_PRETTY_PRINT));
		}
		
	}

}
// convertLocales();

echo time();

exit;
$s=1388201;

$m=floor($s/60);
$s-=$m*60;

$h=floor($m/60);
$m-=$h*60;

$t=floor($h/24);
$h-=$t*24;

echo "t: $t h:$h  m:$m s:$s\n";

exit;
// exit(date('d.m.Y  h:i:s',$t));


$options=[
	['value'=>1,'options'=>[  ]]	
];
$j=json_encode($options);
$j=preg_replace('/options(.*)\[[ ]*\]/i', 'options:[1,2,3]', $j);
echo $j.PHP_EOL;

// $options=json_decode($j,true);



// print_r($options);
exit;


$j=json_decode('[
	{"name":"Menü","ident":"MENÜ", "enabled":true},
	{"name":"Menü","ident":"MENÜ", "enabled":true}
]',true);
print_r($j);

?>