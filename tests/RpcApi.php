<?php 
require_once './../libs/rpc_api.inc';
require_once './../libs/discover.inc';




// $l=DiscoverNetwork1();
// foreach($l as $i=>$v)$l[$i]['urls']=implode(',',$v['urls']);
// var_export($l);
// exit;


$options = OPT_USE_CACHE | OPT_MINIMIZED |	OPT_PROPS_ONLY|OPT_EVENTS;
// $options = OPT_USE_CACHE | OPT_SMALCONFIG |	OPT_PROPS_ONLY;
// $options = OPT_USE_CACHE | OPT_SMALCONFIG ;
// $options = OPT_USE_CACHE;
//$r=DiscoverDevice('http://192.168.112.54:1400/xml/device_description.xml',$options);


$filter=null;
$r=DiscoverDevice('http://192.168.112.61:8000/serverxml.xml,http://192.168.112.61:8080/description.xml',$options,$filter);
$api=new RpcApi($r);
print_r($api->DeviceInfo());
$r=$api->GetVolume();


// $r=DiscoverDevice('enigma2.xml',OPT_SMALCONFIG);
// $api=new RpcApi($r,'192.168.112.65');
// $r=$api->About();

// $r=DiscoverDevice('fritzboxAHA.xml',OPT_SMALCONFIG);
// $api=new FritzApi($r,'192.168.112.254');
// $api->sid='xaver';
// // var_export($r);
// // echo "\n";
// $r=$api->GetSwitchList();
// exit;
// $r=DiscoverDevice('http://192.168.112.60:7676/smp_26_,http://192.168.112.60:7676/smp_14_,http://192.168.112.60:7676/smp_6_,http://192.168.112.60:7676/smp_2_',$options);


// $r=DiscoverDevice('http://192.168.112.15/upnp/basic_dev.cgi,homematic.xml',OPT_USE_CACHE|OPT_SMALCONFIG);
// $api=new RpcApi($r,'192.168.112.15');
// $r=$api->DeviceInfo();

//$r=DiscoverDevice('http://192.168.112.254:49000/tr64desc.xml',OPT_USE_CACHE);
//    $api=new RpcApi($r,'192.168.112.254','x.bauer','bad!boy85');
// $r=$api->GetPhonebookList();


// if(!empty($r))echo "size : ".strlen(json_encode($r))." \n";
//    $api->SetErrorHandler(function ($msg,$code){
//    	echo "Error Handler $msg, $code\n";
//   });
//  $r=$api->_getFunctionParams('GetTransportInfo');
//  $r=$api->_getFunctionParam('CurrentTransportState');

var_export($r);

?>