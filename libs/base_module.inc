<?php
if(!defined('RPCTOOLS'))define('RPCTOOLS', __DIR__);
abstract class BaseModule extends IPSModule {
	
	/**
	 * @param int $InstanceID
	 */
	function __construct($InstanceID) {
		$this->SetupTimers();
		parent::__construct($InstanceID);
	}

	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create(){
		parent::Create();
		$this->RegisterPropertyInteger('ModuleProps', 0);
		$t=$this->UpdateTimer;
		if($t['enabled']){
			$this->RegisterTimer($t['ident'],0,"IPS_RequestAction($this->InstanceID,'{$t['ident']}',true);");
			$this->RegisterPropertyInteger($t['ident'], 0);
		}
		$t=$this->OfflineTimer;
		if($t['enabled']){
			$this->RegisterTimer($t['ident'],0,"IPS_RequestAction($this->InstanceID,'{$t['ident']}',true);");
			$this->RegisterPropertyInteger($t['ident'], 0);
		}
		
	}
	public function ApplyChanges(){
		$this->ValidatePropertys();
		parent::ApplyChanges();
		if($this->ValidateStatus())
			$this->Process($this->CheckOnline()!==false);
// 		}
	}
	public function RequestAction($Ident, $Value){
		switch($Ident){
			case $this->UpdateTimer['ident'] : $this->ProcessUpdate();break;
			case $this->OfflineTimer['ident'] : $this->ProcessOffline(); break;
			default : return false;
		}
		return true;
	}

	/******************************************************
	 * Tools
	 ******************************************************/
	
	protected function IsLastInstance($guid){
 		return 	(count(IPS_GetInstanceListByModuleID($guid))==0);
	}

	/******************************************************
	 * Updates
	 ******************************************************/
	private function Process($online=true){
		if($online)$this->ProcessUpdate();else $this->ProcessOffline();
	}
	protected function ProcessUpdate(){
		$this->SendDebug(__FUNCTION__,'called',0);
	}
	protected function ProcessOffline(){
		$this->SendDebug(__FUNCTION__,'called',0);
	}
	
