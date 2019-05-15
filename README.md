# RpcTools
RpcTools for IP-Symcon


# Configurator Module 

	Discover your network to found Upnp Rpc Devices
	Special Devices 
		- Homematic CCU
		- Enigma2 WebInterface
  

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


- Multimedia RPC Module
	Includes Standard methods for
	- Volume, Mute (Generic)
	- Bass, Loudness, Trebble (sonos)
	- Play,Pause,Stop,Next,Previous
	- Color,Brightness,Sharpness, Contrast (TV , AVR )


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

