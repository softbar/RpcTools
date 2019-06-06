# RpcTools

RpcTools for IP-Symcon


# Configurator Module 

	Durchsucht das Netzwerk nach Rpc Geräten
	Sondermodule
	1. Homematic CCU
	2. Enigma2 WebInterface
	3. Fritzbox (see Add One Modules)
  

Arbeitet wie folgt:

Die Suche nach Geräten im Netzwerk dauert zwischen 5 und 180 Sekunden abhängig 
von der Timeout-Einstellung im Konfigurator und der Anzahl gefundener Gerätes.

Das Ergebnis ist, dass die lokale Windows-Konsole zu hängen scheint, ist aber nicht so :-(

In der Webfront-Konsole kann man den Fortschritt im Nachrichtenprotokoll verfolgen.
 
Mit dem RpcConfigurator können neue Geräte einfach gesucht und erstellt werden.
Wenn dein Gerät nicht in der Liste angezeigt wird und Du die Gerät Beschreibungs-XML-Datei-URL kennst, 
dann kannst du entweder eine allgemeines oder eine Multimedia-Module erstellen
und die vollständige URL als HOST eingeben. Das Modul versucht dann, sich selbst zu konfigurieren.
- HINWEIS Wenn Du mehrere URLs angeben möchtest, dann werden diese entweder als Zeichenfolge durch Komma getrennt oder als einfaches Array übergeben


- Beispiel Urls
	- http://fritz.box:49000/tr64desc.xml
	- http://192.168.112.61:8000/serverxml.xml,http://192.168.112.61:8080/description.xml
	- homematic.xml < für CCU
	- enigma2.xml < für DreamBox Enigma2 WebInterface

Alle Module beinhalten Timer Funktionen zum aktualisieren der Statusvariablen , mit Ausnahme des RPCGENERIC Moduls .
Es gibt 2 Timermodes UPDATE und OFFLINE die jeweils getrennte werte haben können, dies bedeutet 
wenn das Gerät online ist wird der Status alle 15min aktualisiert , wenn es offline ist (TV ausgeschaltet) findet 
die Prüfung / Aktualisierung nur alle 2 Stunden statt solange bis das Gerät wieder online ist.

Alle Module exportieren die Funktionen xxx_GetApi und xxx_GetApiInfo

-xxx_GetApi() 		gibt ein RpcApi Objekt zurück, mit dem alle weiteren Befehle an das Gerät gesendet werden können.
-xxx_GetApiInfo() 	Liefert ein Array mit Informationen zur API


# Generic RPC Module 
	API zum Aufrufen aller vom Gerät erkannten Methoden Es wird nur eine Variable für den Status erstellt
	
Arbeitet:

Dieses Modul exportiert zusätzlich die Funktionen RPCGENERIC_CallMethod
- RPCGENERIC_CallMethod(IpsInstanceID,FunctionName, Kommagetrennter String mit Parametern)

die Syntax entspricht $api->__call(FunctionName, Parameter ) mit dem Unterschied das die Parameter nicht als Array sondern als Komma-getrennter String übergeben.
- RPCGENERIC_CallApi(IpsInstanceID,'SetVolume',"0,Master,10")
- RPCGENERIC_CallApi(IpsInstanceID,'GetVolume',"0,Master")
oder
- $api = RPCGENERIC_GetApi(IpsInstanceID)
- $volume=$api->GetVolume(0,"Master")
- $api->SetVolume(0,"Master",$volume)

Der Aufruf über das RpcApi Objekt $api ist in jedem fall bei mehreren, aufeinander folgenden ,aufrufen sinnvoll da nicht , wie bei  xxx_GetApi(IpsInstanceID), bei jedem Aufruf das Module neu erstellt wird.
 
Darüber hinaus ist es möglich, Anrufe direkt an einen Dienst zu richten. Da einige Rpc-Geräte wie zb die Fritzbox 
mehrere gleichnamige Funktionen enthält, ist es notwendig, den Dienstnamen beim Aufruf anzugeben. Das passiert wie folgt

- $api->__ call ("DeviceInfo1.GetInfo", Array mit Parametern)
oder
- $api->{"DeviceInfo1.GetInfo"}(Parameter,Parameter...)

# Multimedia RPC Module 
	Liefert, abhängig vom Gerät, Standardmethoden für 
	1. Volume, Mute (Generic)
	2. Bass, Loudness, Trebble (sonos, ect..)
	3. Play,Pause,Stop,Next,Previous (Generic)
	4. Color,Brightness,Sharpness, Contrast (TV )

Arbeite:
Dieses Modul exportiert zusätzlich die Funktionen 
- RPCMEDIA_RequestUpdate	aktualisiert alle Status variablen 
- RPCMEDIA_WriteValue		setzen von Status variablen

Da die Rpc Geräte InstanceID normalerweise 0 ist, muss diese Variable beim Aufruf nicht angegeben werden
ebenso die Variable Channel . Diese Werte beim Aufruf automatisch hinzugefügt.

Daher ist auch der Aufruf mit $api->GetVolume() oder $api->SetVolume(10) möglich.

- $api = RPCMEDIA_GetApi(IpsInstanceID)
- $api-> SetVolume (NewVolume)
- $volume=$api->GetVolume ()

oder

benutze die Funktion RPCMEDIA_WriteValue um Status Variablen zu ändern.

- RPCMEDIA_WriteValue (IpsInstanceID, 'VOLUME', 10) sets the volume to 10
- RPCMEDIA_WriteValue (IpsInstanceID, 'PLAYSTATE', value) wird zur Steuerung der Wiedergabe verwendet, wobei folgende Werte möglich sind:
 
	- 0: Stop
	- 1: Pause
	- 2: Play
	- 3: Next
	- 4: Prevoius	

# Anmerkung
Um die Konfigdateien der Geräte im Cache klein zu halten , werden nicht benötigte Funktionen und Statusvariablen beim verarbeiten der XML Dateien ignoriert.
Wer tiefer eintauchen möchte findet alles dazu in der discrover.inc Datei, außerdem habe ich die Module im Quellkode (für meine Verhältnisse *lach* ) gut dokumentiert.

# Zusatz Module

findest du hier <https://github.com/softbar/RpcAddOnes> 
