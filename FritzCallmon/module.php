<?php
require_once __DIR__ . '/../libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
/**
 *
 * @author Xavier
 *        
 */
class FritzCallmon extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create ();
		$this->RegisterPropertyBoolean ( 'ShowMessageButton', false );
		$this->RegisterPropertyBoolean ( 'ShowDialButton', false );
		$this->RegisterPropertyString  ( "DialPort", "" );
		$this->RegisterPropertyInteger ( 'DialLines', 0 );
		$this->RegisterPropertyBoolean ( 'Calllist', true );
		$this->RegisterPropertyInteger ( 'CallLines', 10 );
		$this->RegisterPropertyString  ( "Columns", "Icon, Date, Name, Caller" );
		$this->RegisterPropertyBoolean ( 'ShowHeader', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnRing', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnCall', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnConnect', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnDisconnect', true );
		$this->RegisterPropertyBoolean ( "CallType_1", false );
		$this->RegisterPropertyBoolean ( "CallType_2", true );
		$this->RegisterPropertyBoolean ( "CallType_3", false );
		$this->RegisterPropertyBoolean ( "CallType_4", true );
		$this->RegisterPropertyBoolean ( "CallType_5", false );
		$this->RegisterPropertyBoolean ( "CallType_6", false );
		$this->RegisterPropertyBoolean ( "CallType_9", false );
		$this->RegisterPropertyBoolean ( "CallType_10", false );
		$this->RegisterPropertyBoolean ( "CallType_11", false );
	}
	public function Destroy(){
		parent::Destroy();
		$this->RegisterHook(__CLASS__.$this->InstanceID, false);
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
	public function GetConfigurationForParent(){
		return json_encode(['Host'=>parse_url($this->ReadPropertyString('Host'),PHP_URL_HOST),'Port'=>1012]);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ReceiveData()
	 */
	public function ReceiveData($JSONString) {
		
		$this->SendDebug(__FUNCTION__,utf8_decode($JSONString),0);
		$this->doDecodeCall(json_decode($JSONString,true)['Buffer']);
	}
	
	public function RequestUpdate(){
// 		$this->ReDiscover();
		$this->RunUpdate();
	}
	
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[1,'h'],'OFFLINE_INTERVAL'=>[12,'h']];
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=[
				'X_AVM-DE_OnTel1.GetInfo','X_AVM-DE_OnTel1.GetCallList',
 				'X_AVM-DE_TAM1.GetList','X_AVM-DE_TAM1.GetInfo','X_AVM-DE_TAM1.GetMessageList','X_AVM-DE_TAM1.DeleteMessage',
				'X_AVM-DE_Dect1.GetDectListPath','X_AVM-DE_Dect1.GetNumberOfDectEntries','X_AVM-DE_Dect1.GetGenericDectEntry',
				'X_VoIP1.X_AVM-DE_DialGetConfig','X_VoIP1.X_AVM-DE_DialSetConfig','X_VoIP1.X_AVM-DE_DialNumber','X_VoIP1.X_AVM-DE_DialHangup',
				'DeviceConfig1.X_AVM-DE_CreateUrlSID'
		];
		return [OPT_MINIMIZED+OPT_SMALCONFIG,$filter,':49000/tr64desc.xml'];
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return 'FritzCallmon ('.parse_url($host,PHP_URL_HOST).')';
	}	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		if(!($r=$this->CallApi('X_AVM-DE_OnTel1.GetCallList')))return;
		$xml = @simplexml_load_file ( $r );
		if ($xml === false) {
			IPS_LogMessage ( IPS_GetObject ( $this->InstanceID ) ['ObjectName'], "Fehler beim laden der callList! $r" );
			return false;
		}
		$xml = new simpleXMLElement ( $xml->asXML () );
		$timelimit = 0;
