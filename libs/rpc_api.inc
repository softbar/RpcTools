<?php 
if(!defined('RPCTOOLS'))define('RPCTOOLS', __DIR__);
require_once 'rpc_constants.inc';


class RpcApi {
	private $device=null;
	private $user='';
	private $pass='';
	private $ErrorHandler=null;
	private $lastError='';
	private $UserVars = [];
	function __construct($device_cfg, $host='', $user='',$pass='') {
		if($device_cfg && is_string($device_cfg)){
			if($device_cfg[0]=='{'||$device_cfg[0]=='[')
				$device_cfg=json_decode($device_cfg,true);
			elseif (file_exists($device_cfg))
				$device_cfg=json_decode(file_get_contents($device_cfg),true);
			elseif (file_exists(RPCTOOLS."/rpcconfig/$device_cfg"))
				$device_cfg=json_decode(file_get_contents(RPCTOOLS."/rpcconfig/$device_cfg"),true);
			else $device_cfg=null;
		}
		if(!is_array($device_cfg))throw new Exception((empty($device_cfg)?'Empty':'Invalid')." Device Config",E_USER_ERROR);
		$this->device=$device_cfg;
		if($host){
			if(stripos($host,'http')===false)$host='http://'.$host;
			$this->device[D_HOST]=$host;
		}
		$this->user=$user;
		$this->pass=$pass;
	}
	function __call($fn,$args){
		$this->lastError='';
		if(strpos($fn,'.')){
			list($sn,$fn)=explode('.',$fn);
		}
		if(!$this->_validateFunctionName($fn, $sn)){
			if(($r=$this->_callInternal($fn, $args, $sn))!==null)
				return $r;
			else return $this->error("Function $fn not found");
		}
		if(empty($this->device[D_HOST]))return $this->error("Host missing",E_USER_ERROR);
		$r=null;
		try {
			switch($this->device[D_SERVICES][$sn][S_CON]){
				case 1: $r=$this->_callRpc($fn,$args,$sn,false);break;
				case 2: $r=$this->_callUrl($fn,$args,$sn);break;
				case 3: $r=$this->_callRpc($fn,$args,$sn,true);break;
				default: $r=$this->_callSoap($fn,$args,$sn);
			}
		} catch(Exception $e){
			$msg=$e->getMessage();
			$msg.=' :: ARGS=> '.str_replace(["\r\n"],'',var_export($args,true));
			$msg.=" :: LOGIN=> $this->user : $this->pass";
			$this->error($msg,$e->getCode());
			throw $e;
		}
		return $r;
	}
	function __get($n){
		switch(strtolower($n)){
			case 'name': $r=$this->device[D_NAME];break;
			case 'info': $r=$this->device[D_INFO];break;
			case 'props': $r=$this->device[D_PROPS];break;
			case 'host': $r=$this->device[D_HOST];break;
			case 'urls': $r=$this->device[D_URLS];break;
			case 'port': $r=((count($this->device[D_SERVICES])>0) &&  $v=current($this->device[D_SERVICES]) )?$v[S_PORT]:null;break;	
				
			default: 
				echo "Lookup Uservars\n";
				$r=array_key_exists($n, $this->UserVars)? $this->UserVars[$n]:null;
		}
		return $r;
	}
	function __set($n,$v){
// 		echo "Set Uservar['$n']\n";
		$this->UserVars[$n]=$v;
	}
	function SetErrorHandler($func){
		$this->ErrorHandler=$func;
	}
	public function LastError(){
		return $this->lastError;
	}
	public function FunctionExists($name, $serviceName=''){
		return $this->_validateFunctionName($name, $serviceName);
	}
	public function FunctionList($a1=true,$a2=true){
		$r=[''=>[
			'GetPlayState'=>'InstanceID',
			'SetPlayState'=>'InstanceID,PlayState',
			'FunctionList'=>'',
			'FunctionExists'=>'FunctionName,ServiceName',
			'DeviceInfo'=>'',
		]];	
		foreach ($this->device[D_SERVICES] as $sn=>$s)$r[$sn]=$s[S_FUNCS];
		if($a1==true){
			$nr=[];
			foreach ($r as $v)$nr=array_merge($nr,$v);
			$r=array_keys($nr);
		}
		if($a2==true){
			if($a1==true){
				sort($r);
			}else {
				ksort($r);
				foreach ($r as &$v)ksort($v);
			}
		}
		return $r;
	}
	public function DeviceInfo(){
		$n=[];$fc=$fe=0;
		foreach(PROP_NAMES as $prop=>$name){
			if($this->device[D_PROPS]&$prop)$n[]=ucfirst(strtolower($name));
		}
		foreach ($this->device[D_SERVICES] as $s){
			$fc+=count($s[S_FUNCS]);
			if(!empty($s[S_EVENTS]))$fe+=count(explode(',',$s[S_EVENTS]));
		}
		$r=[
			'name'=>$this->device[D_NAME],
			'type'=>$this->device[D_TYPE],
			'info'=>$this->device[D_INFO],
			'props'=>implode(',',$n),
			'services'=>count($this->device[D_SERVICES]),
			'events'=>$fe,	
			'methods'=>$fc,
			'urls'=>$this->device[D_URLS]					
		];
		return $r;		
	}
	protected function getDefaultValue($name,$serviceName) {
		$lname=strtolower($name);
		if($lname=='instanceid')return 0;
		if($def=$this->_getFunctionParam($name,$serviceName)){
			if(isset($def[V_DEFAULT]))return $def[V_DEFAULT];
			if(isset($def[V_ALLOWED]))return $def[V_ALLOWED][0];
		}
		if(array_key_exists($lname,$this->UserVars))return $this->UserVars[$lname];
		switch($lname){
			case 'channel': return 'Master';
			case 'instanceid':return 0;
			case 'speed': return 1;
			case 'unit' : return 'TRACK_NR';
		}
	}
	
