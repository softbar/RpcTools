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
 		$getFormValues=function ($asList=true){
			$values=[];
			if($d=$this->GetDeviceConfig()){
				foreach($d[D_SERVICES] as $sn=>$s){
					foreach($s[S_FUNCS] as $fn=>$args){
						$values[]=$asList?['s'=>$sn,'f'=>$fn,'a'=>$args]:['label'=>"$sn.$fn","value"=>"$sn.$fn"];
					}
				}
			}
			return $values;
	 	};
		$f=json_decode(parent::GetConfigurationForm(),true);
		$f['actions'][0]['values']=$getFormValues();
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
	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
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
}
?>