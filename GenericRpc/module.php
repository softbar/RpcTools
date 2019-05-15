<?
require_once __DIR__.'/../libs/rpc_module.inc';

/**
 * @author Xavier
 *
 */
class GenericRpcModule extends RPCModule {
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
		$save = parent::ApplyChanges();	
		if($save){
			IPS_ApplyChanges($this->InstanceID);
			return;
		}
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
		
		
		$f=['elements'=>[
			[ "type"=> "Select", "name"=> "View", 	"caption"=> "Options" , "options"=>[
					["value"=>0, "label"=>"Settings"],	
					["value"=>1, "label"=>"Functionlist"]
				]
			]
		]];
		$view = $this->ReadPropertyInteger('View');
		$elements=$actions=null;
		if($view == 0){
			$elements=[
				[ "type"=> "ValidationTextBox", "name"=> "Host", 	"caption"=> "Host" ],
				[ "type"=> "ValidationTextBox", "name"=> "User", 	"caption"=> "Username" ],
				[ "type"=> "PasswordTextBox", "name"=> "Pass", 	"caption"=> "Password" ],			
			];
			$values=$getFormValues(false);
			$actions=[
				["name"=>"method","type"=>"Select", "caption"=>"Select method", "options"=>$values],
 				["name"=>"params","type"=>"ValidationTextBox", "caption"=>"Commaseperated Args"],
 				["type"=>"Button", "caption"=>"Execute","onClick"=>"if(empty(\$method))echo 'please select method first';else echo var_export(RPCG_CallMethod(\$id,\$method,\$params),true);"],
			];
		}
		else if( $view == 1){
			$values=$getFormValues(true);
			$actions=[
				["name"=>"devices","type"=>"List",
			   	 	"add"=>false,"delete"=>false,
					"columns"=>[
				  		["name"=>"s", "caption"=>"Service","width"=>"150px"],
				 		["name"=>"f", "caption"=>"Function","width"=>"200px"],
						["name"=>"a", "caption"=>"Arguments", "width"=> "auto"]
				 	],
					"values"=>$values
				],
 				["name"=>"params","type"=>"ValidationTextBox", "caption"=>"Commaseperated Args"],
 				["type"=>"Button", "caption"=>"Execute","onClick"=>"if(empty(\$devices))echo 'please select method first';else if(!empty(\$devices['a'])&&(\$params==''||count(explode(',',\$params))!=count(explode(',',\$devices['a'])) ) )echo 'Invalid argument count, check your argument input!'; else echo var_export(RPCG_CallMethod(\$id,\$devices['s'].'.'.\$devices['f'],\$params),true);"],
			];
		}
		if($elements)$f['elements']=array_merge($f['elements'],$elements);
		if($actions)$f['actions']=$actions;
		$f["status"]=[
	        [ "code"=> 102, "icon"=> "active", "caption"=> "Connection ready" ],
	        [ "code"=> 200, "icon"=> "error", "caption"=> "Host missing or invalid" ],
	        [ "code"=> 201, "icon"=> "error", "caption"=> "Missing or invalid Rpc Config File , see Expert Settings" ]
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
	
	/*
	 * Protected Overide Section
	 */
	protected function GetDiscoverDeviceOptions(){
		return OPT_MINIMIZED;
	}
	
	/*
	 * Private Section
	 */

	protected function GetPropDef($Ident){}
}
?>