<?php
if(!defined('RPCTOOLS'))define('RPCTOOLS', __DIR__);
require_once 'rpc_constants.inc';
if(!defined('IPS_KERNELMESSAGE'))define(IPS_KERNELMESSAGE,100);
if(!defined('KR_READY'))define(KR_READY,10103);
define('MAX_CFG_SIZE_BEFORE_SAVE_AS_FILE',20000);

/**
 * @author Xavier
 *
 */
abstract class BaseRpcModule extends IPSModule {
	/** @brief Register base propertys and profiles for Device
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create(){
		parent::Create();
		require_once 'utils.inc';
		$this->RegisterPropertyString('Host','');
		$this->RegisterPropertyString('User','');
		$this->RegisterPropertyString('Pass','');
		$this->RegisterPropertyInteger('ModuleProps', 0);
		if($this->timerDef['ONLINE_INTERVAL'][0])$this->RegisterPropertyInteger('ONLINE_INTERVAL', 0);
		if($this->timerDef['OFFLINE_INTERVAL'][0])$this->RegisterPropertyInteger('OFFLINE_INTERVAL', 0);
		$this->RegisterTimer('UPDATE_TIMER',0,"IPS_RequestAction($this->InstanceID,'UPDATE',true);");
		UTILS::RegisterProfileIntegerEx('RPC_State','Information', '', '', [
			[0, $this->Translate('Connected'), '', -1],	
			[1, $this->Translate('Disconnected'), '', -1],
			[2, $this->Translate('Error'), '', -1]	
		]);
		if($this->showRefreshButton){
			$this->RegisterPropertyBoolean('ShowRefreshButton',true);
			UTILS::RegisterProfileIntegerEx('RPC_Refresh','Climate', '', '', [
				[1, $this->Translate("Refresh"), '', -1]
			]);
		}
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}
	/** @brief Apply changes of instance configuration. If configuration not changed then calls BaseRpcModule::RunUpdate()
	 * {@inheritDoc}
	 * @see IPSModule::ApplyChanges()
	 * @return bool True if no property, inside this function, changed
	 */
	public function ApplyChanges(){
		parent::ApplyChanges();
		$ok=$this->ValidateConfiguration();
		if($ok)$ok=$this->UpdateProps(false);
		if (IPS_HasChanges($this->InstanceID)){
 			$this->SendDebug(__FUNCTION__, "Has Changes.. save...", 0);
			IPS_ApplyChanges($this->InstanceID);
			return false;
		}
		if($ok){
			$this->SendDebug(__FUNCTION__, "Config Ok, DoUpdate", 0);
			$this->SetStatus(102);
			if(IPS_GetKernelRunlevel ()==KR_READY){
				$this->RunUpdate();
			}
		}
		return $ok;
	}
	/** @brief Cleanup unsused profiles if is last instance
	 * {@inheritDoc}
	 * @see IPSModule::Destroy()
	 */
	public function Destroy(){
		parent::Destroy();
 		if( $this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120GEN}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120MED}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-20190531SAMR}') &&	
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-20190531SOAV}') &&	
  			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FST}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FLO}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FCM}') &&
 			$this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FHA}') 
 			
 		){
 			@IPS_DeleteVariableProfile('RPC_State');			
 			@IPS_DeleteVariableProfile('RPC_Refresh');			
 		}
	}
	
	/** @brief Load formular and handle Timers and Login fields
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm(){
		$f=json_decode(file_get_contents(RPCTOOLS .'/form.json'),true);
		if(!$this->showRefreshButton)array_splice($f['elements'],3,1);
		if(!$this->requireLogin[1] && !$this->showLogin[1])array_splice($f['elements'],2,1);
		if(!$this->requireLogin[0] && !$this->showLogin[0])array_splice($f['elements'], 1,1);
		
		$s=['s'=>'seconds','m'=>'minutes','h'=>'hours'];
		if($this->timerDef['ONLINE_INTERVAL'][0]) $f['elements'][]=['type'=>'NumberSpinner','name'=>'ONLINE_INTERVAL', 'caption'=>'Refresh every', 'suffix'=>$s[$this->timerDef['ONLINE_INTERVAL'][1]].' 0=off'];
		if($this->timerDef['OFFLINE_INTERVAL'][0])$f['elements'][]=['type'=>'NumberSpinner','name'=>'OFFLINE_INTERVAL', 'caption'=>'Offline check every', 'suffix'=>$s[$this->timerDef['OFFLINE_INTERVAL'][1]].' 0=off'];
		if(defined('MODULEDIR') && file_exists(MODULEDIR.'/form.json')){
			$fn = json_decode(file_get_contents(MODULEDIR.'/form.json'),true);
			if(!empty($fn['elements']))$f['elements']=array_merge($f['elements'],$fn['elements']);
			if(!empty($fn['actions']))$f['actions']=$fn['actions'];
			if(!empty($fn['status'])) $f['status']=array_merge($f['status'],$fn['status']);
		}
		return json_encode($f);
	}
	/** @brief Handle timer update events
	 * {@inheritDoc}
	 * @see IPSModule::RequestAction()
	 * @return bool True if ident processed
	 */
	public function RequestAction($Ident, $Value){
		if($Ident=='UPDATE'){
			$this->RunUpdate();
			return true;
		}
	}
	/** @brief Used to check Systemstartup and update each RpcTools device
	 * {@inheritDoc}
	 * @see IPSModule::MessageSink()
	 */
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data){
		if($Message==IPS_KERNELMESSAGE && $Data[0] === KR_READY){
			$this->StartTimer(rand(1, 6));
		}
    }	
	
	// -----------------------------------------------------------------------------------
	/** 
	 * @brief Setup the Timers
	 * Override this to configure Timers for Module
	 * 
	 * ONLINE_INTERVAL checks when device is Online	 * 
	 * OFFLINE_INTERVAL checks when device is offline
	 * 
	 * Parameters for Checks are [0,'s']
	 * first are the minimum value 0 = timer disabled and second are the multiplicator  s = seconds, m = minutes and h are hours
	 * 
	 * @var array $timerDef
	 * @note Override in your Module to configure Timers
	 */
	protected $timerDef=['ONLINE_INTERVAL'=>[0,'s'],'OFFLINE_INTERVAL'=>[0,'m']];
	/** @brief Setup login paramerters used in ApplyChanges and GetConfigurationForm
	 * 
	 * if first true then check that Username are given, second true checks for password
	 * @var array $requireLogin
	 * @note Override in your Module to configure require login
	 */
	protected $requireLogin=[false,false];
	/** @brief Setup login paramerters used in ApplyChanges and GetConfigurationForm.
	 * 
	 * if first true then shows username field in formular, seccond true show password field
	* @var array $showLogin
	 * @note Override in your Module to configure show login
	 */
	protected $showLogin=[false,false];
	/** @brief If true then show config field in formulars to display a refresh button variable
	 * @var bool $showRefreshButton
	 * @note Override in your Module to configure refresh button
	 */
	protected $showRefreshButton=false;
	/** @brief if not checked then $online=null, true if online false if host not reachable
	 * @var bool|null $online
	 */
	protected $online=null;
	/** @brief State cache if not checked then $state=null, 0=connected | 1=disconnected | 2=error
	 * @var int|null $state
	 */
	protected $state=null;
	/** @brief Status cache if not checked then $status=null 
	 * @var int|null $status
	 */
	protected $status = null;
	// -------------- Status -------------------------------------------------------------
	/** @brief Validate current configuration
	 * @return bool True if nothing changed and Configuration is valid
	 */
	protected function ValidateConfiguration(){
		$ok=$this->ValidateTimers();
		if(!$this->ApplyHost($this->ReadPropertyString('Host'),false))$ok=false;
		if($ok){
			if($this->showRefreshButton){
				$show=$this->ReadPropertyBoolean('ShowRefreshButton');
				$id = @$this->GetIDForIdent('UPDATE');
				if($show && !$id){
					$id=$this->RegisterVariableInteger('UPDATE', $this->Translate('Refresh now'), 'RPC_Refresh', 1);
					IPS_SetIcon($id, 'Climate');
					$this->EnableAction('UPDATE');
				}elseif(!$show && $id){
					$this->DisableAction('UPDATE');
					$this->UnregisterVariable('UPDATE');
				}
			}
			
			$ok=false;
			if($this->requireLogin[0] && empty($this->ReadPropertyString('User')))
				$this->SetStatus(202);
			elseif($this->requireLogin[1] && empty($this->ReadPropertyString('Pass')))
				$this->SetStatus(203);
			else $ok=true;
		}
		return $ok;
	}
	/** @brief Set module status
	 * {@inheritDoc}
	 * @see IPSModule::SetStatus()
	 */
	protected function SetStatus($Status){
		parent::SetStatus($this->status=$Status);
		if($Status==102)
			$this->CheckOnline();
		else $this->SetDeviceState(2);
	}
	/** @brief Return current instance status
	 * @return int Instance Status 
	 */
	protected function GetStatus(){
		return is_null($this->status)?$this->status=IPS_GetInstance($this->InstanceID)['InstanceStatus']:$this->status;
	}
	/** @brief Get device state
	 * @return int State of device 0=connected|1=disconnected|2=error
	 */
	protected function GetDeviceState(){
		if(!($id=@$this->GetIDForIdent('STATE'))){
			$id=$this->RegisterVariableInteger('STATE', $this->Translate('State'), 'RPC_State', 0);
			SetValue($id,$this->state=(IPS_GetInstance($this->InstanceID)['InstanceStatus']!=102)?2:1);
		}
		return is_null($this->state)?$this->state=GetValue($id):$this->state;
	}
	/** @brief Set device state
	 * @param int $State Valid states are 0=connected|1=disconnected|2=error
	 */
	protected function SetDeviceState($State){
		if(!($id=@$this->GetIDForIdent('STATE')))$id=$this->RegisterVariableInteger('STATE', $this->Translate('State'), 'RPC_State', 0);
		if(GetValue($id)!=$State)SetValue($id,$State );
		$this->state=$State;
	}
	/** @brief Checks if host online and setup devicestate
	 * @return bool True if device is online
	 */
	protected function CheckOnline() {
		if(!is_null($this->online))return $this->online;
		$host=parse_url($this->ReadPropertyString('Host'),PHP_URL_HOST);
		$this->online= !empty($host) && @Sys_Ping($host,1);
		$this->SetDeviceState($this->online?0:1);
		return $this->online;
	}	
	// -------------- Module or Device Props -------------------------------------------
	/** @brief Get the module propertys
	 * @return int
	 */
	protected function GetProps(){
		return $this->ReadPropertyInteger('ModuleProps');// intval($this->GetBuffer('MY_PROPS'));
	}
	/** @brief Setup module propertys
	 * @param int $props Device or Module Propertys
	 * @param bool $Update If true then autocreate and delete used and unused vars
	 * @param bool $doApply If true then changes will Apply to Config , false is call comes out from Apply() function
	 * @return bool True if Props changes
	 */
	protected function SetProps(int $props, $Update=true, $doApply=true){
		if($props!=$this->GetProps()){
			$this->SendDebug(__FUNCTION__, "Set new Props: $props", 0);
			if($Update)$this->UpdateDeviceProps($props, $Update,$Update);
			$save= !IPS_HasChanges($this->InstanceID);
			IPS_SetProperty($this->InstanceID, 'ModuleProps', $props);
			if($save && $doApply)IPS_ApplyChanges($this->InstanceID);
			return true;	
		}
		return false;
	}
	/** @brief Update module status variables by propertys
	 * @param int $props Device or Module propertys
	 * @param bool $AutoCreate if true then auto create missing status variables
	 * @param bool $AutoRemove if true then remove unused status variables
	 * @return bool True if status variables removed or created
	 */
	protected function UpdateDeviceProps($props, $AutoCreate=true, $AutoRemove=true){
		if(!$AutoCreate && !$AutoRemove)return false;
		foreach ($this->prop_names as $prop=>$ident){
			$add=(bool)($props & $prop);
			$id=@$this->GetIDForIdent($ident);
			if(!$id && $add && $AutoCreate){
				$id=$this->CreateVarByIdent($ident, false);
			}elseif($id && !$add && $AutoRemove){
// 				if($this->GetPropDef($ident)[DEF_ACTION]==1)
				@$this->DisableAction($ident);
				$this->UnregisterVariable($ident);
			}
		}
		return true;
	}
	/** @brief Update device property is called from ApplyChanges
	 * Override in your own Module
	 * @param bool $doApply If true then changes are applyed to instance
	 * @return bool True is nothing changed
	 */
	protected function UpdateProps($doApply=true){
		return true;
	}
	// -------------- Timers ----------------------------------------------------------
	/** @brief Checks if TimerValues are Ok
	 * This function is called from ApplyChanges and check timer values based on BaseRpcModule::$timerDef
	 * @return bool True if timers valid
	 */
	protected function ValidateTimers(){
		$check=function($id){
			if($this->timerDef[$id][0]){
				$v=$nv=$this->ReadPropertyInteger($id);
				if($v<0)$nv=0;
				elseif($v && $v<$this->timerDef[$id][0])$nv=$this->timerDef[$id][0];
				if($v!=$nv)$this->SendDebug(__FUNCTION__, "correct $id from $v to $nv", 0);
				if($v!=$nv){
					IPS_SetProperty($this->InstanceID, $id, $nv);
					return false;
				}
			}
			return true;
		};
		$ok=$check('ONLINE_INTERVAL');
		if(!$check('OFFLINE_INTERVAL'))$ok=false;
		return $ok;
	}
	/**
	 * @brief Stop current Timer
	 */
	protected function StopTimer(){
		$this->SetTimerInterval('UPDATE_TIMER', 0);
	}
	/**
	 * @brief Start timer based on BaseRpcModule::$timerDef
	 * @param number $delay if greater 0 then this will used to setup timer interval
	 */
	protected function StartTimer($delay=0){
 		if($this->GetDeviceState()>1) return;
		$s=($this->CheckOnline()?'ONLINE':'OFFLINE').'_INTERVAL';
		$i=$delay>0?$delay:($this->timerDef[$s][0]>0?$this->ReadPropertyInteger($s):0);
		if($i>0){
			if(!$delay){
				$t=$this->timerDef[$s][1];
				if($t=='m')$i=$i*60;
				elseif($t=='h')$i=$i*60*60;
			}
			$this->SetTimerInterval('UPDATE_TIMER', $i*1000);
		}
	}
	/**
	 * @brief Main Update Caller , this will be call from TIMER, APPLY and SystemStartup.
	 * @note Override in your own Module
	 */
	protected function RunUpdate(){}
	// -------------- Device Creation -----------------------------------------------------
	/** @brief Checks and Setup given host
	 * @param string $host Host url/ip to check
	 * @param bool $doApply If true changes are applyed to module configuration
	 */
	protected function ApplyHost($host,$doApply=true){
		if(empty($host)){
			$this->SetStatus(200);
			return false;
		}
		return true;
	}
	// -------------- Variables -------------------------------------------------------
	/** @brief Get status variable value by ident
	 * @param string $Ident Status variable ident to get value from
	 * @return NULL|mixed The value for ident or NULL if ident invalid
	 */
	protected function GetValueByIdent($Ident){
		return ($id=@$this->GetIDForIdent($Ident))?GetValue($id):null;
	}
	/** @brief Set status variable value by ident
	 * @param string $Ident Variable ident to set value
	 * @param mixed $Value Data to set
	 * @param bool $force Set value if changed or not
	 * @param bool $AutoCreate Create variable if not exists
	 * @return bool True if changed or false if ident not found or not changed 
	 */
	protected function SetValueByIdent($Ident,$Value, $force=true, $AutoCreate=true ){
		if(!($id=@$this->GetIDForIdent($Ident)) && $AutoCreate){
			$id=$this->CreateVarByIdent($Ident,false);
		}
		if($id){
			$changed=GetValue($id)!=$Value;
			if($force || $changed){
				SetValue($id, $Value);
				return $changed;
			}
		}
		return false;
	}
	/** @brief Create status variable by module property
	 * @param int $Prop Property to create Variable for
	 * @return int Variable Instance ID
	 */
	protected function CreateVarByProp($Prop){
		$ident=$this->prop_names[$Prop];
		return $this->CreateVarByIdent($ident,false);
	}
	/** @brief Create status variable by ident
	 * @param string $Ident Variable ident to create
	 * @param bool $CheckIdent Check Ident is Valid
	 * @return int Variable Instance ID
	 */
	protected function CreateVarByIdent($Ident, $CheckIdent=true){
//   IPS_LogMessage('THIS', 'create var '.$Ident."::".($CheckIdent?'true':'false'));		
 		if($CheckIdent && !$this->ValidIdent($Ident))return 0;
		$id=@$this->GetIDForIdent($Ident);
		if(!$id){
			if(!($def=$this->GetPropDef($Ident))){
				$this->SendDebug(__FUNCTION__, "Prop ->$Ident<- not defined", 0);
				return 0;
			}
			$profile=$def[DEF_PROFILE];
			if(!empty($profile) && !IPS_VariableProfileExists($profile)){
				require_once 'utils.inc';
				if(!$this->CreateMissedProfile($profile)){
					IPS_LogMessage(IPS_GetName($this->InstanceID), "Profile $profile for $Ident missing, set to none");
					$profile='';
				}
			}
			$name=$this->Translate($def[DEF_NAME]);
			$pos=(int)$def[DEF_POS];
			switch($def[DEF_TYPE]){
				case 0: $id=$this->RegisterVariableBoolean(	$Ident, $name, $profile, $pos);break;
				case 1: $id=$this->RegisterVariableInteger(	$Ident, $name, $profile, $pos);break;
				case 2: $id=$this->RegisterVariableFloat(	$Ident, $name, $profile, $pos);break;
				case 3: $id=$this->RegisterVariableString(	$Ident, $name, $profile, $pos);break;
				default:
					IPS_LogMessage(IPS_GetName($this->InstanceID), "Invalid Variable Type ".$def[DEF_TYPE]);
					return 0;
			}
			if($def[DEF_ICON])IPS_SetIcon($id, $def[DEF_ICON]);
			if($def[DEF_ACTION]==1)$this->EnableAction($Ident);
		}
		return $id;
	}
	/** @brief Checks if ident valid
	 * @param string $Ident Variable ident to check
	 * @param bool $CheckProp If true then checks ident prop is set
	 * @return bool
	 */
	protected function ValidIdent($Ident, $CheckProp=false){
		if( (!in_array($Ident,$this->prop_names)) ||
			($CheckProp &&  !($def=$this->GetPropDef($Ident)))	
		) return false;
		return !$CheckProp?true:(bool)($this->GetProps() & $def[DEF_PROP]);
	}
	/** @brief Is called if a profile missed
	 * @param string $name Create missing profile 
	 * @return bool True if profile created
	 * @note Override in your own Module
	 */
	protected function CreateMissedProfile($name){
		IPS_LogMessage(IPS_GetName($this->InstanceID), __FUNCTION__."::Profile ->$name<- not found");
	}
	/** @brief Returns status variable definition
	 * @param string $Ident Variable ident to get Definition
	 * @return null|array Status variable config array or null if ident not valid
	 * @note Must Override in your own Module
	 */
	protected abstract function GetPropDef($Ident);
 	/** @brief Contains a List of valid Propertys
 	 * 
 	 * Key = Property value
 	 * 
 	 * Value = Property ident name
 	 * @var array $prop_names
	 * @note Must Override in your own Module
 	 */
 	protected $prop_names = [];
	//------------------- Tools ----------------------------------------------------------
	/** @brief Is called once if module created or Configuration valid
	 * @param string $name The name of device
	 * @param string $host The host for device
	 * @return string The displayed module name
	 * @note Override in your own Module
	 */
	protected function GetModuleName($name,$host){
		return $name. ' ('.parse_url($host,PHP_URL_HOST).')';
	}

	/** @brief Checks if more instances created with then given module ID
	 * @param string $guid Module ID
	 * @return bool True if no more instance created
	 */
	protected function IsLastInstance($guid){
 		return 	(count(IPS_GetInstanceListByModuleID($guid))==0);
	}
}
/**
 * @author Xavier
 *
 */
