<?
require_once __DIR__.'/../libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
/**
 * @author Xavier
 *
 */
class GenericRpc extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create();
		$this->registerPropertyString('File','');
		$this->RegisterPropertyBoolean('FileMode',false);
		$this->RegisterPropertyInteger('View',0);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
 		$service_values=$event_values=[];
 		$no=$this->Translate('No');
		if($d=$this->GetDeviceConfig()){
			foreach($d[D_SERVICES] as $sn=>$s){
				if(!empty($s[S_EVENTS])){
					$event_values[]=['service'=>$sn, "events"=>$s[S_EVENTS],"enabled"=>$no];
				}
				foreach($s[S_FUNCS] as $fn=>$args){
					$service_values[]=['s'=>$sn,'f'=>$fn,'a'=>$args];
				}
			}
		}
		$f=json_decode(parent::GetConfigurationForm(),true);
		$f['actions'][0]['values']=$service_values;
		if($this->showEvents && count($event_values)>0){
			$id=count($f['actions'])-1;
			$f['actions'][$id]['values']=$event_values;
		} else array_pop($f['actions']);
		return json_encode($f);
	}
	/**
	 * @param string $MethodName
	 * @param string $Arguments
	 * @return mixed
	 */
	public function CallMethod(string $MethodName,string $Arguments){
// 	$this->SendDebug(__FUNCTION__, 'CALL args=> '.$Arguments,0);	
		return $this->CallApi($MethodName,$Arguments==''?[]:explode(',',$Arguments));
	}
	
	/** @brief Show Eventlist in Formular
	 * @var bool $showEvents
	 */
	protected $showEvents = false;
	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		if($this->showEvents) return  OPT_MINIMIZED|OPT_EVENTS;
		return OPT_MINIMIZED;
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate() {}

	protected function ApplyDeviceProps($Props,$doApply){
		return $this->SetProps($Props,true,$doApply);
	}
	
}
?>