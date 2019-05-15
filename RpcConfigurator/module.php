<?php 
require_once __DIR__.'/../libs/discover.inc';

class RpcConfiguratorModule extends IPSModule{
	private $GenericGuid= '{5638FDC0-C112-4108-DE00-201905120GEN}';
	private $MediaGuid 	= '{5638FDC0-C112-4108-DE00-201905120MED}';
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();	
		if(count(IPS_GetInstanceListByModuleID('{5638FDC0-C112-4108-DE00-201905120000}'))>1){
			echo $this->Translate("Only one Insance of Rpc Configurator allowed");
			return null;
		}
		$this->RegisterPropertyBoolean('ShowOptions', true);
		$this->RegisterPropertyBoolean('ShowForm4', true);
		$this->RegisterPropertyInteger('CreateCat', 0);
		$this->RegisterPropertyBoolean('EnableCache', true);
		$this->RegisterPropertyString('BindIp', (($v=Sys_GetNetworkInfo()) && !empty($v[0]['IP']))?$v[0]['IP']:'');
		$this->RegisterPropertyInteger('DiscoverRetrys', 2);
		$this->RegisterPropertyInteger('DiscoverTimeout', 3);
		$this->RegisterPropertyBoolean('AllowManualImport', false);
		$this->RegisterPropertyString('DiscoverList', '');//file_exists(RPCTOOLS.'/discover/list.json')?file_get_contents(RPCTOOLS.'/discover/list.json'):'');
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ApplyChanges()
	 */
	public function ApplyChanges() {
		// TODO Auto-generated method stub
		parent::ApplyChanges();
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
		
		$list=json_decode($this->ReadPropertyString('DiscoverList'),true);
		$ips=[];
		if($v=Sys_GetNetworkInfo())foreach($v as $n)$ips[]=['label'=>$n['IP'],'value'=>$n['IP']];
		if(!is_array($list)||count($list)==0){
			$f = json_decode(file_get_contents(__DIR__.'/form0.json'),true);
			$f['elements'][2]["options"]=$ips;
			
			return json_encode($f);;
		}
		
		$ver=intval(IPS_GetKernelVersion());
		if( $ver < 5 || $this->ReadPropertyBoolean('ShowForm4')){
			$f = json_decode(file_get_contents(__DIR__.'/form4.json'),true);
			if($this->ReadPropertyBoolean('ShowOptions')){
				array_splice($f['elements'],1,null, [
						["name"=>"ShowForm4", "type"=>"CheckBox","caption"=>"Show old Format (for ipsconsole using)"],
						["name"=>"BindIp", "type"=>"Select","caption"=>"Bind Discover to IP", "options"=>$ips],
						["name"=>"CreateCat", "type"=>"SelectCategory","caption"=>"Create new in Category"],
						["name"=>"DiscoverRetrys", "type"=>"NumberSpinner","caption"=>"Search [ 1-10 ]", "suffix"=>"times"],
				 		["name"=>"DiscoverTimeout", "type"=>"NumberSpinner","caption"=>"Timeout [ 1-15 ]", "suffix"=>"seconds"],
					]
				);	
				unset($f['actions']);
			}else {
				
				$f['actions'][0]['values']=$this->GetFormDiscoverListValues($list,true);
			}
		}else{
			$f = json_decode(file_get_contents(__DIR__.'/form.json'),true);
			if($this->ReadPropertyBoolean('ShowOptions')){
				array_splice($f['elements'],1,null, [
						["name"=>"ShowForm4", "type"=>"CheckBox","caption"=>"Show old Format (for ipsconsole using)"],
						["name"=>"BindIp", "type"=>"Select","caption"=>"Bind Discover to IP", "options"=>$ips],
						["name"=>"CreateCat", "type"=>"SelectCategory","caption"=>"Create new in Category"],
						["name"=>"DiscoverRetrys", "type"=>"NumberSpinner","caption"=>"Retrys [1-10]"],
				 		["name"=>"DiscoverTimeout", "type"=>"NumberSpinner","caption"=>"Timeout [1-15]"],
					]
				);
				unset($f['actions']);
			}else {
				
				$f['actions'][0]['values']=$this->GetFormDiscoverListValues($list,false);
			}
		}
		
		

	
		return json_encode($f);
	}