abstract class IPSRpcModule extends BaseRpcModule {
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::Create()
	 */
	public function Create(){
		parent::Create();
		$this->RegisterPropertyString('RpcHash',''); // internal for RPC Configurator and detect startup
		$this->RegisterPropertyString('RpcConfig',''); // Rpc Methods definition
	}
	/**
	 * @return NULL|RpcApi Returns api Object for complex calls outside Module
	 */
	public function GetApi(){
		$saveApi=$this->api;
		$this->api=null;
		if($api = $this->CreateApi()){
			$api->SetErrorHandler(function($msg,$code){
				IPS_LogMessage("API","Error:: ($code) $msg");
			});
		}
		$this->api=$saveApi;
		return $api;
	}
	/** @brief Get current device api info
	 * @return NULL|array Api Informations
	 */
	public function GetApiInfo(){
		return ($api = $this->CreateApi())?$api->DeviceInfo():null;
	}
 	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::$showLogin
	 * @var array $showLogin
	 */
	protected $showLogin=[true,true];
	/**
	 * @brief Current rpc api object
	 * @var RpcApi $api 
	 */
	protected $api=null;
	/**
	 * @brief Contains the last error of api call
	 * @var string $lastError
	 */
	protected $lastError='';
	// -------------- Device Props ----------------------------------------------------
	/**
	 * @param int $Props
	 * @param bool $doApply
	 * @note Override in your own Module
	 */
	protected function ApplyDeviceProps($Props, $doApply){}
	// -------------- Update ----------------------------------------------------------
	/** @brief This function is called from RunUpdate and handle updates for status variables
	 * @note Override in your own Module
	 */
	protected abstract function DoUpdate();
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::RunUpdate()
	 */
	protected function RunUpdate(){
		$this->StopTimer();
		if( $this->GetDeviceState()<2 && $this->CheckOnline()) 
			$this->DoUpdate();
		$this->StartTimer();
	}
	// -------------- Device ----------------------------------------------------------
	/** @brief Get config array to initilize RpcApi
	 * @return NULL|array Api config
	 */
	protected function GetDeviceConfig(){
		if(empty($cfg=$this->ReadPropertyString('RpcConfig'))){
			$this->SendDebug(__FUNCTION__, "API device config is empty", 0);
			return null;
		}
		if(strlen($cfg)<255 && stripos($cfg, '.rpc')){ // is a file
			if(!file_exists(RPCTOOLS .'/rpcconfig/'. $cfg) ){
				$this->SendDebug(__FUNCTION__, sprintf("API device config file %s not found",RPCTOOLS .'/rpcconfig/'. $cfg), 0);
				return null;
			}else $cfg=file_get_contents(RPCTOOLS .'/rpcconfig/'. $cfg);
		}
		if(!($cfg=json_decode($cfg,true))) {
			$this->SendDebug(__FUNCTION__, "Invalid API device config", 0);
			return null;
		}
		return $cfg;		
	}
	/** @brief Return RpcApi object 
	 * @return NULL|RpcApi Api object
	 */
	protected function CreateApi(){
		if(!is_null($this->api))return $this->api;
		if(	$this->GetDeviceState()>1 ||  
			!$this->CheckOnline() ||
		    (!($cfg=$this->GetDeviceConfig()))
		) return null;
		$u=$this->ReadPropertyString('User');
		$p=$this->ReadPropertyString('Pass');
		if($cfg[D_OPTIONS]&OPT_REQUIRE_LOGIN){
			if(empty($u)||empty($p)){
				if(empty($u))$this->SetStatus(202);
				elseif(empty($p))$this->SetStatus(203);
				return null;
			}
		}
		require_once 'rpc_api.inc';
		try {
			$this->api=new RpcApi($cfg ,$this->ReadPropertyString('Host'),$u,$p);
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
	/** @brief Make a rpc call
	 * @param string $function Function name to call
	 * @param array $arguments Function arguments
	 * @return NULL|mixed Result of api call
	 */
	protected function CallApi(string $function, array $arguments=[]){
  		$this->lastError='';
  		if(!($api=$this->CreateApi()))return;
		$this->SendDebug('CallApi',"$function(".implode(',',$arguments).')',0);
		$r=$api->__call($function,$arguments);
 		$this->SendDebug('CallApi',"returns => ".json_encode($r),0);
		return $r; 
	}	
	/** @brief Get the default discover device config. Need to auto configure Device
	 * @return array Discover config [int options, array filter=[], string init page='']
	 * @note Override in your own Module
	 */
	protected function GetDiscoverDeviceOptions(){
		return [OPT_MINIMIZED|OPT_PROPS_ONLY,null,null];
	}
	
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::ApplyHost()
	 */
	protected function ApplyHost($host, $doApply=true){
		if(!parent::ApplyHost($host,$doApply))return false;

		$startup=empty($this->ReadPropertyString('RpcHash'));
		$isInitUrl=strpos($host,',')||stripos($host,'.xml');
		
		if($startup||$isInitUrl){
			// if results from GetDiscoverDeviceOptions is an array then
			// format = [ (int) options , (array) filter, (string) description file ]
			$options=$this->GetDiscoverDeviceOptions();
			if(is_numeric($options))$options=[$options,null,null];
		}
		if($startup && !$isInitUrl){
			$url=parse_url($host);
			if(empty($url['host'])|| empty($url['path'])){
				if(!empty($options[2])){
					if(empty($url['host']))$url['host']=$url['path'];
					$host=(empty($url['scheme'])?'http':$url['scheme']).'://'.$url['host'].$options[2];	
					$isInitUrl=true;
					$this->SendDebug(__FUNCTION__, 'New init Url url=>'.$host, 0);		
				}
			}
		}
		$save=!IPS_HasChanges($this->InstanceID);
		if($isInitUrl){
			require_once RPCTOOLS.'/discover.inc';
			$this->SendDebug(__FUNCTION__, $host, 0);
			try {
				$d=DiscoverDevice($host,$options[0],$options[1]);
			} catch (Exception $e){
				IPS_LogMessage(IPS_GetName($this->InstanceID),__FUNCTION__."::Error Discover Device from => $host");
				$this->SendDebug(__FUNCTION__, "Error ".$e->getMessage(), 0);
				if ($startup)$this->SetStatus(201);
				return false;
			}
			
			$size=strlen(json_encode($d));
			if($size>MAX_CFG_SIZE_BEFORE_SAVE_AS_FILE){
				$fn=pathinfo(DiscoverSaveDevice($d),PATHINFO_BASENAME);
				IPS_SetProperty($this->InstanceID, 'RpcConfig',$fn);
			}else {
				IPS_SetProperty($this->InstanceID, 'RpcConfig', json_encode($d));
			}
			IPS_SetProperty($this->InstanceID, 'RpcHash', md5($d[D_INFO]));
			IPS_SetProperty($this->InstanceID, 'Host', $host=$d[D_HOST]);
			if($this->ApplyDeviceProps($d[D_PROPS],$doApply)===false)$save=true;
			if($doApply&&$save)IPS_ApplyChanges($this->InstanceID);
			IPS_SetName($this->InstanceID,$this->GetModuleName($d[D_NAME],$host));
			$startup=false;
		}
		if ($startup){
			$this->SetStatus(201);
		}else if(stripos($host,'http')===false){
			$host='http://'.$host;
			IPS_SetProperty($this->InstanceID,'Host', $host);
// $this->SendDebug(__FUNCTION__, "Apply host $host SAVE: ".($save?'true':'false'), 0)	;		
			if($doApply&&$save)IPS_ApplyChanges($this->InstanceID);
		}else return true;
		return false;
	}
	/**
	 * @return void
	 */
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
		$this->ApplyHost($urls);
		$this->SendDebug(__FUNCTION__,'finishd', 0);
	}
	// ---------------- Hooks -------------------------------------------------------------------
 	/** @brief Register a Hook for external calls
 	 * @param string $Name Name of the hook
 	 * @param bool $Create if true Hook creating , if false delete hook
 	 * @return string|bool if creating then returns full hook name or false if error or nothing change
 	 */
 	protected function RegisterHook($Name, bool $Create) {
		$ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");
		if(sizeof($ids) > 0) {
			$hookname= "/hook/$Name";
			$hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
			$found = false;
			foreach($hooks as $index => $hook) {
				if(trim($hook['Hook']) == $hookname) {
					if($hook['TargetID'] == $this->InstanceID){
						if($Create)	return $hookname;
						$found=$index;
						break;
					}
					$hooks[$index]['TargetID'] = $this->InstanceID;
					$found = $index;
					break;
				}
			}
			$changed = false;
			if(!$Create && $found!==false){
				unset($hooks[$found]);
				$changed=true;
			}else if($Create && $found===false){
				$hooks[] = Array("Hook" => $hookname, "TargetID" => $this->InstanceID);
				$changed=true;
			}
			if($changed){
				IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
				IPS_ApplyChanges($ids[0]);
			}
			return $Create?$hookname:$changed;
		}else IPS_LogMessage(IPS_GetName($this->InstanceID),'ERROR Instance WebHook not found');
		return false;
	}
	/** @brief Process the hook data
	 * @return bool True if proceed successful 
	 */
	protected function ProcessHookData(){}
}
CONST
	DEF_TYPE = 0,
	DEF_NAME = 1,
	DEF_PROFILE = 2,
	DEF_POS = 3,
	DEF_ICON = 4,
	DEF_PROP = 5,
	DEF_ACTION = 6;


?>