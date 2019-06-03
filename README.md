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
In the *Webfront console*, you can track progress in the debug log.
 
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