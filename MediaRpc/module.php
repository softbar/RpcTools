<?
require_once __DIR__.'/../libs/rpc_module.inc';

class MediaRpcModule extends RPCModule {

	/**
	 * @param string $function
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($function,$arguments){
		return $this->CallApi($function, $arguments);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Destroy()
	 */
	public function Destroy(){
		parent::Destroy();
		if($this->IsLastInstance('{5638FDC0-C112-4108-DE00-201905120MED}')){ // Self
 			if (IPS_VariableProfileExists('RPC_PlayState'))IPS_DeleteVariableProfile('RPC_PlayState');
 			if (IPS_VariableProfileExists('RPC_Bass_Treble'))IPS_DeleteVariableProfile('RPC_Bass_Treble');
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
  		$e=$this->GetTimerFormElements();
  		if(count($e)==0)return;
		$f = json_decode(file_get_contents(__DIR__.'/form.json'),true);
		$f['elements']=array_merge($f['elements'],$e);
		return json_encode($f);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value){
		if(parent::RequestAction($Ident, $Value))return true;
		$this->WriteValue($Ident,$Value);
	}
	/**
	 * 
	 */
	public function RequestUpdate(){
 		$this->DoUpdate();
	}
	/**
	 * @param string $Ident
	 * @return NULL
	 */
	public function ReadValue(string $Ident){
		$this->StopTimer();
		$r=$this->_readValue($Ident);
		$this->StartTimer();
		return $r;
	}
	/**
	 * @param string $Ident
	 * @param string $Value
	 * @return void|NULL
	 */
	public function WriteValue(string $Ident, string $Value){
		$this->StopTimer();
		$ok=$this->_writeValue($Ident, $Value);
		$this->StartTimer();
		return $ok;
	}
	
	/*
	 * Protected Override Section
	 */
	/**
	 * @return integer
	 */
	protected function GetDiscoverDeviceOptions(){
		return OPT_MINIMIZED|OPT_PROPS_ONLY;
	}
	/**
	 * @param int $props
	 * @return boolean
	 */
	protected function ProcessUpdate(){
 		$this->DoUpdate();
	}
	protected function ProcessOffline(){
 		$this->SendDebug(__FUNCTION__, 'CheckStatus => '.($this->CheckStatus()?'true':'false'), 0);
	}
	/*
	 * Private Section
	 */
	private function DoUpdate(){
		if($state=$this->GetState())return $state;
		$this->SendDebug(__FUNCTION__, 'Process start', 0);		
		$this->StopTimer();
		foreach(array_values($this->prop_names) as $ident)if($this->ValidIdent($ident,true))$this->_readValue($ident,false);
		$this->StartTimer();
		$this->SendDebug(__FUNCTION__, 'Process finishd', 0);
	}
	private function _readValue(string $Ident, $CheckIdent=true){
		$r=null;$Ident=strtoupper($Ident);
		if( ($CheckIdent && !$this->ValidIdent($Ident))|| !$this->CreateApi())return null;
		switch($Ident){
			case 'VOLUME': $r=$this->CallApi('RenderingControl.GetVolume', []);break;
			case 'MUTE': $r=$this->CallApi('RenderingControl.GetMute', []);break;
			case 'BASS': $r=$this->CallApi('RenderingControl.GetBass', []);break;
			case 'TREBLE': $r=$this->CallApi('RenderingControl.GetTreble', []);break;
			case 'LOUDNESS': $r=$this->CallApi('RenderingControl.GetLoudness', []);break;
			case 'BRIGHTNESS': $r=$this->CallApi('GetBrightness', []);break;
			case 'SHARPNESS': $r=$this->CallApi('GetSharpness', []);break;
			case 'CONTRAST': $r=$this->CallApi('GetContrast', []);break;
			case 'COLOR': $r=$this->CallApi('GetColor', []);break;
			case 'PLAYSTATE': $r=$this->CallApi('GetPlayState', []);if($r>2)$r=0; break;
			default:$r=null;
		}
		if($r!==null)$this->SetValueByIdent($Ident,$r);
		return $r;
	}	
	private function _writeValue(string $Ident, string $Value){
		$Ident=strtoupper($Ident);
		if(!$this->ValidIdent($Ident)|| !$this->CreateApi())return ;
		switch($Ident){
			case 'VOLUME'	: $Value=(int)$Value;$ok=$this->CallApi('RenderingControl.SetVolume', [null,null,$Value]);break;
			case 'MUTE'		: $Value=(bool)$Value;$ok=$this->CallApi('RenderingControl.SetMute', [null,null,$Value]);break;
			case 'BASS'		: $Value=(int)$Value;$ok=$this->CallApi('RenderingControl.SetBass', [null,null,$Value]);break;
			case 'TREBLE'	: $Value=(int)$Value;$ok=$this->CallApi('RenderingControl.SetTreble', [null,null,$Value]);break;
			case 'LOUDNESS'	: $Value=(bool)$Value;$ok=$this->CallApi('RenderingControl.SetLoudness', [null,null,$Value]);break;
			case 'BRIGHTNESS':$Value=(int)$Value;$ok=$this->CallApi('SetBrightness', [0,$Value]);break;
			case 'SHARPNESS': $Value=(int)$Value;$ok=$this->CallApi('SetSharpness', [0,$Value]);break;
			case 'CONTRAST'	: $Value=(int)$Value;$ok=$this->CallApi('SetContrast', [0,$Value]);break;
			case 'COLOR'	: $Value=(int)$Value;$ok=$this->CallApi('SetColor', [0,$Value]);break;
			case 'PLAYSTATE':  $Value=(int)$Value;if(($ok=$this->CallApi('SetPlayState', [0,$Value]))!==false){$Value=$ok;$ok=true;}break;
			default:$ok=null;
		}
		if($ok)SetValue($this->GetIDForIdent($Ident),$Value);
		return $ok;
	}

	/******************************************************
	 * Variables Override
	 ******************************************************/
	protected function CreateMissedProfile($name){
		if($name=='RPC_PlayState'){
			@IPS_CreateVariableProfile($name,1);
			IPS_SetVariableProfileAssociation($name, 0, $this->Translate('Stop'), '', -1);
			IPS_SetVariableProfileAssociation($name, 1, $this->Translate('Play'), '', -1);
			IPS_SetVariableProfileAssociation($name, 2, $this->Translate('Pause'), '', -1);
			IPS_SetVariableProfileAssociation($name, 3, $this->Translate('Next'), '', -1);
			IPS_SetVariableProfileAssociation($name, 4, $this->Translate('Previous'), '', -1);
			IPS_SetVariableProfileIcon($name, 'Remote');
			return true;
		}else if($name=='RPC_Bass_Treble'){
			@IPS_CreateVariableProfile($name,1);
			IPS_SetVariableProfileValues($name, -10, 10,1);
			IPS_SetVariableProfileIcon($name, 'Music');
			return true;
		}
		IPS_LogMessage(__CLASS__, __FUNCTION__."::Profile ->$name<- not found");
	}
	protected function GetPropDef($Ident){
		switch(strtoupper($Ident)){
 			case $this->prop_names[PROP_VOLUME_CONTROL]: return  [1,'Volume','~Intensity.100',0,'Intensity',PROP_VOLUME_CONTROL,1];
			case $this->prop_names[PROP_MUTE_CONTROL]: return [0,'Mute','~Switch',0,'Speaker',PROP_MUTE_CONTROL,1];
			case $this->prop_names[PROP_TREBLE_CONTROL]: return [1,'Treble','RPC_Bass_Treble',0,'Music',PROP_TREBLE_CONTROL,1];
			case $this->prop_names[PROP_BASS_CONTROL]: return [1,'Bass','RPC_Bass_Treble',0,'Music',PROP_BASS_CONTROL,1];
			case $this->prop_names[PROP_LOUDNESS_CONTROL]: return [0,'Loudness','~Switch',0,'',PROP_LOUDNESS_CONTROL,1];
			case $this->prop_names[PROP_BRIGHTNESS_CONTROL]: return [1,'Brightness','~Intensity.100',0,'',PROP_BRIGHTNESS_CONTROL,1];
			case $this->prop_names[PROP_CONTRAST_CONTROL]: return [1,'Contrast','~Intensity.100',0,'',PROP_CONTRAST_CONTROL,1];
			case $this->prop_names[PROP_SHARPNESS_CONTROL]: return [1,'Sharpness','~Intensity.100',0,'',PROP_SHARPNESS_CONTROL,1];
			case $this->prop_names[PROP_COLOR_CONTROL]: return [1,'Color','~Intensity.100',0,'',PROP_COLOR_CONTROL,1];
			case $this->prop_names[PROP_SOURCE_CONTROL]: return [1,'Source','',0,'',PROP_SOURCE_CONTROL,1];
			case $this->prop_names[PROP_PLAY_CONTROL]: return [1,'Playstate','RPC_PlayState',0,'',PROP_PLAY_CONTROL,1];
// 			case $this->prop_names[PROP_CONTENT_BROWSER]: return [3,'Content','~HTMLBox',0,'',PROP_CONTENT_BROWSER,0];
		}
	}
	protected $prop_names = [PROP_VOLUME_CONTROL=>'VOLUME',PROP_MUTE_CONTROL=>'MUTE',PROP_TREBLE_CONTROL=>'TREBLE',PROP_BASS_CONTROL=>'BASS',PROP_LOUDNESS_CONTROL=>'LOUDNESS',PROP_BRIGHTNESS_CONTROL=>'BRIGHTNESS',PROP_CONTRAST_CONTROL=>'CONTRAST',PROP_SHARPNESS_CONTROL=>'SHARPNESS',PROP_COLOR_CONTROL=>'COLOR',PROP_SOURCE_CONTROL=>'SOURCE',PROP_PLAY_CONTROL=>'PLAYSTATE',PROP_CONTENT_BROWSER=>'CONTENT'];
}
?>