// 		if ($id = $this->ReadPropertyInteger ( "TimeLimit" )) $timelimit = GetValue ( $id );
		$callList = array ();
		foreach ( $xml->Call as $call ) {
			if ($timelimit > 0) { // filter calls by timestamp
				$parts = preg_split ( '/[ .:]/', ( string ) $call->Date );

				$callTimestamp = mktime ( ( int ) $parts [3], ( int ) $parts [4], 0, ( int ) $parts [1], ( int ) $parts [0], ( int ) $parts [2] );

				if ($callTimestamp < $timelimit) continue;
			}

			$callList [] = $call;
		}		
		$messageList=null;
        for ($i=0;$i<5;$i++){
            $GetInfo = $this->CallApi('X_AVM-DE_TAM1.GetInfo',["NewIndex"=>$i]);
            if ($GetInfo["NewName"] <> ""){
                $URL = $this->CallApi('X_AVM-DE_TAM1.GetMessageList',["NewIndex"=>$i]);
                $xml = @simplexml_load_file($URL);
                if ($xml === false){
                    IPS_LogMessage(IPS_GetName($this->InstanceID),__FUNCTION__. "::Error load Messages!");
                    return false;
                }
                $xml = new simpleXMLElement($xml->asXML());
                $messageList[40+$i]=$xml;
            }
        }
		for($i = 0; $i < count ( $callList ); $i ++) {
			// Switch numbers if needed
			if (( int ) $callList [$i]->Type == 3) {
				$tmp = ( string ) $callList [$i]->Caller;
				$callList [$i]->Caller = ( string ) $callList [$i]->Called;
				$callList [$i]->Called = $tmp;
			}

			// Clear own number for example ISDN: POTS: SIP: etc...
			$callList [$i]->Called = str_replace ( strtoupper ( ( string ) $callList [$i]->Numbertype ) . ": ", "", ( string ) $callList [$i]->Called );
			$callList [$i]->addChild ( "AB" ); // create empty message entry

			if ((( int ) $callList [$i]->Port >= 40) && (( int ) $callList [$i]->Port <= 44)) {
				$callList [$i]->Type = 6; // set message deleted as preset
		
				if (strlen ( ( string ) $callList [$i]->Path ) != 0) { // get same message from calllist
					$message = empty($messageList [( int ) $callList [$i]->Port])?null:$messageList [( int ) $callList [$i]->Port];
					$callList [$i]->Duration = "---";
					if(!empty($message->Message)){
// $this->SendDebug(__FUNCTION__, "found Message => ".print_r($message['Message'],true), 0);						
						$message=$message->Message;
						$callList [$i]->addChild ( "Tam" );
						$callList [$i]->Tam->addAttribute ( "index", ( string ) $message[0]->Tam);
						$callList [$i]->Tam->addChild ( "TamIndex", ( string ) $message[0]->Index);
						$callList [$i]->Type = ($message[0]->New == "1" ? "4" : "5");
						$callList [$i]->AB = "1";
						if ($message[0]['New'] == "1") {
							$callList [$i]->Path = "?path=" . urlencode ( ( string ) $callList [$i]->Path ) . "&tam=" .  $message[0]->Tam . "&index=" . $message[0]->Index . "&action=mark";
						} else {
							$callList [$i]->Path = "?path=" . urlencode ( ( string ) $callList [$i]->Path );
						}
						
					}else $callList [$i]->Path ='';
				}
			}
		}
		$this->RenderCallList($callList);
        
		
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
// 		$this->SendDebug(__FUNCTION__, $Ident, 0);
		switch($Ident){
 			case $this->prop_names[PROP_CALLLIST]: return [3,'Callerlist','~HTMLBox',0,'Telephone',PROP_CALLLIST,6];
			case $this->prop_names[PROP_MISSED]	: return [1,'Missed calls','',1,'Talk',PROP_MISSED,7];
			case $this->prop_names[PROP_MESSAGES]: return  [1,'New messages','',2,'Talk',PROP_MESSAGES,8];
			default : $m=null;if(preg_match('/line_(\d)/i',$Ident,$m))return [3,'Status line '.($m[1]+1),'',0,'HollowDoubleArrowRight',constant('PROP_LINE_'.$m[1]),$m[1]];

		}
	}
	protected $prop_names = [PROP_CALLLIST=>'CALLLIST',PROP_MISSED=>'MISSED',PROP_MESSAGES=>'MESSAGES',PROP_LINE_0=>'LINE_0',PROP_LINE_1=>'LINE_1',PROP_LINE_2=>'LINE_2',PROP_LINE_3=>'LINE_3',PROP_LINE_4=>'LINE_4',PROP_LINE_5=>'LINE_5'	];
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::ProcessHookData()
	 */
	protected function ProcessHookData() {	
		if(parent::ProcessHookData())return true;
		$this->SendDebug(__FUNCTION__,print_r($_GET,true),0);
		if(isset($_GET['cmd'])){
			if($_GET['cmd']=='message'){
		       if (isset ($_GET['path'])){
		            $sid = $this->CallApi('DeviceConfig1.X_AVM-DE_CreateUrlSID');
		            $file = $this->ReadPropertyString("Host").":49000".urldecode($_GET['path'])."&".$sid;
		            $this->SendDebug(__FUNCTION__,"Send File => $file",0);
		            @header("Content-type: audio/wave");
		            @header('Content-Transfer-Encoding: binary');
		            @header('Cache-Control: must-revalidate');
		            readfile($file);
		        }
			}
			else if($_GET['cmd']=='dial'){
				if(isset($_GET['number'])){
					$this->doDial($_GET['number']);
					
				}
				 @header("Content-type: text/javascript");
				 echo PHP_EOL;
			}
 		}
	}	

	
	// --------------------------------------------------------------------------------
	
	private function updateProps(){
		$props=PROP_CALLLIST+PROP_MISSED+PROP_MESSAGES ;
		if($lines=$this->ReadPropertyInteger('DialLines'))
			for($j=0;$j<$lines;$j++) $props+=constant("PROP_LINE_$j");			
		$this->SetProps($props);
		if($this->ReadPropertyBoolean('ShowDialButton')||$this->ReadPropertyBoolean('ShowMessageButton')){
			$this->RegisterHook(__CLASS__.$this->InstanceID, true);
		}
	}
	private function doDial(string $Number){
		$DialPort=$this->ReadPropertyString('DialPort');
		
		if(!($api=$this->CreateApi()))return null;
       	$dialConfig = $api->__call("X_VoIP1.X_AVM-DE_DialGetConfig",[]);
       	if($api->LastError())return null;
        if(empty($dialConfig) || $dialConfig == "unconfigured")
            $dialConfig = "";
 
       	if(empty($DialPort) && empty($dialConfig)){
           IPS_LogMessage(__CLASS__,__FUNCTION__. "::Fehler: Anruf an '".$Number."' nicht möglich, da kein Ausgangsport gesetzt ist!");
           return false;
        }
		$DialPort='DECT: '.$DialPort;
        $this->SendDebug(__FUNCTION__, "Number $Number on ".($DialPort?$DialPort:$dialConfig), 0);
        if($DialPort && $DialPort!=$dialConfig){
	        $api->__call("X_VoIP1.X_AVM-DE_DialSetConfig",[$DialPort]);
	       	if($api->LastError())return null;
			sleep(0.5);
        }
		$result = $api->__call("X_VoIP1.X_AVM-DE_DialNumber",[$Number]);
        if($DialPort && $DialPort!=$dialConfig){
        	sleep(0.5);
        	$api->__call("X_VoIP1.X_AVM-DE_DialSetConfig",[$dialConfig]);
        }
        if($api->LastError())return null;
        return $result;
    }
	
	
	/*********************************************************************
	 * Render Basics
 	 *********************************************************************/	
	private function RenderHTML($detailDIV,$msgDIV,$linkDIV, $extendedDIV='', $childDIV=''){
           return "<div class=\"ipsContainer container nestedEven ipsVariable\" style=\"border-color: rgba(255,255,255,0.15); border-style: solid; border-width: 0 0 1px;\">
	<div class=\"content tr\">
		<div class=\"title td\" style=\"width: 100%\">
			<div style=\"min-width: 300px; width: 100%;\">".$detailDIV."</div>
		</div>
		<div class=\"visual td\">$msgDIV</div>
		<div class=\"link td\">$linkDIV</div>
	</div>
	<div class=\"extended empty\">$extendedDIV</div>
	<div class=\"childContainers empty\">$childDIV</div>
</div>";
	}
	private function RenderHead($columns, array $widths=[]){
		$i=0;$columnWidth = 100/count($columns)."%";
		$detailDIV='';
		foreach($columns as $column){
			$col=strtolower($column);
			$width=isset($widths[$col])? 'width: '.$widths[$col].';':'';
			if ($i < count ( $columns ))
				$style = "float: left; width: $columnWidth; overflow-x: hidden; margin-right: 10px;";
			else 
				$style = "float: none; width: auto; overflow-x: hidden;";
			$detailDIV .= "<div style=\"$style$width\">".$this->Translate($column)."</div>";
		}
		return $this->RenderHTML($detailDIV,'','');
	}
	private function RenderDialScript(){
		return "<script>
	function dial(obj) {
		var messagePath = window.location.origin+'/hook/phone$this->InstanceID/?cmd=dial&number='+obj.getAttribute('data-nr');
		//console.log(messagePath);
		var s=document.createElement('SCRIPT');
		s.src=messagePath;
		try{document.body.appendChild(s)} catch(e){}
		window.setTimeout(function(){document.body.removeChild(s)},100);
    }
</script>";
	}
	private function RenderDialDiv($callnumber){
		$buttonVisibility = strlen($callnumber) > 0 ? "visible" : "hidden";
		return '<div class="ipsContainer text colored" style="background-color: rgba(255, 255, 255, 0.3); visibility: '.$buttonVisibility.";\" title=\"$callnumber\" data-nr=\"$callnumber\" onclick=\"dial(this);\">".$this->Translate('Dial')."</div>";
	}
   	private function RenderCallList($callList=null) {
        $missedCallsCounter = 0;
        $messageCounter = 0;
		$showMessages=$this->ReadPropertyBoolean('ShowMessageButton');
		$showDial=$this->ReadPropertyBoolean('ShowDialButton');
		$HTML='';
		$hookURL="/hook/".__CLASS__.$this->InstanceID."/";
		if($showMessages){
	        $HTML.= "<script>
	                    function playMessage(obj) {
	                        var messagePath = window.location.origin+'$hookURL'+obj.getAttribute('data-messagepath')+'&cmd=message';
	                        console.warn(messagePath);
	                        
	                        if(messagePath.length > 0) {
	                            var audio = new Audio(messagePath);
	                            audio.play();
	                        }
	                    }
	                 </script>";
        }
        if($showDial)$HTML.= $this->RenderDialScript();
        $maxCaller = $this->ReadPropertyInteger('CallLines');
		if($maxCaller<1)$maxCaller=1;
        if($callList != null) {    
	        $columns = explode(",", str_replace(" ", "", IPS_GetProperty($this->InstanceID, "Columns")));
	        $columnWidth = 100/count($columns)."%";
			if($this->ReadPropertyBoolean('ShowHeader'))$HTML.=$this->RenderHead($columns);
            $props=['',$this->ReadPropertyBoolean("CallType_1"),$this->ReadPropertyBoolean("CallType_2"),
            		$this->ReadPropertyBoolean("CallType_3"),$this->ReadPropertyBoolean("CallType_4"),
            		$this->ReadPropertyBoolean("CallType_5"),$this->ReadPropertyBoolean("CallType_6"),
            		false,false,$this->ReadPropertyBoolean("CallType_9"),
            		$this->ReadPropertyBoolean("CallType_10"),$this->ReadPropertyBoolean("CallType_11")
            ];
            
            foreach ($callList as $call) {
                $proceed = false;
                if($props[1] && $call->Type == 1)
                    $proceed = true;
                else if($props[2] && $call->Type == 2) {
                    $missedCallsCounter++;
                    $proceed = true;
                }else if($props[3] && $call->Type == 3)
                    $proceed = true;
                else if($props[4] && $call->Type == 4) {
                    $messageCounter++;
                    $proceed = true;
                }else if($props[5] && $call->Type == 5)
                    $proceed = true;
                else if($props[6] && $call->Type == 6)
                    $proceed = true;
                else if($props[9] && $call->Type == 9)
                    $proceed = true;
                else if($props[10] && $call->Type == 10) {
                    $missedCallsCounter++;
                    $proceed = true;
                }else if($props[11] && $call->Type == 11)
                    $proceed = true;
                if(!$proceed || $maxCaller<1)
                    continue;
				$maxCaller--;
                      
                $rowIcon = "";
                switch ($call->Type) {
                    case '1':
                        $rowIcon = "ipsIconHollowArrowRight";
                        break;
                    case '2':
                        $rowIcon = "ipsIconCross";
                        break;
                    case '3':
                        $rowIcon = "ipsIconHollowArrowLeft";
                        break;
                    case '4':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '5':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '6':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '9':
                        $rowIcon = "ipsIconHollowArrowRight";
                        break;
                    case '10':
                        $rowIcon = "ipsIconCross";
                        break;
                    case '11':
                        $rowIcon = "ipsIconHollowArrowLeft";
                        break;
                }
                $detailDIV = "";
                $i=1;

                $callname=empty(trim((string)$call->Name))?'Unknown':(string)$call->Name;
                $callnumber=trim((string)$call->Caller);
// 				$called=trim((string)$call->Called);
// 				$ <CalledNumber>53333995</CalledNumber>
                
                foreach ($columns as $column) {
                    if($i < count($columns))
                        $style = "float: left; width: ".$columnWidth."; overflow-x: hidden; margin-right: 10px;";
                    else
                        $style = "float: none; width: auto; overflow-x: hidden;";
                    
                    switch (strtolower($column)) {
                        case 'icon':
                            $detailDIV .= "<div class=\"icon td ".$rowIcon."\" style=\"".$style." height: 35px; width: 35px !important;\"></div>";
                            break;
                        case 'name':
                            $detailDIV .= "<div style=\"".$style."\">$callname</div>";
                            break;
                        default:
                            $detailDIV .= "<div style=\"".$style."\">".$call->$column."</div>";
                            break;
                    }
                    $i++;
                }
                $dialDIV=$showDial?$this->RenderDialDiv($callnumber):'';
             	$buttonVisibility = strlen(trim($call->Path)) > 0 ? "visible" : "hidden";
                $msgDIV=$showMessages?'<div class="ipsContainer text colored" style="background-color: rgba(255, 255, 255, 0.3); visibility: '.$buttonVisibility.';" data-id="'.$call->Id."\" data-messagepath=\"".$call->Path."\" onclick=\"playMessage(this);\">".$this->Translate('Play')."</div>":'';
                $HTML .=$this->RenderHTML($detailDIV, $msgDIV.$dialDIV,'');            }    
        }
        $this->SetValueByIdent("MISSED", $missedCallsCounter);
        $this->SetValueByIdent("MESSAGES", $messageCounter);
        $this->SetValueByIdent("CALLLIST", $HTML);
    }
	
	private function doDecodeCall($data){
		if($this->GetStatus()!=102){
			IPS_LogMessage(IPS_GetName($this->InstanceID), __FUNCTION__.'::Error Instance not Ready, skip call info');
			return;
		}
		if(!($cfg=json_decode($this->GetBuffer('CALL_INFO'),true)))$cfg=[null,null,0,0,0];
		$arr =explode(';',$data.';');
		array_shift($arr);//$date =array_shift($arr); 
		$cmd =array_shift($arr);
		$line =array_shift($arr);
		$txt=$msnInfo=$nameInfo=null;
		switch($cmd){
			case 'RING':
				$quelle=array_shift($arr);
				$ziel=array_shift($arr);
				$txt=sprintf($this->Translate( "Call from %s for %s"),$quelle,$ziel);
				
			case 'CALL':	
				if(empty($txt)){
					array_shift($arr);
					$ziel=array_shift($arr);
					$quelle=array_shift($arr);
					$txt=sprintf($this->Translate("Call from %s to %s"),$quelle,$ziel);
				}
		// 		if($nameInfo=self::PersonInfo($quelle))$a.=" ({$nameInfo['name']})";
		// 		if($msnInfo=self::MsnInfo($ziel))$b.=" ({$msnInfo['name']})";
		 		$cfg[$line]=[$nameInfo, $msnInfo, $quelle,$ziel,$cmd];
				break;
			case 'CONNECT':
				array_shift($arr); // Unknown Value 10
				$txt=$this->Translate("%s connected with %s");
			case 'DISCONNECT': 
				list($nameInfo, $msnInfo, $quelle, $ziel,$ident)=$cfg[$line];
				if(!$txt){
					$dauer=(int)array_shift($arr);
					if($dauer > 3600)$dauer-=3600;
					$dauer=$dauer > 3600 ? date('h:i:s',$dauer):date('i:s',$dauer);
					$txt=$this->Translate($ident=='RING'?'Incoming':'Outgoing')." ".$this->Translate("connection %s with %s closed. Duration $dauer");
					unset($cfg[$line]);
				}	
				$a=$quelle;	$b=$ziel;
				if($nameInfo)$a.=" ({$nameInfo['name']})";
				if($msnInfo)$b.=" ({$msnInfo['name']})";
				$txt=sprintf($txt,$a,$b);
				break;
			
		}
		$this->SendDebug(__FUNCTION__, $txt, 0);
		if($cmd!='CONNECT')$this->SetBuffer('CALL_INFO',json_encode($cfg));
		
		if($maxLines=$this->ReadPropertyInteger('DialLines')){	
		
			if($line < $maxLines){
				$this->SetValueByIdent('LINE_'.$line, $txt);
			}
		}
			
	
		if( ($cmd=='RING' && $this->ReadPropertyBoolean('UpdateOnRing')) || 
			($cmd=='CALL' && $this->ReadPropertyBoolean('UpdateOnCall')) ||
			($cmd=='CONNECT' && $this->ReadPropertyBoolean('UpdateOnConnect')) ||
			($cmd=='DISCONNECT' && $this->ReadPropertyBoolean('UpdateOnDisconnect'))
		) $this->RunUpdate();

			
		
		

		return $txt;
	}
    
	
}
CONST
	PROP_CALLLIST  		= 16,
	PROP_MISSED  		= 32,
	PROP_MESSAGES  		= 64,

	PROP_LINE_0 = 128,
	PROP_LINE_1 = 256,
	PROP_LINE_2 = 512,
	PROP_LINE_3 = 1024,
	PROP_LINE_4 = 2048,
 	PROP_LINE_5 = 4096;

?>