	/******************************************************
	 * Timers
	 ******************************************************/
	protected $UpdateTimer=['enabled'=>false,'tm'=>'s','min'=>15,'max'=>null,'ident'=>'UPDATE_TIMER'];
	protected $OfflineTimer=['enabled'=>false,'tm'=>'m','min'=>5,'max'=>null,'ident'=>'OFFLINE_TIMER'];
	private function EnableTimer($Ident, $TimeMode, $MinValue, $MaxValue, $RegVar){
		if(strtolower($Ident)=='update')$this->UpdateTimer=['enabled'=>true,'tm'=>$TimeMode,'min'=>$MinValue,'max'=>$MaxValue,'ident'=>$RegVar];
		if(strtolower($Ident)=='offline')$this->OfflineTimer=['enabled'=>true,'tm'=>$TimeMode,'min'=>$MinValue,'max'=>$MaxValue,'ident'=>$RegVar];
	}
	protected function SetupTimers(){}
	/**
	 * @param int $MinValue
	 * @param string $TimeMode
	 * @param int $MaxValue
	 */
	protected function EnableUpdateTimer(int $MinValue, string $TimeMode='s', int $MaxValue=null){
		$this->EnableTimer('update', $TimeMode, $MinValue, $MaxValue, 'UPDATE_TIMER');
	}
	/**
	 * @param int $MinValue
	 * @param string $TimeMode
	 * @param int $MaxValue
	 */
	protected function EnableOfflineTimer( int $MinValue=10, string $TimeMode='m',int $MaxValue=null){
		$this->EnableTimer('offline', $TimeMode, $MinValue, $MaxValue, 'OFFLINE_TIMER');
	}
	/**
	 * 
	 */
	protected function StopTimer(){
		$t=$this->UpdateTimer;
		if($t['enabled'])$this->SetTimerInterval($t['ident'], 0);
		$t=$this->OfflineTimer;
		if($t['enabled'])$this->SetTimerInterval($t['ident'], 0);
		$this->SetBuffer('UPDATE_TIMER',0);
	}
	/**
	 * @param boolean $enabled
	 * @param int $timer
	 * @param string $mode
	 */
	protected function StartTimer(bool $startForce=false,int $seconds=0, string $mode='update'){
		if( ( !$this->UpdateTimer['enabled'] && !$this->OfflineTimer['enabled']) || // No Timer enabled 
			intval($this->GetBuffer('UPDATE_TIMER')) > 0 // Timer Running
		) return;
		$t=$mode=='update'?$this->UpdateTimer:$this->OfflineTimer;
		$c=$seconds==0 && $t['enabled']?$this->ReadPropertyInteger($t['ident']):0;
		if($c==0&&$seconds==0)return;
		if(!$startForce)$startForce=$this->CheckStatus();
		if($startForce){
			if($seconds==0){
				if($t['tm']=='m')$seconds=$c*60;
				else if($t['tm']=='h')$seconds=$c*60*60;
			}
			if($t['enabled']){
				$this->SetTimerInterval($t['ident'], $seconds*1000);
				$this->SetBuffer('UPDATE_TIMER',1);
			}
		}
	}
	protected function GetTimerFormElements(){
		$e=[];
		$t=$this->UpdateTimer;
		if($t['enabled']){
			$suffix=['s'=>'seconds','m'=>'minutes','h'=>'hours'][$t['tm']];
			$suffix=$this->Translate($suffix). ' '.$t['min'].'-'.($t['max']?$t['max']:'x');
			$suffix.=' | 0='.$this->Translate('off');
			$e[]=['type'=>'NumberSpinner','name'=>$t['ident'], 'caption'=>'Update Timer', 'suffix'=>$suffix ];
		}
		$t=$this->OfflineTimer;
		if($t['enabled']){
			$suffix=['s'=>'seconds','m'=>'minutes','h'=>'hours'][$t['tm']];
			$suffix=$this->Translate($suffix). ' '.$t['min'].'-'.($t['max']?$t['max']:'x');
			$suffix.=' | 0='.$this->Translate('off');
			$e[]=['type'=>'NumberSpinner','name'=>$t['ident'],'caption'=>'Offline Timer', 'suffix'=>$suffix ];
		}
		return $e;
		
	}
	/******************************************************
	 * Status
	 ******************************************************/
	protected function ValidateStatus(){
		$this->SendDebug(__FUNCTION__,'called',0);
	}
	protected function CheckOnline() {
		$this->SendDebug(__FUNCTION__,'called',0);
	}
	protected function CheckStatus($debug=false, $checkConnection=false) {
		$i=IPS_GetInstance($this->InstanceID);
		$ok=$i['InstanceStatus']==102;
		if(!$ok && $debug)$this->SendDebug(__FUNCTION__, "Invalid Status: ".$i['InstanceStatus'], 0);
		if($ok && $checkConnection){
			$ok=$i['ConnectionID']>0;
			if(!$ok && $debug)$this->SendDebug(__FUNCTION__, "Instance not connected", 0);
		}
		return true;
	}
	protected function GetStatus($checkConnection=false){
		return IPS_GetInstance($this->InstanceID)['InstanceStatus'];
	}
	
	/******************************************************
	 * Props
	 ******************************************************/
	
	 /******************************************************
	 *  ValidatePropertys wird aufgerufen in baseModule
	 *  ApplyChanges() bevor  parent::ApplyChanges() 
	 *  aufgerufen wird
	 ******************************************************/
	
	protected function ValidatePropertys(){
		$t=$this->UpdateTimer;
		if($t['enabled'] && ($v=$this->ReadPropertyInteger($t['ident'])))$this->ValidatePropValue($t['ident'],$v,$t['min'],$t['max']);
		$t=$this->OfflineTimer;
		if($t['enabled'] && ($v=$this->ReadPropertyInteger($t['ident'])))$this->ValidatePropValue($t['ident'],$v,$t['min'],$t['max']);
	}
	protected function ValidatePropValue(string $propName, int $minValue, $maxValue ){
		$save=!$this->HasChanges();
		if($v=$n=$this->ReadPropertyInteger($propName)){
			if($v<0)$n=0;elseif($v && $v<$minValue)$n=$minValue; else if($maxValue!==null && $v>$maxValue)$n=$maxValue;
			if($v!=$n){
				IPS_SetProperty($this->InstanceID, $propName, $n);
				if($save)IPS_ApplyChanges($this->InstanceID);
			}
		}
	}
	
