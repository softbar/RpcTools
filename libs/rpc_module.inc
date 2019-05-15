<?php 
require_once 'rpc_api.inc';
require_once 'base_module.inc';
/**
 * @author Xavier
 *
 */
abstract class RPCModule extends BaseModule {
	protected $LoginRequired=false;
	public function __call($fn,$args){
		return $this->CallApi($fn, $args);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();
		require_once 'utils.inc';
		$this->RegisterPropertyString('Host','');
		$this->RegisterPropertyString('User','');
		$this->RegisterPropertyString('Pass','');
		$this->RegisterPropertyString('RpcHash',''); // internal for RPC Configurator and detect startup
		$this->RegisterPropertyString('RpcConfig',''); // Rpc Methods definition
		UTILS::RegisterProfileIntegerEx('RPC_State','Information', '', '', [
			[0, $this->Translate('Online'), '', -1],	
			[1, $this->Translate('Offline'), '', -1],
			[2, $this->Translate('Error'), '', -1]	
		]);
		$this->RegisterVariableInteger('STATE', $this->Translate('State'), 'RPC_State', 0);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Destroy()
	 */
	public function Destroy(){
		parent::Destroy();
 		if( IPS_VariableProfileExists('RPC_State') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE00-201905120GEN}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE00-201905120MED}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE00-201905120FRI}')
		) IPS_DeleteVariableProfile('RPC_State');			
	}
	/**
	 * @return NULL|RpcApi Returns api Object for complex calls outside Module
	 */
	public function GetApi(){
		$saveApi=$this->api;
		$this->api=null;
		if($api = $this->CreateApi()){
			$api->SetErrorHandler(function($msg,$code){
				IPS_LogMessage('API', "Error:: ($code) $msg");
			});
		}
		$this->api=$saveApi;
		return $api;
	}
	/**
	 * @return NULL|RpcApi Returns api Object for complex calls outside Module
	 */
	public function GetApiInfo(){
		return ($api = $this->CreateApi())?$api->DeviceInfo():$this->Translate('Error');
	}
	/*
	 * Internal Variable definition 
	 */
	protected $api=null;
	protected $lastError='';
	private $online=null;
	private $state=null;
	
	/******************************************************
	 *  ValidatePropertys wird aufgerufen in baseModule
	 *  ApplyChanges() bevor  parent::ApplyChanges() 
	 *  aufgerufen wird
	 ******************************************************/
	
	protected function ValidatePropertys(){
		$startup=empty($this->ReadPropertyString('RpcHash'));
		$host=$this->ReadPropertyString('Host');
		if(!empty($host) && strpos($host,',')===false){
			if($startup){
				$url=parse_url($host);
				if(empty($url['host'])|| empty($url['path'])){
					// if results from GetDiscoverDeviceOptions is an array then
					// format = [ (int) options , (array) filter ]
					$options=$this->GetDiscoverDeviceOptions();
					if(is_numeric($options))$options=[$options,null,null];
					if(!empty($options[2])){
						if(empty($url['host']))$url['host']=$url['path'];
						$url=(empty($url['scheme'])?'http':$url['scheme']).'://'.$url['host'].':'.(empty($url['port'])?49000:$url['port']);
						if($options[2][0]!='/')$options[2]='/'.$options[2];
						$url.=$options[2];	
						$this->SendDebug(__FUNCTION__, 'New init Url url=>'.$url, 0);		
						$host=$url;
					}
				}
			}else{
				if(stripos($host,'http')===false && stripos($host,'.xml')===false){
					$host='http://'.$host;
					IPS_SetProperty($this->InstanceID,'Host', $host);
					
				}
			}
			$this->InitializeUrls($host);
		}elseif($host && (stripos($host,'.xml')!==false||strpos($host,',')!=false )) {
			$this->InitializeUrls($host);
		}
		
	}
	
	/******************************************************
	 *  Status
	 ******************************************************/
	
	// Wird aufgerufen nachdem Apply ausgefüht wurde 
	// wenn die Rückgabe true ist wird danach Process[Update|Offline] aufgerufen
	protected function ValidateStatus(){
		if(empty($this->ReadPropertyString('Host'))){
			$startup=empty($this->ReadPropertyString('RpcHash'));
			$this->SetStatus($startup?201:200);
		}elseif($this->LoginRequired && empty($this->ReadPropertyString('User'))){
			$this->SetStatus(202);
		}elseif($this->LoginRequired && empty($this->ReadPropertyString('Pass'))){
			$this->SetStatus(203);
		}else {
			$this->SetStatus(102);
			return $this->CheckStatus();
		}
		
	}
	protected function CheckStatus($debug=false,  $checkConnection=false) {
		if(parent::CheckStatus($debug,$checkConnection)){
			if(($s=$this->GetState())==0){
				return true;
			}else if($debug)$this->SendDebug(__FUNCTION__, "Invalid State: $s", 0);
		}
	}	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::SetStatus()
	 */
	protected function SetStatus($Status){
		parent::SetStatus($Status);
		$this->SetState($Status);
	}
	
	protected function CheckOnline() {
		if($this->online!=null)return $this->online;
		$host=parse_url($this->ReadPropertyString('Host'),PHP_URL_HOST);
		$this->online= !empty($host) && @Sys_Ping($host,1);
		$this->SetState($this->online?0:1);
		return $this->online;
	}	
	
	/******************************************************
	 * Timers
	 ******************************************************/
	protected function StartTimer(bool $startForce=false,int $seconds=0, string $mode='update'){
// 		if($this->CheckOnline())
		parent::StartTimer($startForce,$seconds, $this->CheckOnline()?$mode:'offline');
	}
	protected function SetupTimers(){
		$this->EnableUpdateTimer(10);
		$this->EnableOfflineTimer(5);
	}
	
	
	/******************************************************
	 * New Functions
	 ******************************************************/
	protected function GetState(){
		return $this->state==null?$this->state=GetValue($this->GetIDForIdent('STATE')):$this->state;
	}
	protected function SetState($code){
		if($code==102)
			$code=$this->CheckOnline()?0:1;
		else if($code>2)$code=2;
		$id=$this->GetIDForIdent('STATE');
		if(GetValue($id)!=$code)SetValue($id, $code);
		$this->state=$code;
	}
	protected function GetDeviceConfig(){
		if(empty($cfg=$this->ReadPropertyString('RpcConfig'))){
			$this->SendDebug(__FUNCTION__, $this->Translate("API device config is empty"), 0);
			return null;
		}
		if(strlen($cfg)<255 && stripos($cfg, '.rpc')){ // is a file
			if(!file_exists(RPCTOOLS .'/rpcconfig/'. $cfg) ){
				$this->SendDebug(__FUNCTION__, sprintf($this->Translate("API device config file %s not found"),$cfg), 0);
				return null;
			}else $cfg=file_get_contents(RPCTOOLS .'/rpcconfig/'. $cfg);
		}
		if(!($cfg=json_decode($cfg,true))) {
			$this->SendDebug(__FUNCTION__, $this->Translate("Invalid API device config"), 0);
			return null;
		}
		return $cfg;		
	}
	protected function CreateApi(){
		if(!is_null($this->api))return $this->api;
		if(	!$this->CheckOnline() ||
			$this->GetStatus()!=102 ||
		    (!($cfg=$this->GetDeviceConfig())))return null;
		
		try {
			if( $cfg[D_PROPS]!=$this->GetProps())$this->SetProps($cfg[D_PROPS],true,true);
			$this->api=new RpcApi($cfg ,$this->ReadPropertyString('Host'),$this->ReadPropertyString('User'),$this->ReadPropertyString('Pass'));
			$this->SendDebug(__FUNCTION__, 'Api Created', 0);
			$this->api->SetErrorHandler(function($msg,$code){
				$this->SendDebug('API',$this->lastError="($code) $msg",0);
			});
		}catch(Exception $e){
			$this->SendDebug(__FUNCTION__, 'Error Api Creation =>'.$this->lastError=$e->getMessage(), 0);
			$this->api=null;
		}
		return $this->api;
	}
	
	protected function CallApi(string $function, array $arguments=[]){
  		$this->lastError='';
		$this->SendDebug('CallApi',"$function(".implode(',',$arguments).')',0);
		$r=($api=$this->CreateApi())?$api->__call($function,$arguments):null;
 		$this->SendDebug('CallApi',"returns => ".json_encode($r),0);
		return $r; 
	}	
	protected function GetDiscoverDeviceOptions(){
		return OPT_MINIMIZED|OPT_PROPS_ONLY;
	}
	
	protected function DefaultName($name){
		return $name;
	}
	protected function InitializeUrls($urls){
 		if( strpos($urls,',')===false && parse_url($urls,PHP_URL_PATH)=='')return false;
		require_once RPCTOOLS.'/discover.inc';
		try {
			// if resust from GetDiscoverDeviceOptions is an array then
			// format = [ (int) options , (array) filter ]
			$options=$this->GetDiscoverDeviceOptions();
			if(is_numeric($options))$options=[$options,null];
			$d=DiscoverDevice($urls,$options[0],$options[1]);
		} catch (Exception $e){
			IPS_LogMessage(__CLASS__,__FUNCTION__."::Error Discover Device from => $urls");
			$this->SendDebug(__FUNCTION__, "Error ".$e->getMessage(), 0);
			return false;
		}
		$save=!IPS_HasChanges($this->InstanceID);
		$size=strlen(json_encode($d));
		if($size>20000){
			$fn=pathinfo(DiscoverSaveDevice($d),PATHINFO_BASENAME);
			IPS_SetProperty($this->InstanceID, 'RpcConfig',$fn);
		}else {
			IPS_SetProperty($this->InstanceID, 'RpcConfig', json_encode($d));
		}
		IPS_SetProperty($this->InstanceID, 'RpcHash', md5($d[D_INFO]));
		IPS_SetProperty($this->InstanceID, 'Host', $d[D_HOST]);
		IPS_SetName($this->InstanceID,$this->DefaultName($d[D_NAME]));
// 		if($this->ReadPropertyInteger('DeviceProps')) != $d[D_PROPS] )
		$this->SetProps($d[D_PROPS],true,true);	
		if($save)IPS_ApplyChanges($this->InstanceID);
 		return true;
	}

	protected function ReDiscover(){
		if(!($cfg=$this->GetDeviceConfig()))return;
		if(empty($cfg[D_URLS])){
			$this->SendDebug(__FUNCTION__,'no urls saved', 0);
			return;
		}
		$urls=explode(',',$cfg[D_URLS]);
		$h=$this->ReadPropertyString('Host');
		foreach($urls as $k=>$v)$urls[$k]=$h.$v;
		$urls=implode(',',$urls);
		$this->SendDebug(__FUNCTION__,'with urls => '.$urls, 0);
		IPS_SetProperty($this->InstanceID, 'RpcHash', '');
		IPS_SetProperty($this->InstanceID, 'RpcConfig', '');
		$this->InitializeUrls($urls);
// 		IPS_ApplyChanges($this->InstanceID);
		$this->SendDebug(__FUNCTION__,'finishd', 0);
	}

	/******************************************************
	 * Hooks
	 ******************************************************/
	
	protected function ProcessHookData() {	
		if(empty($request = file_get_contents('php://input')))return false;
		$this->SendDebug(__FUNCTION__, print_r($request,true),0);
		return true;
// 		if(!$this->ReadPropertyBoolean('EnableEvents'))return;
	}	
	
}

?>