<?php 
if(!defined('RPCTOOLS'))define('RPCTOOLS', __DIR__);
ini_set('max_execution_time',"180");
require_once 'rpc_constants.inc';
	
const 
	PropFunctions=[PROP_VOLUME_CONTROL=>['GetVolume','SetVolume'],PROP_MUTE_CONTROL=>['GetMute','SetMute'],PROP_TREBLE_CONTROL=>['GetTreble','SetTreble'],PROP_BASS_CONTROL=>['GetBass','SetBass'],PROP_LOUDNESS_CONTROL=>['GetLoudness','SetLoudness'],PROP_BRIGHTNESS_CONTROL=>['GetBrightness','SetBrightness'],PROP_CONTRAST_CONTROL=>['GetContrast','SetContrast'],PROP_SHARPNESS_CONTROL=>['GetSharpness','SetSharpness'],PROP_COLOR_CONTROL=>['GetColor','SetColor'] ,PROP_SOURCE_CONTROL=>['GetSource','SetSource'],PROP_PLAY_CONTROL=>['Stop','Play','Pause','Next','Previous','Seek','GetTransportInfo'],PROP_CONTENT_BROWSER=>['Browse','Search']];

	
	
/**
 * @param number $timeout
 * @param number $retrys
 * @param string $bind_to_my_ip
 * @param number $SenderID
 * @return array|null
 */
function DiscoverNetwork($timeout=5, $retrys=1 , $bind_to_my_ip='', $SenderID=0){
	static 	$discover_user_agent = 'MacOSX/10.8.2 UPnP/1.1 PHP-UPnP/0.0.1a';
	static $running = false;
	if($running)return;
	$running=true;
	if(function_exists('IPS_LogMessage')){
		$debug=function($msg, $toLog=false)use($SenderID){
			if($SenderID && !$toLog)IPS_SendDebug($SenderID, 'RpcTools Discover', $msg, 0);
			else IPS_LogMessage('RpcTools Discover', $msg);
		};
	}else $debug=function($msg){};
	$debug('Discover network starting...',true);	
	if(empty($timeout)||$timeout<2)$timeout=2;else if ($timeout>10&&$retrys>1)$timeout=5;
	if(empty($retrys)||$retrys<1)$retrys=1;
	
	$request = "M-SEARCH * HTTP/1.1\r\nHOST: 239.255.255.250:1900\r\nMAN: \"ssdp:discover\"\r\nMX: $timeout\r\nST: ssdp:all\r\nUSER-AGENT: ".$discover_user_agent."\r\n\r\n";
	$find=[];
	try {
		for($j=0;$j<$retrys;$j++){
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if($bind_to_my_ip)socket_bind($socket, $bind_to_my_ip);
			socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true);
			socket_sendto($socket, $request, strlen($request), 0, '239.255.255.250', 1900);
			socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>$timeout, 'usec'=>'0'));
			$read = [$socket];
			$write = $except = [];
			$name = $port = $m=null;
			$response = '';
			while (socket_select($read, $write, $except, $timeout) && $read) {
				socket_recvfrom($socket, $response, 2048, null, $name, $port);
				if(is_null($response) || !preg_match('/HTTP\/.+200 OK/',$response)) continue;
				if(preg_match('/SERVER:(.*)/i',$response,$m))$server=trim($m[1]);else continue;
				if(preg_match('/LOCATION:(.*)/i',$response,$m))$url=trim($m[1]);else continue;
				$usn=preg_match('/USN:(.*)/i',$response,$m)?preg_replace('/::.*/','',trim($m[1])):'';
				if(empty($find[$name])){
					$find[$name]=['server'=>$server,'host'=>parse_url($url,PHP_URL_SCHEME).'://'.$name,'port'=>parse_url($url,PHP_URL_PORT), 'usn'=>$usn,'urls'=>[$url]];
					$debug("found $name => $server");
				}
				else if(!in_array($url, $find[$name]['urls'])){
					$find[$name]['urls'][]=$url;
					$debug("add url $url on $name");
				}
			}
			socket_close($socket);
		}
	}catch(Exception $e){
		
	}
	$debug('Discover finishd. Found '.count($find). ' network devices',true);	
	$running=false;
	return array_values($find);
}
/**
 * @brief Cleanup the Discover Cache
 */
