# RpcTools
RpcTools for IP-Symcon


# Configurator Module 

	Discover your network to found Upnp Rpc Devices
	Special Devices 
	1. Homematic CCU
	2. Enigma2 WebInterface
  

Works:

The detection process for new devices takes between 15 and 150 seconds, 
depending on the settings in the configurator. 

The result is that the local console seems to be hanging, but in the 
Webfront console you can track the progress in the message log.
 
You can use RpcConfigurator to create new devices 
If your device does not appear in the list and you have the device 
description XML file URL then you can either create a generic or multimedia
Module and enter the full URL as HOST. 
The module then tries to configure itself.



# Generic RPC Module 
	API to call all methods discovered from device
	Only one Variable for Status created

Works:

This module only exports the functions RPCGENERIC_GetApi and RPCGENERIC_CallApi

RPCGENERIC_GetApi returns an object with which all further commands can be sent
to the device.
- NOTE: These two functions are exported to all Rpc devices

The volume can be changed or read as follows
		$api-> SetVolume (InstanceID, channel, NewVolume)
		$volume=$api->GetVolume (InstanceID, Channel)
Since InstanceID is usually 0, this variable does not have to be specified, just
like Channel, these values are automatically added when called.

Therefore the call with $api->GetVolume () or $api->SetVolume(10) is also
possible.

Furthermore, it is possible to address calls directly. Since some Rpc devices such
as the Fritzbox contains several functions of the same name, GetInfo (), it is
necessary to transfer the service name. This happens as follows
	. $api->__ call ("DeviceInfo1.GetInfo", array with parameter)
or
	$api->{"DeviceInfo1.GetInfo"}, array with parameters)

The RPCGENERIC_CallApi(IpsInstanceID,FunctionName, string commaseperated arguments ) is the same as $api->__call only arguments as Commaseperadet String requirend.
	RPCGENERIC_CallApi(IpsInstanceID,'SetVolume',"0,Master,10")
	RPCGENERIC_CallApi(IpsInstanceID,'SetVolume',"10")
or
	$volume=RPCGENERIC_CallApi(IpsInstanceID,'GetVolume',[0,"Master"])
	$volume=RPCGENERIC_CallApi(IpsInstanceID,'GetVolume',[])
 
  	

# Multimedia RPC Module 
	Includes Standard methods for
	1. Volume, Mute (Generic)
	2. Bass, Loudness, Trebble (sonos)
	3. Play,Pause,Stop,Next,Previous
	4. Color,Brightness,Sharpness, Contrast (TV , AVR )


Works:

To set or read the properties, there are the functions RPCMEDIA_ReadValue
and RPCMEDIA_WriteValue.

The command RPCMEDIA_ReadValue (InstanceID, 'VOLUME') calls the information
 directly from the device and updates the status variable VOLUME,
conversely, the command RPCMEDIA_WriteValue (InstanceID, 'VOLUME', 10)
sets the volume to 10

The command TRPCMEDIA WriteValue (Instance ID, 'PLAYSTATION', value)
is used to control the playback, whereby the following values are possible: 
0: Stop, 1: Pause, 2: Play, 3: Next, 4: Prevoius	