	public function RequestAction($Ident, $Value) {
		if($Ident=='DISCOVER'){
			$Value=(bool)$Value;
			$list=$Value?[]:json_decode($this->ReadPropertyString('DiscoverList'),true);
			$this->BuildDiscoverList($list);
		}else 
		if($Ident=='CREATE'){
			if($data=json_decode($Value,true))$this->CeateDevice($data);
		}else 
		if($Ident=='ADD'){
//			if($data=json_decode($Value,true))$this->_ceateDevice($data);
			
		}
		else echo "Unknown Action ->$Ident<-";
	}
	public function ListDevices(){
		return $this->GetFormDiscoverListValues(json_decode($this->ReadPropertyString('DiscoverList'),true),false,false);
	}
	public function CreateGenericDevice(int $ListIndex){
		$list=$this->GetFormDiscoverListValues(json_decode($this->ReadPropertyString('DiscoverList'),true),false);
		if(!empty($list[$ListIndex])){
			$list[$ListIndex]['create']['moduleID']=$this->GenericGuid;
			$this->CeateDevice($list[$ListIndex]['create']);
			return true;
		}else IPS_LogMessage(__CLASS__, __FUNCTION__.":: Invalid ListIndex => $ListIndex");
	}
	public function CreateMediaDevice(int $ListIndex){
		$list=$this->GetFormDiscoverListValues(json_decode($this->ReadPropertyString('DiscoverList'),true),false);
		if(!empty($list[$ListIndex])){
			$list[$ListIndex]['create']['moduleID']=$this->MediaGuid;
			$this->CeateDevice($list[$ListIndex]['create']);
			return true;
		}else IPS_LogMessage(__CLASS__, __FUNCTION__.":: Invalid ListIndex => $ListIndex");
	}
	public function ClearCache(){
		DiscoverCleanUpCache();
	}
	