function DiscoverCleanUpCache(){
	$f = scandir(RPCTOOLS.'/discovercache/');
	if($f)foreach($f as $n)if($n[0]!='.')unlink(RPCTOOLS."/discovercache/$n");
}
/**
 * @param string|array $urls
 * @param number $options
 * @param array $filter
 * @return array
 */
function DiscoverDevice($urls, $options=1, $filter=[]){
	$cache=$loadXml=$addStateVariable=$importFunctions=$importDevice=null;
	$includeStateVars=($options&OPT_MINIMIZED)?['Channel','Speed','Unit','CurrentTransportState','DesiredBass','DesiredTreble','DesiredVolume']:null;
	if(!is_array($urls))$urls=explode(',', $urls);
	$device=[D_HOST=>parse_url($urls[0],PHP_URL_SCHEME).'://'.parse_url($urls[0],PHP_URL_HOST),D_PROPS=>0, D_NAME=>'',	D_INFO=>'',	D_TYPE=>'',D_URLS=>'',D_OPTIONS=>0,  D_SERVICES=>[], ID_VARTABLE=>[], 'infos'=>[]];
	if($device[D_HOST]=='://')$device[D_HOST]='';
	if(is_array($filter)&&count($filter)>0){
		foreach($filter as $id=>$v){
			unset($filter[$id]);
			if(strpos($v,'.')===false)$v='*.'.$v;
			list($sn,$fn)=explode('.',$v);
			if(empty($filter[$sn]))$filter[$sn]=[];
			if(!in_array($fn, $filter[$sn]))$filter[$sn][]=$fn;
		}
	}else $filter=null;
	$isValidFilter=function($sn,$fn)use($filter){
		if(!$filter)return true;
		$m=null;$shortsn=preg_match('/(.*)\d/',$sn,$m)?$m[1]:$sn; 
		foreach($filter as $fsn=>$ffn){
			if($fsn=='*' || strcasecmp($fsn,$sn)==0 || strcasecmp($fsn,$shortsn)==0){
				foreach($ffn as $fff){
					if($fff=='*'|| strcasecmp($fff,$fn)==0){
						return true;	
					}
				}
			}
		}
		return false;
	};
	$getFunctionProp=function ($fn){
// 		static $propFunctions=[PROP_VOLUME_CONTROL=>['GetVolume','SetVolume'],PROP_MUTE_CONTROL=>['GetMute','SetMute'],PROP_TREBLE_CONTROL=>['GetTreble','SetTreble'],PROP_BASS_CONTROL=>['GetBass','SetBass'],PROP_LOUDNESS_CONTROL=>['GetLoudness','SetLoudness'],PROP_BRIGHTNESS_CONTROL=>['GetBrightness','SetBrightness'],PROP_CONTRAST_CONTROL=>['GetContrast','SetContrast'],PROP_SHARPNESS_CONTROL=>['GetSharpness','SetSharpness'],PROP_COLOR_CONTROL=>['GetColor','SetColor'] ,PROP_SOURCE_CONTROL=>['GetSource','SetSource'],PROP_PLAY_CONTROL=>['Stop','Play','Pause','Next','Previous','Seek','GetTransportInfo'],PROP_CONTENT_BROWSER=>['Browse','Search']];
		foreach(PropFunctions as $prop=>$propfuncs)if($ok=in_array($fn, $propfuncs))break;
		return $ok?$prop:false;
	};
	$loadXml=function($name, $force=false)use(&$cache){
		if(stripos($name,'http')===false){
			if($name[0]==':')$name=substr($name,2);
 			$name=RPCTOOLS.'/predefined/'.$name;
			return @simplexml_load_file($name);
		}
		if(is_null($cache))return @simplexml_load_file($name);
		return $cache->Load($name,$force);
	};
	$convertVar=function($xml_var)use($options){
			$var=json_decode(json_encode($xml_var),true);
			unset($var['@attributes']);
			if(isset($var['dataType'])){
				$var[V_TYPE]=$var['dataType'];
				unset($var['dataType']);
			}
			if(!empty($var['allowedValueRange'])){
				foreach(['minimum'=>V_MIN,'maximum'=>V_MAX,'step'=>V_STEP] as $k=>$v){
					if(isset($var['allowedValueRange'][$k]))$var[$v]=$var['allowedValueRange'][$k];
				}
				unset($var['allowedValueRange']);
			}
			if(!empty($var['allowedValueList']))  {
				$values=$var['allowedValueList']['allowedValue'];
				if(!is_array($values))$values=[$values];
				$var[V_ALLOWED]=$values;
				unset($var['allowedValueList']);
			}
			if(isset($var['defaultValue'])){
				$var[V_DEFAULT]=$var['defaultValue'];
				unset($var['defaultValue']);
			}
			unset($var['name']);
// 			if(empty($var['defaultValue']) && !empty($var['allowed']))
// 				$var['defaultValue']=$var['allowed'][0];
			
			if(isset($var[V_DEFAULT])){
				if($var[V_TYPE]!='string'){
					if(is_numeric($var[V_DEFAULT]))$var[V_DEFAULT]=is_float($var[V_DEFAULT])?floatval($var[V_DEFAULT]):intval($var[V_DEFAULT]);
					if($var[V_TYPE]!='boolean')$var[V_DEFAULT]=$var[V_DEFAULT]=='false'?false:(bool)$var[V_DEFAULT];
				}
			}

			if($options & OPT_SMALCONFIG){
				unset($var[V_TYPE]);
				if(count($var)==0) $var=null; 
			}
			return $var;		
	};
	
	$getStateVar=function($xml,$var_name)use($options,$convertVar){
		$r=null;
		foreach($xml->serviceStateTable->stateVariable as $var){
			if(strcasecmp((string)$var->name, $var_name)==0){
				$r=$convertVar($var);
				break;
			}
		}
 		return $r;
	};
	$addStateVariable=function($var_name,$var, &$curService, $force=false)use(&$device,$includeStateVars){
		if(!$force && is_array($includeStateVars) && !in_array($var_name, $includeStateVars))return;
		if(empty($device[ID_VARTABLE][$var_name])){
			$device[ID_VARTABLE][$var_name]=$var;
		}else {
			if($device[ID_VARTABLE][$var_name]!=$var){
				if(empty($curService[ID_VARTABLE][$var_name])){
// 					echo "duplicate $var_name add to service\n";
					$curService[ID_VARTABLE][$var_name]=$var;	
				}else if($curService[ID_VARTABLE][$var_name]!=$var) {
// 					echo "new duplicate $var_name\n";
// 					echo var_export($var,true)."\n".var_export($curService[ID_VARTABLE][$var_name],true);
	 				return null;
				}
			}
		}
	};
	$importEvents=function($xml, &$curService)use(&$device,$addStateVariable,$convertVar){
		if(empty($xml->serviceStateTable->stateVariable))return;
		$evars=[];
		foreach($xml->serviceStateTable->stateVariable as $var){
			if($var->attributes()['sendEvents']=='yes'){
				$name=trim((string)$var->name);
				if(!in_array($name,$evars)){
// 					echo "Send Event : $var->name\n";	
					$evars[]=$name;
					$addStateVariable( $name,$convertVar($var), $curService,true);
				}
			}
		}
		if(count($evars)>0){
			$curService[S_EVENTS]=implode(',',$evars);
			$device[D_PROPS]=$device[D_PROPS]|PROP_EVENTS;
		}
	};
	
	$importFunctions=function($xml, &$curService,$sn)use(&$device,$options,$addStateVariable,$getStateVar,$getFunctionProp,$isValidFilter,$importEvents){
		if(empty($xml->actionList))return null;
		$funcs=null;
		foreach($xml->actionList->action as $action){
			$n=(string)$action->name;
			
			if($prop=$getFunctionProp($n))$device[D_PROPS]=$device[D_PROPS]|$prop;
			if( $options&OPT_PROPS_ONLY && !$prop)continue;
			else if(!$isValidFilter($sn,$n))continue;
			$r=[];
			if($action->argumentList)foreach($action->argumentList->argument as $arg ){
				$var=$getStateVar($xml,(string)$arg->relatedStateVariable);
				$name=(string)$arg->name;
				if((string)$arg->direction=='in')$r[]=$name;
				if($var)$addStateVariable($name,$var,$curService);
			}
			$funcs[$n]=implode(',',$r);
			if($prop=$getFunctionProp($n))$device[D_PROPS]=$device[D_PROPS]|$prop;
		}
		$curService[S_FUNCS]=$funcs;
		if($funcs && count($funcs)>0 && ($options&OPT_EVENTS))$importEvents($xml,$curService);
		
	};
	$importDevice=function($xml,$port)use(&$device,&$importDevice,$options,$loadXml,$importFunctions){
		$d=null;
		if(!empty($xml->device))$d=$xml->device;
		else if(!empty($xml->serviceList))$d=$xml;
		if(!$d)return;
		foreach($d[0] as $k=>$v)if(($v=trim((string)$v)) && empty($device['infos'][$k]))$device['infos'][$k]=$v;
		if($d->serviceList){
			foreach($d->serviceList->service as $service){
				$s=explode(':',(string)$service->serviceId); $s=array_pop($s);
				if(empty($device[D_SERVICES][$s])){
					$device[D_SERVICES][$s][S_PORT]=isset($service->connectionPort)?(int)$service->connectionPort:$port;
					$device[D_SERVICES][$s][S_CON]=isset($service->connectionType)?(int)$service->connectionType:0;
					if(isset($service->lowerNames))$device[D_SERVICES][$s][S_LOWERNAMES]=true;
					$curService=&$device[D_SERVICES][$s];
					$curService[S_CURL]=trim((string)$service->controlURL);
					if($options&OPT_EVENTS)$curService[S_EURL]=trim((string)$service->eventSubURL);
  					$urn=trim((string)$service->serviceType);
 					if($urn!='urn:schemas-upnp-org:service:'.$s.':1')$curService[S_URN]=$urn;
					$scpd=trim((string)$service->SCPDURL);
					if(!empty($scpd)){
						if($scpd[0]!='/')$scpd='/'.$scpd;
						$scpd=$port?$device[D_HOST].':'.$port.$scpd:$scpd ;
						if($x=$loadXml($scpd))$importFunctions($x,$curService,$s);
					}
					if(empty($curService[S_FUNCS])){
						unset($device[D_SERVICES][$s]);
					}
				}
			}
		}
		if(!empty($d->deviceList)){
			foreach ($d->deviceList->device as $d){
				$importDevice($d,$port);
			}
		}
		
	};
	$finishProcess=function()use(&$device){
		if(empty($device[D_HOST])){
			foreach($device[D_URLS] as $url){
				$p=parse_url($url);
				if(!empty($p['scheme']) && !empty($p['host'])){
					$device[D_HOST]=$p['scheme'].'://'.$p['host'];
					break;
				}
			}
		}
		
		$device[D_URLS]=implode(',', $device[D_URLS]);
		foreach(['friendlyName','modelNumber'] as $k){
			if(!empty($device['infos'][$k])){
				$k=$device['infos'][$k];
				if(!$device[D_NAME] || (strlen($device[D_NAME])+strlen($k)<120))$device[D_NAME].=$device[D_NAME]?' '.$k:$k;
			}
		}
		foreach(['manufacturer','modelDescription','modelName'] as $k){
			if(!empty($device['infos'][$k])){
				$k=$device['infos'][$k];
					if(!$device[D_INFO]||(strlen($device[D_INFO])+strlen($k)<200))$device[D_INFO].=$device[D_INFO]?' '.$k:$k;
			}
		}
		if(!empty($device['infos']['deviceType'])){
			$s=explode(':',$device['infos']['deviceType']);while(($p=array_pop($s))&&is_numeric($p));
			$device[D_TYPE]=$p;		
		}
		if(preg_match('/homematic/i', $device[D_INFO])){
			$device[D_TYPE]='SmartHome';
		}
		else if(preg_match('/enigma/i', $device[D_INFO])){
			$device[D_TYPE]='MediaBox';
		}
		if(preg_match('/fritz/i', $device[D_INFO])){
			$device[D_OPTIONS]=$device[D_OPTIONS]|OPT_REQUIRE_LOGIN;
		}
		$m=null;	
		if(preg_match('/^[ \d.\-\+]*/',$device[D_NAME],$m)){
			$device[D_NAME]=str_replace($m[0], '', $device[D_NAME]);
		}
		unset($device['infos']);
	};
	if($options&OPT_USE_CACHE)$cache=new Cache();
	
	$device[D_URLS]=[];
	foreach($urls as $url){
		if($xml=$loadXml($url)){
			$importDevice($xml,parse_url($url,PHP_URL_PORT));
			$device[D_URLS][]=str_replace($device[D_HOST], '', $url);
		}
	}
	$finishProcess();
	if($options&OPT_SAVECONVIG && count($device[D_SERVICES])>0){
		DiscoverSaveDevice($device);
	}
	return $device;
}
/**
 * @param array $device
 * @param boolean $override
 * @param string $filename
 * @return string The Filename of saved Device
 */
