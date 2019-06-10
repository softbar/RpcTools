<?php 
require_once __DIR__.'/../libs/discover.inc';

/**
 * @author Xaver
 *
 */
class RpcConfigurator extends IPSModule{
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();	
		if(count(IPS_GetInstanceListByModuleID('{5638FDC0-C112-4108-DE05-201905120000}'))>1){
			echo $this->Translate("Only one Instance of Rpc Configurator allowed");
			return null;
		}
		$this->RegisterPropertyBoolean('ShowOptions', false);
		$this->RegisterPropertyBoolean('ShowForm4', true);
		$this->RegisterPropertyBoolean('EnableCache', true);
		$this->RegisterPropertyString('BindIp', (($v=Sys_GetNetworkInfo()) && !empty($v[0]['IP']))?$v[0]['IP']:'');
		$this->RegisterPropertyInteger('DiscoverTimeout', 3);
		$this->RegisterPropertyBoolean('AllowManualImport', false);
		$this->RegisterPropertyString('User','');
		$this->RegisterPropertyString('Pass','');
		$this->RegisterPropertyString('DiscoverList', '');//file_exists(RPCTOOLS.'/discover/list.json')?file_get_contents(RPCTOOLS.'/discover/list.json'):'');
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ApplyChanges()
	 */
	public function ApplyChanges() {
		parent::ApplyChanges();
		$v=$nv=$this->ReadPropertyInteger('DiscoverTimeout');
		if($v<1)$nv=1;elseif($v>15)$nv=15;
		if($v!=$nv){
			IPS_SetProperty($this->InstanceID, 'DiscoverTimeout', $nv);
			IPS_ApplyChanges($this->InstanceID);
		}else if(!$this->ReadPropertyBoolean('EnableCache'))DiscoverCleanUpCache();
	}
		
	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
		$ips=[];
		$ver=intval(IPS_GetKernelVersion());
		if($ver>5)$ver=5;
		if($v=Sys_GetNetworkInfo())foreach($v as $n)$ips[]=['label'=>$n['IP'],'value'=>$n['IP']];
		$list=json_decode($this->ReadPropertyString('DiscoverList'),true);
		if(!is_array($list)||count($list)==0){
			$f = json_decode(file_get_contents(__DIR__."/form_startup$ver.json"),true);
			$f['elements'][2]["options"]=$ips;
 			return json_encode($f);;
		}
		
