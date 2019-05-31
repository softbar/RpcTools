<?

require_once __DIR__.'/../libs/rpc_module.inc';
/**
 * @author Xavier
 *
 */
class GenericRpc extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();
		$this->registerPropertyString('File','');
		$this->RegisterPropertyBoolean('FileMode',false);
		$this->RegisterPropertyInteger('View',0);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ApplyChanges()
	 */
	public function ApplyChanges() {
		parent::ApplyChanges();	
	}
 	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
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
		$f['actions']=[
				["name"=>"devices","type"=>"List",
			   	 	"add"=>false,"delete"=>false,
					"columns"=>[
				  		["name"=>"s", "caption"=>"Service","width"=>"150px"],
				 		["name"=>"f", "caption"=>"Function","width"=>"200px"],
						["name"=>"a", "caption"=>"Arguments", "width"=> "auto"]
				 	],
					"values"=>$getFormValues()
				],
 				["name"=>"params","type"=>"ValidationTextBox", "caption"=>"Commaseperated Args"],
 				["type"=>"Button", "caption"=>"Execute","onClick"=>"if(empty(\$devices))echo 'please select method first';else if(!empty(\$devices['a'])&&(\$params==''||count(explode(',',\$params))!=count(explode(',',\$devices['a'])) ) )echo 'Invalid argument count, check your argument input!'; else echo var_export(RPCGENERIC_CallMethod(\$id,\$devices['s'].'.'.\$devices['f'],\$params),true);"],
		];
		
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
	 * @see BaseRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		return OPT_MINIMIZED;
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate() {}

}
?>