	protected function GetProps(){
		return $this->ReadPropertyInteger('ModuleProps');// intval($this->GetBuffer('MY_PROPS'));
	}
	/**
	 * @param int $props Device or Module Propertys
	 * @param boolean $Update If true then autocreate and delete used and unused vars
	 * @param boolean $FromDevice This is true if call comes from Device Props
	 * @return boolean True if Props changes
	 */
	protected function SetProps(int $props, $Update=true, $FromDevice=false){
		if($props!=$this->GetProps()){
			if($Update)$this->UpdateDeviceProps($props, $Update,$Update);
			$save= !IPS_HasChanges($this->InstanceID);
			IPS_SetProperty($this->InstanceID, 'ModuleProps', $props);
			if($save)IPS_ApplyChanges($this->InstanceID);
			//$this->SetBuffer('MY_PROPS',$props);
			return true;	
		}
	}
	protected function UpdateDeviceProps($props, $AutoCreate=true, $AutoRemove=true){
		if(!$AutoCreate && !$AutoRemove)return false;
		foreach ($this->prop_names as $prop=>$ident){
			$add=(bool)($props & $prop);
			$id=@$this->GetIDForIdent($ident);
			if(!$id && $add && $AutoCreate){
				$id=$this->CreateVarByIdent($ident, false);
			}elseif($id && !$add && $AutoRemove){
				if($this->GetPropDef($ident)[DEF_ACTION]==1)
					$this->DisableAction($ident);
				$this->UnregisterVariable($ident);
			}
		}
		return true;
	}
	/******************************************************
	 * Variables
	 ******************************************************/
	protected function GetValueByIdent($Ident){
		if($id=@$this->GetIDForIdent($Ident)){
			return GetValue($id);
		}else $this->SendDebug(__FUNCTION__, "Ident ->$Ident<- not found", 0);
	}
	protected function SetValueByIdent($Ident,$Value, $force=true, $AutoCreate=true ){
		if(!($id=@$this->GetIDForIdent($Ident)) && $AutoCreate){
			$id=$this->CreateVarByIdent($Ident,false);
		}
		if($id){
			$changed=GetValue($id)!=$Value;
			if($force || $changed){
				SetValue($id, $Value);
				return $changed;
			}else return false;
		}else IPS_LogMessage(__CLASS__, __FUNCTION__."::Ident ->$Ident<- not found");
	}
	protected function CreateVarByIdent($Ident, $CheckIdent=true){
//   IPS_LogMessage('THIS', 'create var '.$Ident."::".($CheckIdent?'true':'false'));		
 		if($CheckIdent && !$this->ValidIdent($Ident,true))return 0;
		$id=@$this->GetIDForIdent($Ident);
		if(!$id){
			if(!($def=$this->GetPropDef($Ident))){
				$this->SendDebug(__FUNCTION__, "Prop ->$Ident<- not defined", 0);
				return 0;
			}
			if($this->BeforeVarCreate($Ident, $def)===false)return 0;
			$profile=$def[DEF_PROFILE];
			if(!empty($profile) && !IPS_VariableProfileExists($profile)){
				require_once 'utils.inc';
				if(!$this->CreateMissedProfile($profile)){
					IPS_LogMessage(__CLASS__, "Profile $profile for $Ident missing, set to none");
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
			}
// IPS_LogMessage('THIS', 'var '.$Ident."::".var_export($def,true));		
			if($def[DEF_ICON])IPS_SetIcon($id, $def[DEF_ICON]);
			if($def[DEF_ACTION]==1)$this->EnableAction($Ident);
		}
		if($id)$this->AfterVarCreate($Ident);
		return $id;
	}
	protected function ValidIdent($Ident, $Silent=false){
		if(!in_array($Ident,$this->prop_names)){
			if(!$Silent)$this->SendDebug(__FUNCTION__,"Invalid Ident $Ident",0);
			return false;
		}
		if(!($def=$this->GetPropDef($Ident))){
			$this->SendDebug(__FUNCTION__, "Prop ->$Ident<- not defined", 0);
			return false;
		}
		$props=$this->GetProps();
		if(($props & $def[DEF_PROP]) == 0 ){
			if(!$Silent)$this->SendDebug(__FUNCTION__,"($props) Device dos not support $Ident ({$def[DEF_PROP]}) control",0);
			return false;
		}
		return true;
	}
	protected function CreateMissedProfile($name){
		IPS_LogMessage(__CLASS__, __FUNCTION__."::Profile ->$name<- not found");
	}
	
	protected abstract function GetPropDef($Ident);
	protected function AfterVarCreate($Ident){}
	protected function BeforeVarCreate($Ident, &$def){}
 	protected $prop_names = [];
	/******************************************************
	 * Hooks
	 ******************************************************/
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
			return $hookname;
		}else IPS_LogMessage(get_class($this),'ERROR Instance WebHook not found');
		return null;
	}
	protected function ProcessHookData(){}

}
CONST
	DEF_TYPE = 0,
	DEF_NAME = 1,
	DEF_PROFILE = 2,
	DEF_POS = 3,
	DEF_ICON = 4,
	DEF_PROP = 5,
	DEF_ACTION = 6

;
?>