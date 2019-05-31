<?
require_once __DIR__.'/../libs/rpc_module.inc';
define('MODULEDIR',__DIR__);
/**
 * @author Xavier
 *
 */
class FritzStatus extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create();
		// View 0
		$this->RegisterPropertyBoolean('ShowReboot', false);
		$this->RegisterPropertyBoolean('ShowReconnect', false);
		$this->RegisterPropertyBoolean('ShowWifiKey', false);
		
		$this->RegisterPropertyBoolean('Wifi2GActionEnabled', true);
		$this->RegisterPropertyBoolean('Wifi5GActionEnabled', true);
		$this->RegisterPropertyBoolean('WifiGuestActionEnabled', true);
		$this->RegisterPropertyString('WifiGuestKey', '');
        /* Profile erstellen */
		UTILS::RegisterProfileBooleanEx("RPC_InternetState", "Internet", "", "", [[false, $this->Translate('Disconnected'),"", 0xFF0000],[true, $this->Translate('Connected'),"", 0x00FF00]]);
        UTILS::RegisterProfileBooleanEx("RPC_NetPower", "Network", "", "", [[false, $this->Translate('Off'),"", 0xFF0000],[true, $this->Translate('On'),"", 0x00FF00]]);
        UTILS::RegisterProfileBooleanEx("RPC_YesNo", "Power", "", "", [[false, $this->Translate('No'),"", 0xFF0000],[true, $this->Translate('Yes'),"", 0x00FF00]]);
        UTILS::RegisterProfileInteger("RPC_Speed", "Speed", "", " MBit/s", 0,0,1);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::Destroy()
	 */
	public function Destroy() {
		parent::Destroy();
		if($this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FST}')){
			if(IPS_VariableProfileExists('RPC_InternetState'))IPS_DeleteVariableProfile('RPC_InternetState');
			if(IPS_VariableProfileExists('RPC_NetPower'))IPS_DeleteVariableProfile('RPC_NetPower');
			if(IPS_VariableProfileExists('RPC_YesNo'))IPS_DeleteVariableProfile('RPC_YesNo');
			if(IPS_VariableProfileExists('RPC_Speed'))IPS_DeleteVariableProfile('RPC_Speed');
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::ApplyChanges()
	 */
	public function ApplyChanges() {
		if(parent::ApplyChanges() && $this->GetStatus()==102){
			$this->updateProps();
			$this->RunUpdate();
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value){
		if(parent::RequestAction($Ident, $Value))return true;
		switch ($Ident){
			case $this->prop_names[PROP_RECONNECT] : $this->doReconnect();break;
			case $this->prop_names[PROP_REBOOT] : $this->doReboot();break;
			case $this->prop_names[PROP_WIFI2G] : $this->doSetWLANState(PROP_WIFI2G, $Value);break;
			case $this->prop_names[PROP_WIFI5G] : $this->doSetWLANState(PROP_WIFI5G, $Value);break;
			case $this->prop_names[PROP_WIFIGUEST] : $this->doSetWLANState(PROP_WIFIGUEST, $Value);break;
			default:
				IPS_LogMessage(IPS_GetName($this->InstanceID), __FUNCTION__."::Error unknown action ->$Ident<-");
		}
			
	}
	/**
	 * 
	 */
	public function RequestUpdate(){
		$this->RunUpdate();
	}
	
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[1,'h'],'OFFLINE_INTERVAL'=>[12,'h']];
	protected $requireLogin=[true,true];
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=[
				'WANCommonInterfaceConfig1.GetCommonLinkProperties', 
				'WANIPConnection1.GetExternalIPAddress', 	'WANIPConnection1.ForceTermination', 
				'WANPPPConnection1.GetExternalIPAddress',	'WANPPPConnection1.ForceTermination',
				'WLANConfiguration.GetInfo','WLANConfiguration.SetEnable',
				'DeviceConfig1.Reboot',
		];
		return [OPT_MINIMIZED+OPT_SMALCONFIG,$filter,':49000/tr64desc.xml'];
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return 'FritzStatus ('.parse_url($host,PHP_URL_HOST).')';
	}	
	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		$this->doUpdateBoxStatus();
		$this->doUpdateWlanStatus();
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		switch($Ident){
 			case $this->prop_names[PROP_EXTERNAL_IP]: return [3,'External IP-Adress','',0,'Internet',PROP_EXTERNAL_IP,0];
			case $this->prop_names[PROP_SPEED_DOWN]	: return [1,'Download Speed','RPC_Speed',0,'HollowArrowDown',PROP_SPEED_DOWN,0];
			case $this->prop_names[PROP_SPEED_UP]	: return [1,'Upload Speed','RPC_Speed',0,'HollowArrowUp',PROP_SPEED_UP,0];
			case $this->prop_names[PROP_ISTATE]		: return [0,'Internet State','RPC_InternetState',0,'',PROP_ISTATE,0];
			case $this->prop_names[PROP_RECONNECT]	: return [0,'Reconnect Internet','RPC_YesNo',0,'',PROP_RECONNECT,1];
			case $this->prop_names[PROP_REBOOT]		: return [0,'Reboot Fritzbox','RPC_YesNo',0,'',PROP_REBOOT,1];
			case $this->prop_names[PROP_WIFI2G]		: return [0,'WLAN: Internal 2.4 Ghz','RPC_NetPower',0,'',PROP_WIFI2G,(int)$this->ReadPropertyBoolean('Wifi2GActionEnabled')];
			case $this->prop_names[PROP_WIFI5G]		: return [0,'WLAN: Internal 5.0 Ghz','RPC_NetPower',0,'',PROP_WIFI5G,(int)$this->ReadPropertyBoolean('Wifi5GActionEnabled')];
			case $this->prop_names[PROP_WIFIGUEST]	: return [0,'WLAN: Guests','RPC_NetPower',0,'',PROP_WIFIGUEST,(int)$this->ReadPropertyBoolean('WifiGuestActionEnabled')];
			case $this->prop_names[PROP_WIFIGUEST_KEY]	: return [3,'WLAN guests key','',0,'',PROP_WIFIGUEST_KEY,0];
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_EXTERNAL_IP =>'EXTERNAL_IP',PROP_SPEED_DOWN => 'SPEED_DOWN',PROP_SPEED_UP => 'SPEED_UP',PROP_ISTATE => 'ISTATE',PROP_RECONNECT => 'RECONNECT',PROP_REBOOT => 'REBOOT',PROP_WIFI2G => 'WIFI2G',PROP_WIFI5G => 'WIFI5G',PROP_WIFIGUEST => 'WIFIGUEST',PROP_WIFIGUEST_KEY=>'WIFIGUEST_KEY'];

	// --------------------------------------------------------------------------------
	
	
   	private function doSetWLANState($wlanProp, $state) {
       	if(!$this->CreateApi())return null;
       	if($wlanProp==PROP_WIFI2G) $id=1;elseif($wlanProp==PROP_WIFI5G)$id=2;else if($wlanProp==PROP_WIFIGUEST)$id=3;
       	if($id && ($this->CallApi("WLANConfiguration$id.SetEnable",[$state]))){
        	$this->doUpdateWlanStatus($id);
       	}
    }  
    private function doReconnect(){
       	if(
       		($r=$this->CallApi('WANPPPConnection1.ForceTermination')) ||
       		($r=$this->CallApi('WANIPConnection1.ForceTermination')) 
       	)return $r;
    }
	private function doReboot(){
		if($r=$this->CallApi('DeviceConfig1.Reboot')){
			return $r;
		}
	}	
	private function doUpdateBoxStatus(){
		$info=[];
		if($r=$this->CallApi('WANCommonInterfaceConfig1.GetCommonLinkProperties'))$info=$r;
		if(isset($info['NewPhysicalLinkStatus'])){
			$v=($info['NewPhysicalLinkStatus']=='Up');
			if($this->SetValueByIdent('ISTATE', $v)){
				IPS_LogMessage(IPS_GetName($this->InstanceID), "Internet: ".$this->Translate($v?'Connected':'Disconnected'));
			}
		}
		if(isset($info['NewLayer1UpstreamMaxBitRate']))	$this->SetValueByIdent('SPEED_UP',  round($info['NewLayer1UpstreamMaxBitRate']/1000000, 1));
       	if(isset($info['NewLayer1DownstreamMaxBitRate']))$this->SetValueByIdent('SPEED_DOWN', round($info['NewLayer1DownstreamMaxBitRate']/1000000, 1));
     	
       	if(
       		($r=$this->CallApi('WANPPPConnection1.GetExternalIPAddress')) ||
       		($r=$this->CallApi('WANIPConnection1.GetExternalIPAddress')) 
      	) $this->SetValueByIdent('EXTERNAL_IP', $r);
	}
	private function doUpdateWlanStatus($id=0){
		if($id){
			if($r=$this->CallApi("WLANConfiguration$id.GetInfo")){
      			if($id==1)$id='WIFI2G';else if($id==2)$id='WIFI5G';else if($id==3)$id='WIFIGUEST';
				$this->SetValueByIdent($id,(bool)$r['NewEnable']);
			}
			return ;
		}
		if($r=$this->CallApi('WLANConfiguration1.GetInfo')){
        	$this->SetValueByIdent("WIFI2G",(bool)$r['NewEnable']);
		}
		if($r=$this->CallApi('WLANConfiguration3.GetInfo')){
        	$this->SetValueByIdent("WIFIGUEST",(bool)$r['NewEnable']);
			if($r=$this->CallApi('WLANConfiguration2.GetInfo')){
	        	$this->SetValueByIdent("WIFI5G",(bool)$r['NewEnable']);
			}
 		}elseif($r=$this->CallApi('WLANConfiguration2.GetInfo')){
        	$this->SetValueByIdent("WIFIGUEST",(bool)$r['NewEnable']);
		}
		
	}
	private function updateProps(){
		$props=PROP_EXTERNAL_IP+PROP_SPEED_DOWN+PROP_SPEED_UP+PROP_ISTATE+PROP_WIFI2G+PROP_WIFI5G+PROP_WIFIGUEST;
		if($k=$this->ReadPropertyBoolean('ShowWifiKey'))$props+=PROP_WIFIGUEST_KEY;
		if($this->ReadPropertyBoolean('ShowReboot'))$props+=PROP_REBOOT;
		if($this->ReadPropertyBoolean('ShowReconnect'))$props+=PROP_RECONNECT;
		$this->SetProps($props);
		if($k)$this->SetValueByIdent($this->prop_names[PROP_WIFIGUEST_KEY], $this->ReadPropertyString('WifiGuestKey'));
		$this->MaintainAction($this->prop_names[PROP_WIFI2G], $this->ReadPropertyBoolean('Wifi2GActionEnabled'));
		$this->MaintainAction($this->prop_names[PROP_WIFI5G], $this->ReadPropertyBoolean('Wifi5GActionEnabled'));
		$this->MaintainAction($this->prop_names[PROP_WIFIGUEST], $this->ReadPropertyBoolean('WifiGuestActionEnabled'));
	}	
}
CONST 
	PROP_EXTERNAL_IP = 1,
	PROP_SPEED_DOWN = 2,
	PROP_SPEED_UP = 4,
	PROP_ISTATE = 8,
	PROP_RECONNECT = 16,
	PROP_REBOOT = 32,
	PROP_WIFI2G = 64,
	PROP_WIFI5G = 128,
	PROP_WIFIGUEST = 256,
	PROP_WIFIGUEST_KEY = 512;

?>