	private function error($msg,$code=E_USER_ERROR){
		$this->lastError="($code) $msg";
		if($this->ErrorHandler)
			call_user_func_array($this->ErrorHandler, [$msg,$code]);
		else echo $this->lastError ."\n";
		return null;
	}
	private function _validateFunctionName(&$name, &$serviceName){
		if(empty($serviceName)||empty($this->device[D_SERVICES][$serviceName])){
			foreach($this->device[D_SERVICES] as $sn=>$s)
				if(!empty($s[S_FUNCS][$name])){$serviceName=$sn;return true;}
			foreach($this->device[D_SERVICES] as $sn=>$s)
				foreach(array_keys($s[S_FUNCS]) as $fn)if(strcasecmp($fn, $name)==0){$name=$fn;$serviceName=$sn; return true;}
			return false;
		}
		if(empty($this->device[D_SERVICES][$serviceName]))return;
		$d=&$this->device[D_SERVICES][$serviceName];
		if(!empty($d[S_FUNCS][$name]))return true;
		foreach(array_keys($d[S_FUNCS]) as $fn)if(strcasecmp($fn, $name)==0){$name=$fn;return true;}
	}
	private function _prepareArguments(&$arguments, $func_keys, $sn=''){
		while (count($arguments) > count($func_keys))array_pop($arguments);
	    foreach($arguments as $key=>$value){
	    	if(is_numeric($key)){
	    		unset($arguments[$key]);
	    		$arguments[$func_keys[$key]]=$value;
	    	}
	    }
	    foreach($func_keys as $key){
	    	if(!isset($arguments[$key])||$arguments[$key]===null)
	    		$arguments[$key]=$this->getDefaultValue($key, $sn);
	    }
	}
	private function _getFunctionParam($name,$serviceName=''){
		if(!empty($this->device[D_SERVICES][$serviceName][ID_VARTABLE][$name]))
			return $this->device[D_SERVICES][$serviceName][ID_VARTABLE][$name];
		else if(!empty($this->device[ID_VARTABLE][$name]))
			return $this->device[ID_VARTABLE][$name];
	}
/*
 	function _getFunctionParams($name,$serviceName=''){
		return $this->_validateFunctionName($name, $serviceName)?
			$this->device[D_SERVICES][$serviceName][S_FUNCS][$name]:false;
	}
*/	
	private function _callInternal($function,$arguments,$serviceName){
		$fn=strtolower($function);	
		if($fn=='functionexists'||$fn=='exists'){
			return call_user_func_array([$this,'FunctionExists'], $arguments);
		}
		elseif($fn=='deviceinfo'||$fn=='info'){
			return call_user_func_array([$this,'DeviceInfo'], $arguments);
		}else
		if($fn=='listmethods'||$fn=='listfunctions'||$fn=='functionlist'){
			return call_user_func_array([$this,'FunctionList'], $arguments);
		}else
		if($fn=='getplaystate'){
			if(	($r=$this->GetTransportInfo()) && 
				($p=$this->_getFunctionParam('CurrentTransportState')) && 
				(!empty($p[V_ALLOWED]))){
 				$i=array_search($r['CurrentTransportState'], $p[V_ALLOWED]);
// 				echo "r: ".var_export($r,true)."\np:".var_export($p,true)."\ni:$i\n";
				if($i==3)$i=2;
				return $i;
			}else return 0;
		}else
		if ($fn=='setplaystate') {
			$id=count($arguments)==2?array_shift($arguments):0;
			switch($arguments[0]){
				case 0: $ok=$this->Stop($id);break;
				case 1: $ok=$this->Play($id);break;
				case 2: $ok=$this->Pause($id);break;
				case 3: if($ok=$this->Next($id))$arguments[0]=1;break;
				case 4: if($ok=$this->Previous($id))$arguments[0]=1;break;
				default: $ok=null;
			}
			return $ok?$arguments[0]:false;			
		}
	}
	private function _callSoap($function,$arguments,$serviceName){
		$url=$this->device[D_HOST].':';
		$s=$this->device[D_SERVICES][$serviceName];
		$url.=$s[S_PORT].$s[S_CURL];
		$urn=empty($s[S_URN])?"urn:schemas-upnp-org:service:$serviceName:1":$s[S_URN];
		$params=array('location'=> $url,'uri'=> $urn,'noroot'=> true,'exceptions'=> false,'trace'=> true);
		if($this->user)$params['login']=$this->user;
		if($this->pass)$params['password']=$this->pass;
		$client = new SoapClient( null,$params);
		$this->_prepareArguments($arguments,  empty($s[S_FUNCS][$function])?[]:explode(',',$s[S_FUNCS][$function]),$serviceName);
		$params=array(); 
	    foreach($arguments as $key=>$value)$params[]=new SoapParam($value, $key);
	    $response=$client->__soapCall($function,$params);
	    if(is_soap_fault($response))return $this->error($response->faultstring,$response->faultcode);
	 	return is_null($response)?true:$response;
	}	
	private function _callUrl($function,  $arguments, $serviceName){
	   	$url=$this->device[D_HOST].':';
		$s=$this->device[D_SERVICES][$serviceName];
		$url.=$s[S_PORT].$s[S_CURL];
	   	if($this->user||$this->pass)$url=str_ireplace('http://',"http://$this->user:$this->pass@", $url);
		$chr=strpos($url,'?')===false ? '?' : '&';
		$fn=isset($s[S_LOWERNAMES])?strtolower($function):$function;
	    if($chr=='&')$url.=$fn;
	    else $url.='/'.strtolower($fn);
	    $this->_prepareArguments($arguments,  empty($s[S_FUNCS][$function])?[]:explode(',',$s[S_FUNCS][$function]),$serviceName);
	    $args=http_build_query($arguments);
	    if($args)$url.=$chr.$args;
exit($url);	
	    
	    if(!$result=@file_get_contents($url)){
	    	return $this->error("Empty Response");
	    }
	    if(substr($result,0,5)=='<?xml'){
			$xml=new SimpleXMLElement(str_replace('e2', '', $result));
			$result=json_decode(json_encode($xml),true);
	    }elseif($result[0]=='['||$result[0]=='{'){
	      if($r=json_decode($result,true))$result=$r;
	    }
	    return $result;
	}
  	private function _callRpc($function, array $arguments, string $serviceName, bool $AsXmlrpc=false){
 	    $strencode=$strdecode=null;
  		if(!$AsXmlrpc){
		    $params = array_values($arguments);
		    $strencode = function(&$item, $key)use(&$strencode) {if (is_string($item) )$item = utf8_encode($item);else if ( is_array($item) )array_walk_recursive($item, $strencode);};
		    array_walk_recursive($params, $strencode);
		    $postData=json_encode(["jsonrpc" => "2.0","method" => $function,"params" => $params,"id" => $requestID = round(fmod(microtime(true)*1000, 10000))]);
			$header = "Content-type: application/json"."\r\n";
	    } else {
	    	if(!function_exists('xmlrpc_encode_request'))require_once 'xml_rpc.inc';
	    	$postData=xmlrpc_encode_request($function, $arguments);
	    	$header='Content-type: application/xml'."\r\n";
	    }
	   	$url=$this->device[D_HOST].':';
		$s=$this->device[D_SERVICES][$serviceName];
		$url.=$s[S_PORT].$s[S_CURL];
		if($this->user || $this->pass) {
			$header .= "Authorization: Basic ".base64_encode($this->user.":".$this->pass)."\r\n";
		}
		$options = ["http" =>["method"=>'POST',"header"=>$header,"content"=>$postData]];
		$context  = stream_context_create($options);		
		$result = file_get_contents($url, false, $context);
	    if(empty($result) || $result=== false)return $this->error('Request Empty Response');
		if($AsXmlrpc){
			$response=xmlrpc_decode($result);
    		if(isset($response['faultCode']))return $this->error($response['faultString'],$response['faultCode']);
			return $response;			
		}
		$response= json_decode($result, true);
	    if (is_null($response)) return $this->error('Invalid Responce Format');
	    $strdecode = function(&$item, $key)use(&$strdecode) {if ( is_string($item) )$item = utf8_decode($item);else if ( is_array($item) )array_walk_recursive($item, $strdecode);};
	    array_walk_recursive($response, $strdecode);
	    if (isset($response['error'])) return $this->error($response['error']['message']);
	    if ($response['id'] != $requestID)return $this->error('Invalid ResponseID '.$response['id'].' need '.$this->_requestID);
	    return $response['result'];
	}
	

}

class FritzApi extends RpcApi {
	
	protected function getDefaultValue($name,$serviceName) {
		$value = parent::getDefaultValue($name, $serviceName);
		if(empty($value) && strtolower($name)=='sid'){
			die('SID missing');
		}
		return $value;
	}
	
	
	function GetFritzboxSID($host, $pass, $user=''){
		$host.= '/login_sid.lua';
		$tmp = @simplexml_load_string(@file_get_contents($host));
		if(empty($tmp) || $tmp->BlockTime > 0)return empty($tmp)?null:(int)$tmp->BlockTime;
		$response = $tmp->Challenge . '-' . md5(iconv("UTF-8", "UTF-16LE",  $tmp->Challenge . '-' . $pass));
		$user=empty($user)?'?':'?user='.$user.'&';
		$host=$host . $user. 'response=' . $response; //'?user=' . $fritz_user . '&response=' . $response;
		$tmp = @simplexml_load_string(@file_get_contents($host));
		return empty($tmp)?null:(string)$tmp->SID;
	}	
}

?>