[По русски](README.ru-RU.md)
# inetAIS daemon [![License: CC BY-NC-SA 4.0](screenshots/Cc-by-nc-sa_icon.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.en)
Broadcast to LAN of the AIS messages from [digitraffic.fi](https://www.digitraffic.fi/en/marine-traffic/ais/) as NMEA 0183 AIS flow. For demo and tests purposes.  

We strongly do not recommend using this software  as a replacement of real AIS on the boat. First of all, because of the [declared restrictions](https://www.digitraffic.fi/en/marine-traffic/ais/), secondly, because of the data delay reaching minutes.


version 0.

## Features
- Broadcast of AIS targets for one or more user defined points.
- Broadcast of all known digitraffic.fi AIS targets.
- Broadcast of AIS targets for current position. Position can be obtained from [gpsd](https://gpsd.io/), [gpsdPROXY](https://github.com/VladimirKalachikhin/gpsdPROXY) or from [SignalK](https://signalk.org/).
- Direct send data to [gpsdPROXY](https://github.com/VladimirKalachikhin/gpsdPROXY).  

## Compatibility
Any device/software capable of receiving NMEA 0183 messages via LAN.  

For example, the [gpsd](https://gpsd.io/)-based software charftplotter:  
![GaladrielMap](screenshots/s0.jpeg)  

Or [SignalK](https://signalk.org/)-based software charftplotter:  
![freeboard](screenshots/s1.jpeg)  

Or OpenCPN:  
![OpenCPN](screenshots/s2.jpeg)  

With the data received from [gpsdPROXY](https://github.com/VladimirKalachikhin/gpsdPROXY):  
![OpenCPN](screenshots/s3.png)  

## Requirements
Linux, PHP 7.

## Install & Configure
1) Copy project to any dir by `git clone`, copy and unzip from GitHub or by any way.  
2) Edit *params.php*

## Usage
Start daemon by  
`php inetAIS.php`  
or  
`./start`  
or  
`./start -d`  
for daemonise.  

Configure your device/software to receive NMEA 0183 from host (may be localhost?) and port (3800 by default) as you set in *params.php*.  
No need for special configuration to receive data in the [gpsdPROXY](https://github.com/VladimirKalachikhin/gpsdPROXY).

## Support
[Forum](https://github.com/VladimirKalachikhin/Galadriel-map/discussions)

The forum will be more lively if you make a donation at [ЮMoney](https://sobe.ru/na/galadrielmap)

[Paid personal consulting](https://kwork.ru/it-support/20093939/galadrielmap-installation-configuration-and-usage-consulting)  