	private function GetFormDeviceValue($urls,$host=''){
		static $props_n=[PROP_VOLUME_CONTROL=>'vol',PROP_MUTE_CONTROL=>'mute',PROP_TREBLE_CONTROL=>'trebl',PROP_BASS_CONTROL=>'bass',PROP_LOUDNESS_CONTROL=>'loudn',PROP_BRIGHTNESS_CONTROL=>'brigh',PROP_CONTRAST_CONTROL=>'contr',PROP_SHARPNESS_CONTROL=>'sharp',PROP_COLOR_CONTROL=>'color',PROP_SOURCE_CONTROL=>'src',PROP_PLAY_CONTROL=>'play',PROP_CONTENT_BROWSER=>'cont'];
		$values=null;
		$options = $this->ReadPropertyBoolean('EnableCache')?OPT_USE_CACHE:0;
		$this->SendDebug(__FUNCTION__, sprintf($this->Translate('parse device urls=> %s'),is_array($urls)?implode(',',$urls):$urls), 0);
		$d=DiscoverDevice($urls,$options|OPT_MINIMIZED);
		if(count($d[D_SERVICES])==0){
			$this->SendDebug(__FUNCTION__,sprintf($this->Translate("no Services in %s found"),$d[D_NAME]),0);
			return;
		}
		$props=[];
		if($d[D_PROPS]==0){
			$props[]='GenericRpc';
		}else{
			foreach($props_n as $prop=>$n)if($d[D_PROPS] & $prop)$props[]=$n;
		}
		$props=implode(',',$props);
		$values=[
					'host'=>$d[D_HOST]|$host,
					'type'=>$d[D_TYPE],
					'info'=>$d[D_INFO],
					'props'=>$props,
					'urls'=>is_array($urls)?implode(',',$urls):$urls
		];
		$this->SendDebug(__FUNCTION__, sprintf($this->Translate('add device %s ( %s )'),$d[D_NAME],$props), 0);
		return $values;
	}
	private function findFormDeviceValue($new_value, $values){
		foreach($values as $v)
			if($v['host']==$new_value['host']&&$v['info']==$new_value['info'])return true;
	}
	private function BuildDiscoverList($values=[]){
		$this->SendDebug(__FUNCTION__, $this->Translate('started'), 0);
		$list = DiscoverNetwork(
				$this->ReadPropertyInteger('DiscoverTimeout'),
				$this->ReadPropertyInteger('DiscoverRetrys'),
				$this->ReadPropertyString('BindIp'),
				$this->InstanceID
		);		
		if(count($list)==0){
			$this->SendDebug(__FUNCTION__, $this->Translate("No devices found"), 0);
			return;
		}
		$this->SendDebug(__FUNCTION__, sprintf($this->Translate('detect %s network devices'),count($list)), 0);
		// Search for predefined devices
		$tmp=null;
		foreach ( $list as $id=>$found){
			// Adding Fritzbox tr64desc
			if(preg_match('/fritz!box/i', $found['server'])){
				$tmp=$found;
	 			$url=str_replace(parse_url($tmp['urls'][0],PHP_URL_PATH),'/tr64desc.xml',$tmp['urls'][0]);
	  			$tmp['urls']=[$url];
			}
			// Adding predefined XML devices
			else if(preg_match('/homematic/i', $found['server'])){
				array_push($list[$id]['urls'],'homematic.xml');
			}
			else if(preg_match('/[.\-\d]*vserver-\d+-bigmem.+upnp/i',$found['server'])){
				array_unshift($list[$id]['urls'],'enigma2.xml');
			}
		}
		if($tmp)$list[]=$tmp;

		// Now check devicese
		$check=count($values); // check duplicates if $values is NOT empty
		foreach($list as $found){
			if(!($dv=$this->GetFormDeviceValue($found['urls'],$found['host'])))continue;
			if($check>0){
				$ok=$this->findFormDeviceValue($dv, $values);
				if($ok)continue;
			}
			$values[]=$dv;
		}
		$found=count($values)-$check;
		$this->SendDebug(__FUNCTION__, sprintf($this->Translate($check>0?'found %s new devices':'found %s devices'),$found), 0);
		if($found>0){
			IPS_SetProperty($this->InstanceID, 'DiscoverList', json_encode($values));
			IPS_ApplyChanges($this->InstanceID);
		}
	}
	private function GetFormDiscoverListValues($list,$colored, $create=true){
		if(!is_array($list)||count($list)==0)return [];
		$modules = [];
		foreach (array_merge(IPS_GetInstanceListByModuleID($this->GenericGuid),IPS_GetInstanceListByModuleID($this->MediaGuid)) as $id){
			$hash=IPS_GetProperty($id, 'RpcHash');
			$modules[$hash]=$id;
		}
		foreach($list as $id=>$found){
			$list[$id]['status']=(Sys_Ping(parse_url($found['host'],PHP_URL_HOST),1))?'online':'offline';
			$hash=md5($found['info']);
			$list[$id]['instanceID']=empty($modules[$hash])?0:$modules[$hash];
			if($colored&& $list[$id]['instanceID']>0)$list[$id]['rowColor'] ='#C0FFC0';
			if($create){
				if($found['props']==''||$found['props']=='GenericRpc')
					$guid=$this->GenericGuid;
				else $guid=$this->MediaGuid;
				$list[$id]['create']=[ 
					"moduleID"=>$guid,
		            "configuration"=>["Host"=> $found['urls']]
				];
			}
			$list[$id]['itemid']=$id;
		}
		return $list;
	}
	private function CeateDevice($create) {
		$newInstanceId=IPS_CreateInstance($create['moduleID']);// GenericRpcModule
		if($ParentID=$this->ReadPropertyInteger('CreateCat'))IPS_SetParent($newInstanceId, $ParentID);
  		IPS_SetConfiguration($newInstanceId, json_encode($create['configuration']));
		IPS_ApplyChanges($newInstanceId);
		$this->SendDebug(__FUNCTION__,"Instance ".IPS_GetName($newInstanceId)." [$newInstanceId] created",0);	
 	}
 		
}
?>