		if( $ver < 5 ){
			$f = json_decode(file_get_contents(__DIR__.'/form4.json'),true);
			$values = $this->RenderFormDiscoverList($list);
		}elseif($this->ReadPropertyBoolean('ShowForm4')){
			$f = json_decode(file_get_contents(__DIR__.'/form5_4.json'),true);
			$values = $this->RenderFormDiscoverList($list);
		}else{
			$f = json_decode(file_get_contents(__DIR__.'/form5.json'),true);
			$values = $this->RenderFormDiscoverList($list,false);
		}
		if($this->ReadPropertyBoolean('ShowOptions')){
			$e=[
					["name"=>"BindIp", "type"=>"Select","caption"=>"Bind Discover to IP", "options"=>$ips],
			 		["name"=>"DiscoverTimeout", "type"=>"NumberSpinner","caption"=>"Timeout [ 1-15 ]", "suffix"=>"seconds"],
					["name"=>"EnableCache", "type"=>"CheckBox","caption"=>"Enable Cache"],
					["name"=>"AllowManualImport","type"=>"CheckBox","caption"=>"Allow manual import"],
					["type"=>"Label","caption"=>"The following information is used if a device needs a login (FRITZ!Box ..)"],
					["name"=>"User", "type"=>"ValidationTextBox","caption"=>"Loginname"],
 					["name"=>"Pass", "type"=>"PasswordTextBox","caption"=>"Password"]
			];
			if($ver>=5)	array_unshift($e, ["name"=>"ShowForm4", "type"=>"CheckBox","caption"=>"Show old Format (for ipsconsole using)"]);
			array_splice($f['elements'],1,null, $e);	
			unset($f['actions']);
		}else{
			$f['actions'][0]['values']=$values;
			if(!$this->ReadPropertyBoolean('AllowManualImport')){
				if($ver<5){
					array_pop($f['actions']);
				}
				array_pop($f['actions']);
			}
		}
	
		
		return json_encode($f);
	}

	public function RequestAction($Ident, $Value) {
		if($Ident=='DISCOVER'){
			$Value=(bool)$Value;
			$this->BuildDiscoverList($Value?[]:json_decode($this->ReadPropertyString('DiscoverList'),true));
		}else 
		if($Ident=='CREATE'){
			if($data=json_decode($Value,true))$this->CeateDevice($data);
		}else 
		if($Ident=='ADD'){
			if(!empty($Value))$this->BuildDiscoverList(json_decode($this->ReadPropertyString('DiscoverList'),true),$Value);
		}
		else echo "Unknown Action ->$Ident<-";
	}
	public function ListDevices(){
		return json_decode($this->ReadPropertyString('DiscoverList'),true);
	}
	public function CreateGenericDevice(int $ListIndex){
		$list=$this->RenderFormDiscoverList(json_decode($this->ReadPropertyString('DiscoverList'),true),false);
		if(!empty($list[$ListIndex])){
			$list[$ListIndex]['create']['moduleID']=$this->GenGuid('GenericRpc');
			$this->CeateDevice($list[$ListIndex]['create']);
			return true;
		}else IPS_LogMessage(__CLASS__, __FUNCTION__.":: Invalid ListIndex => $ListIndex");
	}
	public function ClearCache(){
		DiscoverCleanUpCache();
	}
	
	private $BaseGuid = '{5638FDC0-C112-4108-DE05-%s}';
	private $Guids = [
		"GenericRpc"=>"201905120GEN",
		"MediaRpc"=>"201905120MED",
		"FritzStatus"=>"201905120FST",
		"FritzLog"=>"201905120FLO",	
		"FritzHomeAuto"=>"201905120FHA",	
		"FritzCallmon"=>"201905120FCM",
		"SamsungTVRemote"=>"20190531SAMR",
		"SonyAVRRemote"=>"20190531SOAV"	
	];
	private function GenGuid($guidName){
		return sprintf($this->BaseGuid, $this->Guids[$guidName]);
	}
	private function BuildDiscoverList($discoverList=[], $urls=''){
		if(empty($urls)){
			$this->SendDebug(__FUNCTION__, $this->Translate('started'), 0);
			$list = DiscoverNetwork(
					$this->ReadPropertyInteger('DiscoverTimeout'),
					1,
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
		} else { // Import Device from urls
			$this->SendDebug(__FUNCTION__, $this->Translate("Import from url")." => $urls", 0);
			if(stripos($urls,'http')!==0 && stripos($urls,'.xml')===false){
				$this->SendDebug(__FUNCTION__, $this->Translate("Invalid url"), 0);
				return;
			}
			if(	strpos($urls,',')===false && parse_url($urls,PHP_URL_PATH)==''){
				$this->SendDebug(__FUNCTION__, $this->Translate("Invalid url format"), 0);
				return;
			}
			$list[]=['urls'=>$urls,'host'=>''];
		}
		$GetFormDeviceValue=function($urls,$host=''){
			static $props_n=[PROP_VOLUME_CONTROL=>'vol',PROP_MUTE_CONTROL=>'mute',PROP_TREBLE_CONTROL=>'trebl',PROP_BASS_CONTROL=>'bass',PROP_LOUDNESS_CONTROL=>'loudn',PROP_BRIGHTNESS_CONTROL=>'brigh',PROP_CONTRAST_CONTROL=>'contr',PROP_SHARPNESS_CONTROL=>'sharp',PROP_COLOR_CONTROL=>'color',PROP_SOURCE_CONTROL=>'src',PROP_PLAY_CONTROL=>'play',PROP_CONTENT_BROWSER=>'cont',PROP_EVENTS=>'events'];
			$options = (int)$this->ReadPropertyBoolean('EnableCache');
	 		$this->SendDebug('BuildDiscoverList', sprintf($this->Translate('parse device urls=> %s'),is_array($urls)?implode(',',$urls):$urls), 0);
			$d=DiscoverDevice($urls,$options|OPT_MINIMIZED);
			if(count($d[D_SERVICES])==0){
	 			$this->SendDebug('BuildDiscoverList',sprintf($this->Translate("no Services in %s found"),$d[D_NAME]),0);
				return;
			}
			$props=[];
			if($d[D_PROPS]==0){
				$props[]='GenericRpc';
			}else{
				foreach($props_n as $prop=>$n)if($d[D_PROPS] & $prop)$props[]=$n;
			}
			$props=implode(',',$props);
			$value=[
						'host'=>$d[D_HOST]|$host,
						'type'=>$d[D_TYPE],
						'info'=>$d[D_INFO],
						'props'=>$props,
						'urls'=>is_array($urls)?implode(',',$urls):$urls
			];
	// 		$this->SendDebug(__FUNCTION__, sprintf($this->Translate('add device %s ( %s )'),$d[D_NAME],$props), 0);
			return $value;
		};
		$findFormDeviceValue=function ($value, $values){
			foreach($values as $v)
				if($v['host']==$value['host']&&$v['info']==$value['info']&&$v['type']==$value['type']&&$v['props']==$value['props'])return true;
		};
// 		$discoverList=[];
		// Now check devicese
		$check=count($discoverList); // check duplicates if $discoverList is NOT empty
		$fb_id = false;  // Fritzbox found id
		$sam_id= false;
		$sony_id=false;
		foreach($list as $found){
			if(!($dv=$GetFormDeviceValue($found['urls'],$found['host'])) || 
			  ($check>0 &&  $findFormDeviceValue($dv, $discoverList)) 
			)continue;
			if($dv['props']=='GenericRpc' && $dv['type']=='InternetGatewayDevice' && preg_match('/fritz!box/i',$dv['info']))$fb_id=count($discoverList);
			elseif(preg_match('/Sony(.*)STR-DN1050/',$dv['info']))$sony_id=count($discoverList);
			elseif(preg_match('/Samsung.*TV.*UE55F6400/',$dv['info']))$sam_id=count($discoverList);
			
			$discoverList[]=$dv;
		}
		if($fb_id!==false){
			$clone=$discoverList[$fb_id];
			foreach (['FritzStatus','FritzLog','FritzHomeAuto','FritzCallmon'] as $guidname){
				if(!IPS_ModuleExists($this->GenGuid($guidname)))continue;
				$clone['props']=$guidname;
				$clone['type']=str_replace('Fritz', '', $guidname);
				if($check==0 || !$findFormDeviceValue($clone, $discoverList))$discoverList[]=$clone;
			}
		}
		if($sam_id!==false){
			$clone=$discoverList[$sam_id];
			$guidname='SamsungTVRemote';
			if(IPS_ModuleExists($this->GenGuid($guidname))){
				$clone['props']=$guidname;
				$clone['type']='RemoteControl';
				if($check==0 || !$findFormDeviceValue($clone, $discoverList))$discoverList[]=$clone;
			}
		}
		if($sony_id!==false){
			$clone=$discoverList[$sony_id];
			$guidname='SonyAVRRemote';
			$clone['type']='RemoteControl';
			if(IPS_ModuleExists($this->GenGuid($guidname))){
				$clone['props']=$guidname;
				if($check==0 || !$findFormDeviceValue($clone, $discoverList))$discoverList[]=$clone;
			}
		}
		
		$found=count($discoverList)-$check;
		
		$this->SendDebug(__FUNCTION__, sprintf($this->Translate($check>0?'found %s new devices':'found %s devices'),$found), 0);
		if($found>0){
			IPS_SetProperty($this->InstanceID, 'DiscoverList', json_encode($discoverList));
			IPS_ApplyChanges($this->InstanceID);
		}
	}
	private function RenderFormDiscoverList($list,$colored='#C0FFC0', $create=true){
		if(!is_array($list)||count($list)==0)return [];
		$modules = [];
		foreach($this->Guids as $guidname=>$guid){
			
			$guid=sprintf($this->BaseGuid,$guid);
			
			$modules[$guidname]=[];
			foreach(IPS_GetInstanceListByModuleID($guid) as $id){
				$modules[$guidname][]=[
						'hash'=>@IPS_GetProperty($id, 'RpcHash'),
						'host'=>IPS_GetProperty($id, 'Host'),
						'id'=>$id
				];	
			}
		}
		$findModuleInstanceID=function($host,$guidname,$hash)use($modules){
			foreach($modules[$guidname] as $item){
				if(($item['hash']==''|| $item['hash']==$hash) && $item['host']==$host){
					return $item['id'];
				}
			}
			return 0;
		};
		$user=$this->ReadPropertyString('User');
		$pass=$this->ReadPropertyString('Pass');
		
		foreach($list as $id=>$item){
			$list[$id]['status']=(Sys_Ping(parse_url($item['host'],PHP_URL_HOST),1))?'online':'offline';
			if(empty($item['props']))$item['props']='GenericRpc';
			$guidname='MediaRpc';
			if(isset($this->Guids[$item['props']]))$guidname=$item['props'];
// $this->SendDebug(__FUNCTION__,$guidname,0);			
			$list[$id]['instanceID']=$findModuleInstanceID($item['host'],$guidname,md5($item['info']));
			if($colored&& $list[$id]['instanceID']>0)$list[$id]['rowColor']=$colored;
			if($create){
				$list[$id]['create']=[ 
					"moduleID"=>$this->GenGuid($guidname),
		            "configuration"=>["Host"=> $item['urls']]
				];
				if($guidname!='MediaRpc' &&  $guidname!='GenericRpc'){
					$list[$id]['create']["configuration"]['User']=$user;
					$list[$id]['create']["configuration"]['Pass']=$pass;
				}
			}
			$list[$id]['itemid']=$id;
		}
		return $list;
	}
	private function CeateDevice($create) {
		$newInstanceId=IPS_CreateInstance($create['moduleID']);
		IPS_SetConfiguration($newInstanceId, json_encode($create['configuration']));
		IPS_ApplyChanges($newInstanceId);
		$this->SendDebug(__FUNCTION__,"Instance ".IPS_GetName($newInstanceId)." [$newInstanceId] created",0);	
 	}
 		
}
?>