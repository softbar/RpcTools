# RpcTools
RpcTools for IP-Symcon


# Configurator Module 

	Discover your network to found Upnp Rpc Devices
	Special Devices 
	1. Homematic CCU
	2. Enigma2 WebInterface
	3. Fritzbox (see Add One Modules)
  

Works:

The search of devices in the network takes between 5 and 180 seconds, 
depending on the timeout setting in the configurator and the number 
of found device.

The result is that the *local Windows* console seems to hang, but it is not like this :-(
In the *Webfront console*, you can track progress in the message log.
 
You can use **RpcConfigurator** to find and create new devices. 
If your device does not appear in the list and you have the *device 
description XML file URL* then you can either create a generic or multimedia
Module and enter the *full URL* as HOST. The module then tries to configure itself.
- NOTE When specifying multiple URLs, these are either separated as a string by comma or passed as a simple array

- Example urls
	- http://fritz.box:49000/tr64desc.xml
	- http://192.168.112.61:8000/serverxml.xml,http://192.168.112.61:8080/description.xml
	- homematic.xml 	for CCU
	- enigma2.xml 		for DreamBox Enigma2 WebInterface

All modules include timer functions for updating the status variables, with the exception of the RPCGENERIC module.
There are 2 timer modes *UPDATE* and *OFFLINE* which can each have separate values, this means when the device is *online* 
the status is updated every 15min, if it is *offline* (TV off) the check / update will take place only every 2 hours 
until the device is *online* again.

All module exports the functions
- **xxx_GetApi**(IpsInstanceID)		Returns an **RpcApi** object with which all further commands can be sent to the device.
- **xxx_GetApiInfo**(IpsInstanceID) Returns an array of informatons to the API


# Generic RPC Module 
	API to call all methods discovered from device only one Variable for Status created

This module also exports the function
- **RPCGENERIC_CallMethod**(IpsInstanceID, FunctionName, comma-separated string with parameters)

Works:

the syntax is the same as **$api**-> __ call (FunctionName, Parameter), with the difference that the parameters are passed not as an array but as a comma-separated string.
- **RPCGENERIC_CallApi**(IpsInstanceID, 'SetVolume', "0, Master, 10")
- **RPCGENERIC_CallApi**(IpsInstanceID, 'GetVolume', "0, Master")
or
- **$api** = **RPCGENERIC_GetApi**(IpsInstanceID)
- $volume = **$api**-> GetVolume (0, "Master")
- **$api**->SetVolume (0, "Master", $ volume)

Calling it via the *RpcApi* object **$api** is useful in any case when calling several, consecutive, calls, as it does not make sense, as with xxx_GetApi (IpsInstanceID), with each call the module is recreated.

Furthermore, it is possible to address calls directly to an service. Since some Rpc devices such
as the Fritzbox contains several functions of the same name, GetInfo(), it is
necessary to transfer the service name. This happens as follows
- **$api**->__ call ("DeviceInfo1.GetInfo", array with parameter)
or
- **$api**->{"DeviceInfo1.GetInfo"}(parameter,parameter...)


# Multimedia RPC Module 
	Includes Standard methods for
	1. Volume, Mute (Generic)
	2. Bass, Loudness, Trebble (sonos)
	3. Play,Pause,Stop,Next,Previous (Generic)
	4. Color,Brightness,Sharpness, Contrast (TV )

This module also exports the functions
- **RPCMEDIA_RequestUpdate**(IpsInstanceID) 				updates all status variables
- **RPCMEDIA_WriteValue**(IpsInstanceID,statusvar,value) 	set status variable

Works:

Since InstanceID is usually 0, this variable does not have to be specified, just
like Channel, these values are automatically added when called.

Therefore the call with **$api**->GetVolume() or **$api**->SetVolume(10) is also possible.

The volume can be changed or read as follows
- **$api**=**RPCMEDIA_GetApi**(IpsInstanceID)
- $volume=**$api**->GetVolume()
- **$api**->SetVolume(NewVolume)

or use the **RPCMEDIA_WriteValue** function to change status variables.
- **RPCMEDIA_WriteValue**IpsInstanceID, 'VOLUME', 10) sets the volume to 10
- **RPCMEDIA_WriteValue**(IpsInstanceID, 'PLAYSTATE', value) is used to control the playback, whereby the following values are possible: 
	- 0: Stop
	- 1: Pause
	- 2: Play
	- 3: Next
	- 4: Prevoius	


# Add One Modules
	- FritzStatus		Show Fritzbox status , enable switch of TAM, Wifi, Reboot,Reconnect 
	- FritzLog		Show Fritzbox systemlog
	- FritzCallmon		Show Fritzbox caller list, aktive calls , call lines and more
	- FritzHomeAuto		Fritzbox DECT actors, enable power switch and show Power,Energy,Temperature ... 
	- SamsungTVRemote	Enable network remote commands to Samung TV F-Series (UE55F6470) Use this to power off your TV
	
Unneeded plug-ins can be easily deleted, but will be reinstalled during an update

When using a Fritzbox add-on module, when creating manually, the specification of the host or the IP is sufficient.
As an example Host = http://fritz.box or 192.168.178.1

Note: To keep the cache device config files small, unneeded functions and status variables are ignored when processing the XML files.
If you want to dive deeper finds everything in the discrover.inc file, I also have the modules in the source code (for my purposes * laugh *) well documented.


# class `BaseRpcModule` {#class_base_rpc_module}

```
class BaseRpcModule
  : public IPSModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  Create()` | Register base propertys and profiles .