function DiscoverSaveDevice($device, $override=true, $filename=''){
	$file=RPCTOOLS . '/rpcconfig/';
	if(!is_dir($file))mkdir($file,755);
	$file.=$filename?$filename:str_replace([':','\\','/','?','*'],'', $device[D_NAME]).'.rpc';
	if(!file_exists($file) || $override) file_put_contents($file, json_encode($device));
	return $file;
}
/**
 * @param array $device
 * @param string $filename
 */
function DiscoverDeleteDevice($device, $filename=''){
	$file=RPCTOOLS . '/rpcconfig/';
	$file.=$filename?$filename:str_replace([':','\\','/','?','*'],'', $device[D_NAME]).'.rpc';
	if(file_exists($file))unlink($file);
}



/**
 * @author Xavier
 *
 */
class Cache {
	/**
	 * $_cachePath string 
	 */
	protected $_cachePath = RPCTOOLS . '/discovercache/';
	function __construct(){
		@mkdir($this->_cachePath,755);		
	}
	/**
	 * @param string $Name
	 * @param boolean $force
	 * @return NULL|SimpleXMLElement
	 */
	public function Load($Name, $force=false){
		$fn = $this->_buildCacheFilename($Name);
		if($force===true || !file_exists($fn)){
			$content=file_get_contents($Name);
			file_put_contents($fn, $content);
			return empty($content)?null:@simplexml_load_string($content);
		}else $Name=$fn;
		return @simplexml_load_file($Name);
	}
	/**
	 * @param string $filename
	 * @param string $defExt
	 * @return string
	 */
	private function _buildCacheFilename($filename, $defExt='xml'){
		return $this->_cachePath . str_ireplace(['.xml',':','//','/','.'],['','_','','_','-'], $filename).'.'.$defExt;
	}
}

?>