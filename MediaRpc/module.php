<?php
require_once __DIR__.'/../libs/rpc_module.inc';

class MediaRpc extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value){
		if(parent::RequestAction($Ident, $Value))return true;
		$this->WriteValue($Ident,$Value);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::Destroy()
	 */
	public function Destroy(){
		parent::Destroy();
		if( $this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120MED}')){
			if (IPS_VariableProfileExists('RPC_PlayState'))IPS_DeleteVariableProfile('RPC_PlayState');
 			if (IPS_VariableProfileExists('RPC_Bass_Treble'))IPS_DeleteVariableProfile('RPC_Bass_Treble');
		}
	}
	/**
	 * @param string $Ident
	 * @param string $Value
	 * @return void|NULL
	 */
	public function WriteValue(string $Ident, string $Value){
		$this->StopTimer();
		$ok=($this->GetDeviceState()<2 && $this->CheckOnline())?true:null;
		if($ok){
			$ok=($this->ValidIdent($Ident=strtoupper($Ident),true))?$this->_writeValue($Ident, $Value):null;
		}
		$this->StartTimer();
		return $ok;
	}
	/**
	 *
	 */
	public function RequestUpdate(){
		$this->RunUpdate();
	}
	
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[10,'s'],'OFFLINE_INTERVAL'=>[5,'m']];
	protected $showRefreshButton=true;
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		return OPT_MINIMIZED|OPT_PROPS_ONLY;
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::ApplyDeviceProps()
	 */
	protected function ApplyDeviceProps($Props){
		$this->SetProps($Props,true);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		$myProps=$this->GetProps();
		foreach ($this->prop_names as $prop=>$ident){
			if($myProps&$prop){
				$this->_readValue($ident);
			}
		}
	}	
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::CreateMissedProfile()
	 */
	protected function CreateMissedProfile($name){
		if($name=='RPC_PlayState'){
			UTILS::RegisterProfileIntegerEx($name, 'Remote', '', '', [
				[0, $this->Translate('Stop'), '', -1],
				[1, $this->Translate('Play'), '', -1],
				[2, $this->Translate('Pause'), '', -1],
				[3, $this->Translate('Next'), '', -1],
				[4, $this->Translate('Previous'), '', -1]
			]);
			return true;
		}else if($name=='RPC_Bass_Treble'){
			UTILS::RegisterProfileInteger($name, 'Music', '', '', -10, 10, 1);
			return true;
		}
		IPS_LogMessage(IPS_GetName($this->InstanceID), __FUNCTION__."::Profile ->$name<- not found");
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		switch($Ident){
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
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_VOLUME_CONTROL=>'VOLUME',PROP_MUTE_CONTROL=>'MUTE',PROP_TREBLE_CONTROL=>'TREBLE',PROP_BASS_CONTROL=>'BASS',PROP_LOUDNESS_CONTROL=>'LOUDNESS',PROP_BRIGHTNESS_CONTROL=>'BRIGHTNESS',PROP_CONTRAST_CONTROL=>'CONTRAST',PROP_SHARPNESS_CONTROL=>'SHARPNESS',PROP_COLOR_CONTROL=>'COLOR',PROP_SOURCE_CONTROL=>'SOURCE',PROP_PLAY_CONTROL=>'PLAYSTATE',PROP_CONTENT_BROWSER=>'CONTENT'];

	// --------------------------------------------------------------------------------
	private function _readValue(string $Ident){
		$r=null;
		if(!$this->CreateApi())return null;
		switch($Ident){
			case 'VOLUME'	: $r=$this->CallApi('RenderingControl.GetVolume', []);break;
			case 'MUTE'		: $r=$this->CallApi('RenderingControl.GetMute', []);break;
			case 'BASS'		: $r=$this->CallApi('RenderingControl.GetBass', []);break;
			case 'TREBLE'	: $r=$this->CallApi('RenderingControl.GetTreble', []);break;
			case 'LOUDNESS'	: $r=$this->CallApi('RenderingControl.GetLoudness', []);break;
			case 'BRIGHTNESS':$r=$this->CallApi('GetBrightness', []);break;
			case 'SHARPNESS': $r=$this->CallApi('GetSharpness', []);break;
			case 'CONTRAST'	: $r=$this->CallApi('GetContrast', []);break;
			case 'COLOR'	: $r=$this->CallApi('GetColor', []);break;
			case 'PLAYSTATE': $r=$this->CallApi('GetPlayState', []);if($r>2)$r=0; break;
			default:$r=null;
		}
		if(!is_null($r))$this->SetValueByIdent($Ident,$r);
		return $r;
	}	
	private function _writeValue(string $Ident, string $Value){
		if(!$this->CreateApi())return null;
		switch($Ident){
			case 'VOLUME'	: $ok=$this->CallApi('RenderingControl.SetVolume', [0,null,$Value=(int)$Value]);break;
			case 'MUTE'		: $ok=$this->CallApi('RenderingControl.SetMute', [0,null,$Value=(bool)$Value]);break;
			case 'BASS'		: $ok=$this->CallApi('RenderingControl.SetBass', [0,null,$Value=(int)$Value]);break;
			case 'TREBLE'	: $ok=$this->CallApi('RenderingControl.SetTreble', [0,null,$Value=(int)$Value]);break;
			case 'LOUDNESS'	: $ok=$this->CallApi('RenderingControl.SetLoudness', [0,null,$Value=(bool)$Value]);break;
			case 'BRIGHTNESS':$ok=$this->CallApi('SetBrightness', [0,$Value=(int)$Value]);break;
			case 'SHARPNESS': $ok=$this->CallApi('SetSharpness', [0,$Value=(int)$Value]);break;
			case 'CONTRAST'	: $ok=$this->CallApi('SetContrast', [0,$Value=(int)$Value]);break;
			case 'COLOR'	: $ok=$this->CallApi('SetColor', [0,$Value=(int)$Value]);break;
			case 'PLAYSTATE': if(($ok=$this->CallApi('SetPlayState', [0,$Value=(int)$Value]))!==false){$Value=$ok;$ok=true;}break;
			default:$ok=null;
		}
		if(!is_null($ok))$this->SetValueByIdent($Ident,$Value);
		return $ok;
	}
}

?>