`public  ApplyChanges()` | Apply changes of instance configuration. If configuration not changed then calls [BaseRpcModule::RunUpdate()](#class_base_rpc_module_1a68a31c7c52465488fdfc3da36f31803f) .
`public  Destroy()` | Cleanup unsused profiles if is last instance of RpcTools .
`public  GetConfigurationForm()` | Load formular and handle Timers and Login fields .
`public  RequestAction( $Ident, $Value)` | Handle timer update events .
`public  MessageSink( $TimeStamp, $SenderID, $Message, $Data)` | Used to check Systemstartup and update each RpcTools device .
`protected  $timerDef` | Setup the Timers Overide this to configure Timers for Module ONLINE_INTERVAL checks when device is Online OFFLINE_INTERVAL checks when device is offline Parameters for Checks are [0,'s'] first are the minimum value 0 = timer disabled second are the multiplicator s = seconds, m = minutes and h are hours.
`protected  $requireLogin` | Setup login paramerters used in ApplyChanges and GetConfigurationForm.
`protected  $showLogin` | Setup login paramerters used in ApplyChanges and GetConfigurationForm.
`protected  $showRefreshButton` | If true then show config field in formulars to display a refresh button variable.
`protected  $online` | if not checked Null, true if online false if host not reachable
`protected  $state` | State cache 0=connected \| 1=disconnected \| 2=error.
`protected  $status` | Status cache.
`protected  $prop_names` | Contains a List of valid Propertys Key = Property value Value = Property ident name.
`protected  ValidateConfiguration()` | Validate current Configuration.
`protected  SetStatus( $Status)` | Set Module status .
`protected  GetStatus()` | Return current instance status.
`protected  GetDeviceState()` | 
`protected  SetDeviceState( $State)` | 
`protected  CheckOnline()` | Checks if host online and setup devicestate.
`protected  GetProps()` | Get the device propertys.
`protected  SetProps(int $props, $Update, $doApply)` | 
`protected  UpdateDeviceProps( $props, $AutoCreate, $AutoRemove)` | 
`protected  UpdateProps( $doApply)` | Update device property is called from ApplyChanges Overide in your own Module.
`protected  ValidateTimers()` | Checks if TimerValues are Ok This function is called from ApplyChanges and check timer values based on [BaseRpcModule::$timerDef](#class_base_rpc_module_1a5ad1b85667ffc1a42c03b909f1122427).
`protected  StopTimer()` | Stop current Timer.
`protected  StartTimer( $delay)` | Start timer based on [BaseRpcModule::$timerDef](#class_base_rpc_module_1a5ad1b85667ffc1a42c03b909f1122427).
`protected  RunUpdate()` | Main Update Caller , this will be call from TIMER, APPLY and SystemStartup.
`protected  ApplyHost( $host, $doApply)` | Checks and Setup given host.
`protected  GetValueByIdent( $Ident)` | 
`protected  SetValueByIdent( $Ident, $Value, $force, $AutoCreate)` | 
`protected  CreateVarByProp( $Prop)` | 
`protected  CreateVarByIdent( $Ident, $CheckIdent)` | 
`protected  ValidIdent( $Ident, $CheckProp)` | 
`protected  CreateMissedProfile( $name)` | 
`protected  GetPropDef( $Ident)` | Returns variable definitions Must overide in your own Module.
`protected  GetModuleName( $name, $host)` | 
`protected  IsLastInstance( $guid)` | 

## Members

### `public  Create()` {#class_base_rpc_module_1a2bb29ec41119710ce9366660be8880e1}

Register base propertys and profiles .

**See also**: IPSModule::Create()

### `public  ApplyChanges()` {#class_base_rpc_module_1a0ec6975ebd33aeb4248ec49e74913677}

Apply changes of instance configuration. If configuration not changed then calls [BaseRpcModule::RunUpdate()](#class_base_rpc_module_1a68a31c7c52465488fdfc3da36f31803f) .

**See also**: IPSModule::ApplyChanges()

### `public  Destroy()` {#class_base_rpc_module_1a2cfb3f524bc1c2c087acc060536d7cdb}

Cleanup unsused profiles if is last instance of RpcTools .

**See also**: IPSModule::Destroy()

### `public  GetConfigurationForm()` {#class_base_rpc_module_1a42c0cd9c8229505fbb5a6101d9d607bd}

Load formular and handle Timers and Login fields .

**See also**: [BaseRpcModule::GetConfigurationForm()](#class_base_rpc_module_1a42c0cd9c8229505fbb5a6101d9d607bd)

### `public  RequestAction( $Ident, $Value)` {#class_base_rpc_module_1abc8e0369d489b7301287fedc42e9a8c2}

Handle timer update events .

**See also**: IPSModule::RequestAction() 


#### Returns
bool True if ident is UPDATE

### `public  MessageSink( $TimeStamp, $SenderID, $Message, $Data)` {#class_base_rpc_module_1a141f5f553069f2967755380e3f4006c2}

Used to check Systemstartup and update each RpcTools device .

**See also**: IPSModule::MessageSink()

### `protected  $timerDef` {#class_base_rpc_module_1a5ad1b85667ffc1a42c03b909f1122427}

Setup the Timers Overide this to configure Timers for Module ONLINE_INTERVAL checks when device is Online OFFLINE_INTERVAL checks when device is offline Parameters for Checks are [0,'s'] first are the minimum value 0 = timer disabled second are the multiplicator s = seconds, m = minutes and h are hours.



### `protected  $requireLogin` {#class_base_rpc_module_1af154ebb51380c9f0ae59c3ce5707e3ca}

Setup login paramerters used in ApplyChanges and GetConfigurationForm.

if first true then check then that Username are given, seccond true checks for password

### `protected  $showLogin` {#class_base_rpc_module_1aaf9f0a21bd98eff89563f4702362c478}

Setup login paramerters used in ApplyChanges and GetConfigurationForm.

if first true then shows username field in formular, seccond true show password field

### `protected  $showRefreshButton` {#class_base_rpc_module_1ae69cbe62767276fafdc358e889f971d4}

If true then show config field in formulars to display a refresh button variable.



### `protected  $online` {#class_base_rpc_module_1a5bd71a14e99b2bef7ab3f7fb41204c0e}

if not checked Null, true if online false if host not reachable



### `protected  $state` {#class_base_rpc_module_1ae4041411f6bf360d3f6e3da42a7236ed}

State cache 0=connected | 1=disconnected | 2=error.



### `protected  $status` {#class_base_rpc_module_1a0d73ac4b107a069efbcfa9e4d8fad287}

Status cache.



### `protected  $prop_names` {#class_base_rpc_module_1ae9abb29319252561f8f79ed2c8bf65cb}

Contains a List of valid Propertys Key = Property value Value = Property ident name.



### `protected  ValidateConfiguration()` {#class_base_rpc_module_1a680e3cf6a8c260948d5b89836d377085}

Validate current Configuration.

#### Returns
bool True if nothing changed and Configuration is valid

### `protected  SetStatus( $Status)` {#class_base_rpc_module_1a66ed46afb82eda4f1f996be7e6540722}

Set Module status .

**See also**: IPSModule::SetStatus()

### `protected  GetStatus()` {#class_base_rpc_module_1a975a310d42a9ddfdde0504f27c813898}

Return current instance status.

#### Returns
int Instance Status

### `protected  GetDeviceState()` {#class_base_rpc_module_1ad29e3d08aa2f0ebd107a844a786cc434}



#### Returns
int State of device 0=connected|1=disconnected|2=error

### `protected  SetDeviceState( $State)` {#class_base_rpc_module_1af3ff1bfc850026f7dfce7117682a2eb8}



#### Parameters
* integer`$State` Valid states are 0=connected|1=disconnected|2=error

### `protected  CheckOnline()` {#class_base_rpc_module_1a4b772b4d29b4c92a1f77549fd410e67b}

Checks if host online and setup devicestate.

#### Returns
bool True if device is online

### `protected  GetProps()` {#class_base_rpc_module_1a76f10ae3e0d5a0831e878eddac87ddd5}

Get the device propertys.

/** 
#### Returns
int

### `protected  SetProps(int $props, $Update, $doApply)` {#class_base_rpc_module_1ab3c471daf7673d541778c283c029dcc3}



#### Parameters
* int`$props` Device or Module Propertys 


* bool`$Update` If true then autocreate and delete used and unused vars 


* bool`$doApply` If true then changes will Apply to Config , false is call comes out from Apply() function 





#### Returns
bool True if Props changes

### `protected  UpdateDeviceProps( $props, $AutoCreate, $AutoRemove)` {#class_base_rpc_module_1aa38d129f9c6a9ccfc6d14a3af625b358}



#### Parameters
* int`$props` Device or Module propertys 


* bool`$AutoCreate` if true then auto create missing variables 


* bool`$AutoRemove` if true then remove unused variables 





#### Returns
bool True if variables removed or created

### `protected  UpdateProps( $doApply)` {#class_base_rpc_module_1a0e2144ecacdb5a6f2cd215108c7a9ea0}

Update device property is called from ApplyChanges Overide in your own Module.

#### Parameters
* bool`$doApply` If true then changes are applyed to instance 





#### Returns
bool True is nothing changed

### `protected  ValidateTimers()` {#class_base_rpc_module_1a58f253958ada5909bf5ee813ad35cb5d}

Checks if TimerValues are Ok This function is called from ApplyChanges and check timer values based on [BaseRpcModule::$timerDef](#class_base_rpc_module_1a5ad1b85667ffc1a42c03b909f1122427).

#### Returns
bool True if timers valid

### `protected  StopTimer()` {#class_base_rpc_module_1a96282fd8f20b4d589a6de705ad9b3204}

Stop current Timer.



### `protected  StartTimer( $delay)` {#class_base_rpc_module_1a169c2a440628d6b1ce275e0eb9adceee}

Start timer based on [BaseRpcModule::$timerDef](#class_base_rpc_module_1a5ad1b85667ffc1a42c03b909f1122427).

#### Parameters
* number`$delay` if greater 0 then this will used to setup timer interval

### `protected  RunUpdate()` {#class_base_rpc_module_1a68a31c7c52465488fdfc3da36f31803f}

Main Update Caller , this will be call from TIMER, APPLY and SystemStartup.



### `protected  ApplyHost( $host, $doApply)` {#class_base_rpc_module_1aa05b4847aac9631a743a5fbb8f59ed2d}

Checks and Setup given host.

#### Parameters
* string`$host` Host url/ip to check 


* bool`$doApply` If true changes are applyed to module configuration

### `protected  GetValueByIdent( $Ident)` {#class_base_rpc_module_1aa7e058f12fe478c2a75576da8ee9c81a}



#### Parameters
* string`$Ident` Variable ident to get value 





#### Returns
NULL|mixed Returns the value for ident or NULL if ident invalid

### `protected  SetValueByIdent( $Ident, $Value, $force, $AutoCreate)` {#class_base_rpc_module_1a1f87b4e5788ab0565ee015336dd35d79}



#### Parameters
* string`$Ident` Variable ident to set value 


* mixed`$Value` Data to set 


* bool`$force` Set value if changed or not 


* bool`$AutoCreate` Create variable if not exists 





#### Returns
bool True if changed or false if ident not found or not changed

### `protected  CreateVarByProp( $Prop)` {#class_base_rpc_module_1a7a46587a7c8a921f8ecc1361afaeab75}



#### Parameters
* int`$Prop` Property to create Variable for 





#### Returns
int Variable Instance ID

### `protected  CreateVarByIdent( $Ident, $CheckIdent)` {#class_base_rpc_module_1a8636f513770e4624a958fe20d6221446}



#### Parameters
* string`$Ident` Variable ident to create 


* bool`$CheckIdent` Check Ident is Valid 





#### Returns
int Variable Instance ID

### `protected  ValidIdent( $Ident, $CheckProp)` {#class_base_rpc_module_1a6a68ae1d8bbb12faba4f69edde487aa5}



#### Parameters
* string`$Ident` Variable ident to check 


* bool`$CheckProp` If true then checks ident prop is set 





#### Returns
bool

### `protected  CreateMissedProfile( $name)` {#class_base_rpc_module_1a96c51d7504ea32cb2b2f9517cb44f7db}



#### Parameters
* string`$name` Create missing profile 





#### Returns
bool True if profile created

### `protected  GetPropDef( $Ident)` {#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158}

Returns variable definitions Must overide in your own Module.

#### Parameters
* string`$Ident` Variable ident to get Definition

### `protected  GetModuleName( $name, $host)` {#class_base_rpc_module_1aea9a8a1c1f29ecee0d47ccb44e418d49}



#### Parameters
* string`$name` 


* string`$host` 





#### Returns
string

### `protected  IsLastInstance( $guid)` {#class_base_rpc_module_1ac282aaf9f57f6e492f44bb5d11c46e02}



#### Parameters
* string`$guid` Module ID 





#### Returns
bool True if no more instance created

# class `Cache` {#class_cache}




Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  __construct()` | 
`public  Load( $Name, $force)` | 
`protected  $_cachePath` | 

## Members

### `public  __construct()` {#class_cache_1a885a215e76fd17e7337e5c9f89a9b779}





### `public  Load( $Name, $force)` {#class_cache_1ace5a384dc765031cd5e64e7321acec7f}



#### Parameters
* string`$Name` 


* boolean`$force` 





#### Returns
NULL|SimpleXMLElement

### `protected  $_cachePath` {#class_cache_1ab167a23de9dbdcdbf643a39f95d928f1}





# class `FritzCallmon` {#class_fritz_callmon}

```
class FritzCallmon
  : public IPSRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  PROP_MISSED` | 
`public  Create()` | 
`public  Destroy()` | 
`public  GetConfigurationForm()` | 
`public  GetConfigurationForParent()` | 
`public  ReceiveData( $JSONString)` | 
`public  RequestUpdate()` | 
`protected  $timerDef` | 
`protected  $requireLogin` | 
`protected  $showRefreshButton` | 
`protected  $prop_names` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  GetModuleName( $name, $host)` | 
`protected  UpdateProps( $doApply)` | 
`protected  DoUpdate()` | 
`protected  GetPropDef( $Ident)` | 
`protected  ProcessHookData()` | 

## Members

### `public  PROP_MISSED` {#class_fritz_callmon_1a8cf5a6047d7ce975a53a5fd01f94afe0}





### `public  Create()` {#class_fritz_callmon_1a64114c2d9b4a3e22837bd584194a814f}



**See also**: [IPSRpcModule::Create()](#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37)

### `public  Destroy()` {#class_fritz_callmon_1a89951a07f091bd0262847bd9840ee502}



**See also**: [BaseRpcModule::Destroy()](#class_base_rpc_module_1a2cfb3f524bc1c2c087acc060536d7cdb)

### `public  GetConfigurationForm()` {#class_fritz_callmon_1a656acbe8d71da761c90d92110c6dff0b}



**See also**: [BaseRpcModule::GetConfigurationForm()](#class_base_rpc_module_1a42c0cd9c8229505fbb5a6101d9d607bd)

### `public  GetConfigurationForParent()` {#class_fritz_callmon_1a5c42f254d00b6cab44e50cba672c14f7}





### `public  ReceiveData( $JSONString)` {#class_fritz_callmon_1a7864cf9c370cfc1652c8d2c57c6bad7c}



**See also**: IPSModule::ReceiveData()

### `public  RequestUpdate()` {#class_fritz_callmon_1ad7419171215628ba4df1d97eb6c369a7}





### `protected  $timerDef` {#class_fritz_callmon_1ab3aac449b40a1250ef9d2f272d0b0314}





### `protected  $requireLogin` {#class_fritz_callmon_1a359a17ca6cda67471ec904e14356e89c}





### `protected  $showRefreshButton` {#class_fritz_callmon_1ae1ee93295f505418b9af3d15ab7b9286}





### `protected  $prop_names` {#class_fritz_callmon_1a576633d3b403c5796d508a2a14b61111}





### `protected  GetDiscoverDeviceOptions()` {#class_fritz_callmon_1a3c8ab21951b61bfbed361569a8cce210}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  GetModuleName( $name, $host)` {#class_fritz_callmon_1a9b581004f2bebd27c7627aa6c905279c}



**See also**: [BaseRpcModule::GetModuleName()](#class_base_rpc_module_1aea9a8a1c1f29ecee0d47ccb44e418d49)

### `protected  UpdateProps( $doApply)` {#class_fritz_callmon_1a26e52bdd8b310fe14bc2c4d0bbff5608}



**See also**: [BaseRpcModule::UpdateProps()](#class_base_rpc_module_1a0e2144ecacdb5a6f2cd215108c7a9ea0)

### `protected  DoUpdate()` {#class_fritz_callmon_1a4b739440e2b7cbac744eb487abafd868}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

### `protected  GetPropDef( $Ident)` {#class_fritz_callmon_1af3c44520bcedbdcf674697e1676ab203}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

### `protected  ProcessHookData()` {#class_fritz_callmon_1a9b1612ca9afb0e8bc53515bb56fc436a}



**See also**: [IPSRpcModule::ProcessHookData()](#class_i_p_s_rpc_module_1a750d91cd7e8616de0b9dd4436bb26dfa)

# class `FritzHomeAuto` {#class_fritz_home_auto}

```
class FritzHomeAuto
  : public IPSRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  PROP_TEMP` | 
`public  Create()` | 
`public  Destroy()` | 
`public  GetConfigurationForm()` | 
`public  RequestAction( $Ident, $Value)` | 
`public  RequestUpdate()` | 
`protected  $timerDef` | 
`protected  $requireLogin` | 
`protected  $showRefreshButton` | 
`protected  $prop_names` | 
`protected  ValidateConfiguration()` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  GetModuleName( $name, $host)` | 
`protected  DoUpdate()` | 
`protected  CreateMissedProfile( $name)` | 
`protected  GetPropDef( $Ident)` | 

## Members

### `public  PROP_TEMP` {#class_fritz_home_auto_1ae6f7a1595492c0db77322235e61172d0}





### `public  Create()` {#class_fritz_home_auto_1acd1808489fd949979e9c7b5205248ec3}



**See also**: [IPSRpcModule::Create()](#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37)

### `public  Destroy()` {#class_fritz_home_auto_1a45e6c2fc958755a5d474930de8b9cf84}



**See also**: [BaseRpcModule::Destroy()](#class_base_rpc_module_1a2cfb3f524bc1c2c087acc060536d7cdb)

### `public  GetConfigurationForm()` {#class_fritz_home_auto_1aef24a081ba2721dbb45d8dac38fa4be4}



**See also**: [BaseRpcModule::GetConfigurationForm()](#class_base_rpc_module_1a42c0cd9c8229505fbb5a6101d9d607bd)

### `public  RequestAction( $Ident, $Value)` {#class_fritz_home_auto_1a0373e36106637f6bc67e37790868609a}



**See also**: [BaseRpcModule::RequestAction()](#class_base_rpc_module_1abc8e0369d489b7301287fedc42e9a8c2)

### `public  RequestUpdate()` {#class_fritz_home_auto_1adee8001d2dd7572f1d624a78ffc04fec}





### `protected  $timerDef` {#class_fritz_home_auto_1a7a7415d80721d9b794a52490345cf915}





### `protected  $requireLogin` {#class_fritz_home_auto_1aa0cc7044b88526a1100905e8fb5a5adb}





### `protected  $showRefreshButton` {#class_fritz_home_auto_1afc9e11516fa8b4dca6f81acc3e65d18e}





### `protected  $prop_names` {#class_fritz_home_auto_1a9993182cace748d58a3186e82d84f1a5}



**See also**: [BaseRpcModule::$prop_names](#class_base_rpc_module_1ae9abb29319252561f8f79ed2c8bf65cb)

### `protected  ValidateConfiguration()` {#class_fritz_home_auto_1a04ee22650c6982f5a6bde8b47c1578e1}



**See also**: [BaseRpcModule::ValidateConfiguration()](#class_base_rpc_module_1a680e3cf6a8c260948d5b89836d377085)

### `protected  GetDiscoverDeviceOptions()` {#class_fritz_home_auto_1a5d2a221ae30172dee52f44d87471f0b9}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  GetModuleName( $name, $host)` {#class_fritz_home_auto_1a4652427b21416a1f0f94bd897ba81481}



**See also**: [BaseRpcModule::GetModuleName()](#class_base_rpc_module_1aea9a8a1c1f29ecee0d47ccb44e418d49)

### `protected  DoUpdate()` {#class_fritz_home_auto_1aa0248529d1ba0ac3236b3f6257b5e89e}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

### `protected  CreateMissedProfile( $name)` {#class_fritz_home_auto_1adddc89e979fec7734ccf996111d02ab8}



**See also**: [BaseRpcModule::CreateMissedProfile()](#class_base_rpc_module_1a96c51d7504ea32cb2b2f9517cb44f7db)

### `protected  GetPropDef( $Ident)` {#class_fritz_home_auto_1a5771a2b0e43502fe07560bd532e2ddec}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

# class `FritzLog` {#class_fritz_log}

```
class FritzLog
  : public IPSRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  Create()` | 
`public  RequestUpdate()` | 
`protected  $timerDef` | 
`protected  $requireLogin` | 
`protected  $showRefreshButton` | 
`protected  $prop_names` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  GetModuleName( $name, $host)` | 
`protected  DoUpdate()` | 
`protected  GetPropDef( $Ident)` | 
`protected  UpdateProps( $doApply)` | 

## Members

### `public  Create()` {#class_fritz_log_1aa044aae7c3de3da7b9d0a21dc925f9e7}



**See also**: [IPSRpcModule::Create()](#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37)

### `public  RequestUpdate()` {#class_fritz_log_1a08f7d02638818c5ca4e6e4d9c688f05c}





### `protected  $timerDef` {#class_fritz_log_1ade91bc70fcf1159e1c5f8ce5daf0b1e8}





### `protected  $requireLogin` {#class_fritz_log_1aa33feeea3a43d8c801a1871a7a7cc31a}





### `protected  $showRefreshButton` {#class_fritz_log_1a5548f3ad5b25540ecb467b7f12859a03}





### `protected  $prop_names` {#class_fritz_log_1ae041ccecc2db126d5f88e4a73744a2ba}



**See also**: [BaseRpcModule::$prop_names](#class_base_rpc_module_1ae9abb29319252561f8f79ed2c8bf65cb)

### `protected  GetDiscoverDeviceOptions()` {#class_fritz_log_1a1d2f948eb09446fb68bda8edf2e33399}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  GetModuleName( $name, $host)` {#class_fritz_log_1af61c15e200808a07b5bb36359ff8521b}



**See also**: [BaseRpcModule::GetModuleName()](#class_base_rpc_module_1aea9a8a1c1f29ecee0d47ccb44e418d49)

### `protected  DoUpdate()` {#class_fritz_log_1ad26ddb40d1dae074690f41832aabf37f}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

### `protected  GetPropDef( $Ident)` {#class_fritz_log_1aa54d200970878b611494106abcfee660}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

### `protected  UpdateProps( $doApply)` {#class_fritz_log_1a9d8a4e6c4d8fa81e536c1b1904475f7c}



**See also**: [BaseRpcModule::UpdateProps()](#class_base_rpc_module_1a0e2144ecacdb5a6f2cd215108c7a9ea0)

# class `FritzStatus` {#class_fritz_status}

```
class FritzStatus
  : public IPSRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  PROP_SPEED_DOWN` | 
`public  Create()` | 
`public  Destroy()` | 
`public  RequestAction( $Ident, $Value)` | 
`public  RequestUpdate()` | 
`public  WriteValue(string $Ident,string $Value)` | 
`protected  $timerDef` | 
`protected  $requireLogin` | 
`protected  $showRefreshButton` | 
`protected  $prop_names` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  GetModuleName( $name, $host)` | 
`protected  DoUpdate()` | 
`protected  GetPropDef( $Ident)` | 
`protected  UpdateProps( $doApply)` | 

## Members

### `public  PROP_SPEED_DOWN` {#class_fritz_status_1ac23a4d231aea51af02ace6a8944c9ad1}





### `public  Create()` {#class_fritz_status_1a9843575b6c330707cb297a6c1120db6a}



**See also**: [IPSRpcModule::Create()](#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37)

### `public  Destroy()` {#class_fritz_status_1aeb746f5c897ffaf6bc37c08118c144c9}



**See also**: [BaseRpcModule::Destroy()](#class_base_rpc_module_1a2cfb3f524bc1c2c087acc060536d7cdb)

### `public  RequestAction( $Ident, $Value)` {#class_fritz_status_1a0db7e130f8d9769f6c1aa4008c7206f3}



**See also**: [BaseRpcModule::RequestAction()](#class_base_rpc_module_1abc8e0369d489b7301287fedc42e9a8c2)

### `public  RequestUpdate()` {#class_fritz_status_1a4ade3cb539d103183d76f04ac1276a9f}





### `public  WriteValue(string $Ident,string $Value)` {#class_fritz_status_1af829ef2a8c1634b4387d614d07553bfd}



#### Parameters
* string`$Ident` 


* string`$Value` 





#### Returns
void|NULL

### `protected  $timerDef` {#class_fritz_status_1ade3540e0055a8665bae06456904139f2}





### `protected  $requireLogin` {#class_fritz_status_1a1a2a82027f5fba9706113dcf228f4639}





### `protected  $showRefreshButton` {#class_fritz_status_1acc896a597bcda6b42890661c3589674a}





### `protected  $prop_names` {#class_fritz_status_1a9d6bf4c8ad81ec99dee4aa8816bf797c}



**See also**: [BaseRpcModule::$prop_names](#class_base_rpc_module_1ae9abb29319252561f8f79ed2c8bf65cb)

### `protected  GetDiscoverDeviceOptions()` {#class_fritz_status_1a653de5f91a28bb4b7912a373a602d281}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  GetModuleName( $name, $host)` {#class_fritz_status_1a8e6527c0ce6d418c78107985a3dc5ab7}



**See also**: [BaseRpcModule::GetModuleName()](#class_base_rpc_module_1aea9a8a1c1f29ecee0d47ccb44e418d49)

### `protected  DoUpdate()` {#class_fritz_status_1a951ff4feb3c5014354e25a6f875e56ed}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

### `protected  GetPropDef( $Ident)` {#class_fritz_status_1a78ea46e4f510f401f6b3a08325280301}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

### `protected  UpdateProps( $doApply)` {#class_fritz_status_1a6e26d0e3a80fd7c71fa5619891e0ae9c}



**See also**: [BaseRpcModule::UpdateProps()](#class_base_rpc_module_1a0e2144ecacdb5a6f2cd215108c7a9ea0)

# class `GenericRpc` {#class_generic_rpc}

```
class GenericRpc
  : public IPSRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  Create()` | 
`public  GetConfigurationForm()` | 
`public  CallMethod(string $MethodName,string $Arguments)` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  GetPropDef( $Ident)` | 
`protected  DoUpdate()` | 

## Members

### `public  Create()` {#class_generic_rpc_1aca9e4961454f7c006631eb182fc1ebf2}



**See also**: [IPSRpcModule::Create()](#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37)

### `public  GetConfigurationForm()` {#class_generic_rpc_1a4f4b75d26e6d9b806a76820103f50ede}



**See also**: [BaseRpcModule::GetConfigurationForm()](#class_base_rpc_module_1a42c0cd9c8229505fbb5a6101d9d607bd)

### `public  CallMethod(string $MethodName,string $Arguments)` {#class_generic_rpc_1a9275bcd533fc30f3864ae67f3df5a4ab}



#### Parameters
* string`$MethodName` 


* string`$Arguments` 





#### Returns
mixed

### `protected  GetDiscoverDeviceOptions()` {#class_generic_rpc_1a9b875804802b7074faa8a321e417f35b}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  GetPropDef( $Ident)` {#class_generic_rpc_1ad3ac7bcb5511e3be7b71505dcd6d842a}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

### `protected  DoUpdate()` {#class_generic_rpc_1a9719b05fa819751f3f8947837193264a}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

# class `IPSRpcModule` {#class_i_p_s_rpc_module}

```
class IPSRpcModule
  : public BaseRpcModule
```  



Xavier

## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  Create()` | 
`public  GetApi()` | 
`public  GetApiInfo()` | 
`protected  $showLogin` | 
`protected  $api` | Current rpc api object.
`protected  $lastError` | Contains the last error of api call.
`protected  ApplyDeviceProps( $Props)` | 
`protected  DoUpdate()` | 
`protected  RunUpdate()` | 
`protected  GetDeviceConfig()` | 
`protected  CreateApi()` | 
`protected  CallApi(string $function,array $arguments)` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  ApplyHost( $host, $doApply)` | 
`protected  ReDiscover()` | 
`protected  RegisterHook( $Name,bool $Create)` | 
`protected  ProcessHookData()` | 

## Members

### `public  Create()` {#class_i_p_s_rpc_module_1a2326c10a7cc3358f5b15081eec9e3b37}



**See also**: [BaseRpcModule::Create()](#class_base_rpc_module_1a2bb29ec41119710ce9366660be8880e1)

### `public  GetApi()` {#class_i_p_s_rpc_module_1a93f4e4633c213d53f73a02c173925f1e}



#### Returns
NULL|RpcApi Returns api Object for complex calls outside Module

### `public  GetApiInfo()` {#class_i_p_s_rpc_module_1a6f4653208d1b7666a4df5a735a5435b0}



#### Returns
NULL|array Returns api Informations

### `protected  $showLogin` {#class_i_p_s_rpc_module_1ab670535a78c36023e1618e7f0fd5be56}



**See also**: [BaseRpcModule::$showLogin](#class_base_rpc_module_1aaf9f0a21bd98eff89563f4702362c478)

### `protected  $api` {#class_i_p_s_rpc_module_1acf210f0ecfa6aa5c08c07f79cabf6b42}

Current rpc api object.



### `protected  $lastError` {#class_i_p_s_rpc_module_1a3ef2686178b959b324e152295ce5c91e}

Contains the last error of api call.



### `protected  ApplyDeviceProps( $Props)` {#class_i_p_s_rpc_module_1aa0de8ca81dedfffa0a93115894e616c7}





### `protected  DoUpdate()` {#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3}





### `protected  RunUpdate()` {#class_i_p_s_rpc_module_1a070ae30cda3f6bf4e7d7e8b5251dc823}





### `protected  GetDeviceConfig()` {#class_i_p_s_rpc_module_1a8c26af9a0bf9c42c8dbecc59510167de}



#### Returns
NULL|array

### `protected  CreateApi()` {#class_i_p_s_rpc_module_1aa3a66e2912db056c098dedadf452d8bc}



#### Returns
NULL|RpcApi

### `protected  CallApi(string $function,array $arguments)` {#class_i_p_s_rpc_module_1a234a73ced504238c432128d437fbf170}



#### Parameters
* string`$function` Function name to call 


* array`$arguments` Function arguments 





#### Returns
NULL|mixed Result of api call

### `protected  GetDiscoverDeviceOptions()` {#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446}



#### Returns
array

### `protected  ApplyHost( $host, $doApply)` {#class_i_p_s_rpc_module_1a331a06580c4535c5dddf74dd094a5e7a}



**See also**: [BaseRpcModule::ApplyHost()](#class_base_rpc_module_1aa05b4847aac9631a743a5fbb8f59ed2d)

### `protected  ReDiscover()` {#class_i_p_s_rpc_module_1a0dbe5fbd8626c0b5409f28498d814877}



#### Returns
void

### `protected  RegisterHook( $Name,bool $Create)` {#class_i_p_s_rpc_module_1a2ccbcdcba79f8fa74e1179ea72e7d5fa}



#### Parameters
* string`$Name` Name of the hook 


* bool`$Create` if true Hook creating , if false delete hook 





#### Returns
string|bool if creating then returns full hook name or false if error or nothing change

### `protected  ProcessHookData()` {#class_i_p_s_rpc_module_1a750d91cd7e8616de0b9dd4436bb26dfa}



#### Returns
bool True if proceed successful

# class `MediaRpc` {#class_media_rpc}

```
class MediaRpc
  : public IPSRpcModule
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  PROP_MUTE_CONTROL` | 
`public  RequestAction( $Ident, $Value)` | 
`public  Destroy()` | 
`public  WriteValue(string $Ident,string $Value)` | 
`public  RequestUpdate()` | 
`protected  $timerDef` | 
`protected  $showRefreshButton` | 
`protected  $prop_names` | 
`protected  GetDiscoverDeviceOptions()` | 
`protected  ApplyDeviceProps( $Props)` | 
`protected  DoUpdate()` | 
`protected  CreateMissedProfile( $name)` | 
`protected  GetPropDef( $Ident)` | 

## Members

### `public  PROP_MUTE_CONTROL` {#class_media_rpc_1a7e014a5f07ae95c5777188022674025a}





### `public  RequestAction( $Ident, $Value)` {#class_media_rpc_1a4bd3ef89d551edd79d050535ccdd48f8}



**See also**: [BaseRpcModule::RequestAction()](#class_base_rpc_module_1abc8e0369d489b7301287fedc42e9a8c2)

### `public  Destroy()` {#class_media_rpc_1a9b00ee978be30572def9f4b01378d3b1}



**See also**: [BaseRpcModule::Destroy()](#class_base_rpc_module_1a2cfb3f524bc1c2c087acc060536d7cdb)

### `public  WriteValue(string $Ident,string $Value)` {#class_media_rpc_1a3d19dd2d5c6281f68e96f395c5df4c26}



#### Parameters
* string`$Ident` 


* string`$Value` 





#### Returns
void|NULL

### `public  RequestUpdate()` {#class_media_rpc_1a7fcde5da4b0c90759c7a13ac51f9ceaa}





### `protected  $timerDef` {#class_media_rpc_1ac493655aa97b87ae2fb9a56e299a77cb}





### `protected  $showRefreshButton` {#class_media_rpc_1a7c1eff4577d83969b69db2d8405a15cc}





### `protected  $prop_names` {#class_media_rpc_1a629be05f1379b319958124e5757f7969}



**See also**: [BaseRpcModule::$prop_names](#class_base_rpc_module_1ae9abb29319252561f8f79ed2c8bf65cb)

### `protected  GetDiscoverDeviceOptions()` {#class_media_rpc_1abe3db7c8e25045846ebf5d5903aa1582}



**See also**: [IPSRpcModule::GetDiscoverDeviceOptions()](#class_i_p_s_rpc_module_1a899aaf4bcdc4944f0e6c6f4e460af446)

### `protected  ApplyDeviceProps( $Props)` {#class_media_rpc_1a76507bcd5dd59d9cf87e8a46502d3080}



**See also**: [IPSRpcModule::ApplyDeviceProps()](#class_i_p_s_rpc_module_1aa0de8ca81dedfffa0a93115894e616c7)

### `protected  DoUpdate()` {#class_media_rpc_1a5b3f5da6a7031eb2d24420285824948f}



**See also**: [IPSRpcModule::DoUpdate()](#class_i_p_s_rpc_module_1a8da09f1c7ac80d0df775b0dc9c98fff3)

### `protected  CreateMissedProfile( $name)` {#class_media_rpc_1abb712b40c9046a92a7c04780dc746493}



**See also**: [BaseRpcModule::CreateMissedProfile()](#class_base_rpc_module_1a96c51d7504ea32cb2b2f9517cb44f7db)

### `protected  GetPropDef( $Ident)` {#class_media_rpc_1ac5f4a01cbf601660597fa28db8fbad4c}



**See also**: [BaseRpcModule::GetPropDef()](#class_base_rpc_module_1a9a21dc98cc40eddd753ea64e82c7d158)

# class `RpcApi` {#class_rpc_api}






## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  __construct( $device_cfg, $host, $user, $pass)` | 
`public  __call( $fn, $args)` | 
`public  __get( $n)` | 
`public  __set( $n, $v)` | 
`public  SetErrorHandler( $func)` | 
`public  LastError()` | 
`public  FunctionExists( $name, $serviceName)` | Check if function is valid.
`public  FunctionList( $a1, $a2)` | 
`public  DeviceInfo()` | 
`protected  getDefaultValue( $name, $serviceName)` | 

## Members

### `public  __construct( $device_cfg, $host, $user, $pass)` {#class_rpc_api_1adb9ef03cd5db23081473f3ee55d91e56}





### `public  __call( $fn, $args)` {#class_rpc_api_1aec07339075d9a93b42c90cb2f1938f43}





### `public  __get( $n)` {#class_rpc_api_1aa48f5f1a3819f0339a9bfeae6e3ee7b1}





### `public  __set( $n, $v)` {#class_rpc_api_1aab02044d17165fc21f88b9d6dc37a671}





### `public  SetErrorHandler( $func)` {#class_rpc_api_1a74b9496318f56c933139740aa6e1d517}





### `public  LastError()` {#class_rpc_api_1ad7e9a2890c8a9da3bdccec4fc8a04496}



#### Returns
string

### `public  FunctionExists( $name, $serviceName)` {#class_rpc_api_1a08982ca1d38a1dab9fe767cef8a21f06}

Check if function is valid.

#### Parameters
* string`$name` Function name to find 


* string`$serviceName` If given then search for function only in service name 





#### Returns
boolean True if function found

### `public  FunctionList( $a1, $a2)` {#class_rpc_api_1a099ba9062684e2b60dcf1d1c5c7ad890}



#### Parameters
* boolean`$a1` 


* boolean`$a2` 





#### Returns
array

### `public  DeviceInfo()` {#class_rpc_api_1aff8692abb906fa238916165081502b74}



#### Returns
array

### `protected  getDefaultValue( $name, $serviceName)` {#class_rpc_api_1acd9c509d6e5386db869c2d1c05318465}





# class `SamsungTVRemote` {#class_samsung_t_v_remote}

```
class SamsungTVRemote
  : public IPSModule
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  enabled` | 
`public  Create()` | 
`public  RequestAction( $Ident, $Value)` | 
`public  ApplyChanges()` | 
`public  Destroy()` | 
`public  GetConfigurationForm()` | 
`public  ResetGroups(bool $ResetMacros)` | 
`public  SendKey(string $Key)` | 
`public  SendMacro(string $Name)` | 

## Members

### `public  enabled` {#class_samsung_t_v_remote_1abb4e5d4e5c55c8ecdf44139ba81b6e87}





### `public  Create()` {#class_samsung_t_v_remote_1a60fb991928e7ae9d4e7b03c96117f83d}



**See also**: IPSModule::Create()

### `public  RequestAction( $Ident, $Value)` {#class_samsung_t_v_remote_1a72d3488652369b141297e1c95e4808b1}



**See also**: IPSModule::RequestAction()

### `public  ApplyChanges()` {#class_samsung_t_v_remote_1a488b631481fdf253b89446497771ddb6}



**See also**: IPSModule::ApplyChanges()

### `public  Destroy()` {#class_samsung_t_v_remote_1a846231299c7ee6f8e6feaf0fed721058}



**See also**: IPSModule::Destroy()

### `public  GetConfigurationForm()` {#class_samsung_t_v_remote_1a0c772dc877413667e0e8496552f26c66}



**See also**: IPSModule::GetConfigurationForm()

### `public  ResetGroups(bool $ResetMacros)` {#class_samsung_t_v_remote_1a537743eb72e4877e994f1e398d37df73}



#### Parameters
* bool`$ResetMacros`

### `public  SendKey(string $Key)` {#class_samsung_t_v_remote_1abc4bb5e3c85739d653dc6f66c9acba2e}



#### Parameters
* string`$Key` 





#### Returns
boolean

### `public  SendMacro(string $Name)` {#class_samsung_t_v_remote_1a07eb08f3aa1a05006547dbc9dd1532ca}



#### Parameters
* string`$Name` 





#### Returns
boolean

# class `UTILS` {#class_u_t_i_l_s}






## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------

## Members

# class `XmlRPC_Array` {#class_xml_r_p_c___array}

```
class XmlRPC_Array
  : public XmlRPC_Parm
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  getType()` | 
`protected  getFormattedValue()` | 

## Members

### `public  getType()` {#class_xml_r_p_c___array_1a7d4a95bcbf4510a8fe1929b0af98ec07}





### `protected  getFormattedValue()` {#class_xml_r_p_c___array_1ae25250af320a7cf7f49c3f8031afc56e}





# class `XmlRPC_Binary` {#class_xml_r_p_c___binary}

```
class XmlRPC_Binary
  : public XmlRPC_Parm
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  getType()` | 
`protected  getFormattedValue()` | 

## Members

### `public  getType()` {#class_xml_r_p_c___binary_1adc2bb86e0d2ded38b00e68979eb6de36}





### `protected  getFormattedValue()` {#class_xml_r_p_c___binary_1ad569cf299243e7f440b93241e16b9c7b}





# class `XmlRPC_Date` {#class_xml_r_p_c___date}

```
class XmlRPC_Date
  : public XmlRPC_Parm
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  getType()` | 
`protected  getFormattedValue()` | 

## Members

### `public  getType()` {#class_xml_r_p_c___date_1a6e1461a4d93b886c6b7aee4c4c8bb839}





### `protected  getFormattedValue()` {#class_xml_r_p_c___date_1a1a60c09df88fcec05f5434fb5a206bdf}





# class `XmlRPC_Parm` {#class_xml_r_p_c___parm}






## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  __construct( $value)` | 
`public  getType()` | 
`public  getValue()` | 
`public  __toString()` | 
`protected  getFormattedValue()` | 

## Members

### `public  __construct( $value)` {#class_xml_r_p_c___parm_1a20e35080666b63df13e0bfbe538d9bfb}





### `public  getType()` {#class_xml_r_p_c___parm_1a81a02fad40c47ee11a22c88feb109cb0}





### `public  getValue()` {#class_xml_r_p_c___parm_1aadd66388b615d510c69a616d8c88b6ac}





### `public  __toString()` {#class_xml_r_p_c___parm_1a101c211c380d7dbcc529dcce8b5f053c}





### `protected  getFormattedValue()` {#class_xml_r_p_c___parm_1a550aba99c2a436daaf1123e84d1cedc0}





# class `XmlRPC_Struct` {#class_xml_r_p_c___struct}

```
class XmlRPC_Struct
  : public XmlRPC_Parm
```  





## Summary

 Members                        | Descriptions                                
--------------------------------|---------------------------------------------
`public  getType()` | 
`protected  getFormattedValue()` | 

## Members

### `public  getType()` {#class_xml_r_p_c___struct_1ac4da7d84712efbd7f3334fdb4a1e1add}





### `protected  getFormattedValue()` {#class_xml_r_p_c___struct_1adc18951c7c5f9d9973ea4d15853b7a45}
