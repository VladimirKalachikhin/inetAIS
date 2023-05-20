<?php
// ссылка на источник информации о положении целей AIS
$urlAISlocations = 'https://meri.digitraffic.fi/api/ais/v1/locations';	// AIS locations data url
// ссылка на источник сведений о целях AIS
$urlAISvessels = 'https://meri.digitraffic.fi/api/ais/v1/vessels';	// AIS targets metadata url
// Массив пунктов, в которых показывается обстановка AIS, radius - в км.
// AIS POI array, radius is in km.
$AISinterestPoints = array(
'Гельсингфорс' => array('latitude'=>60.1688,'longitude'=>24.939,'radius'=>5),
'Ханко' => array('latitude'=>59.8222,'longitude'=>22.9491,'radius'=>5),
'Або' => array('latitude'=>60.432,'longitude'=>22.2236,'radius'=>5)
//'All' => array()
);
// период опроса источника информации о положении, сек.
$getDataTimeout = 15;	// AIS locations data polling interval, sec.
// Период актуальности данных.
// Timeouts
// время в секундах, в течении которого цель AIS сохраняется после получения от неё последней информации
$noVehicleTimeout = 10*60; 	// seconds, time of continuous absence of the vessel in AIS, when reached - is deleted from the data. "when a ship is moored or at anchor, the position message is only broadcast every 180 seconds;"
// период в секундах после времени обновления данных, когда считается, что данные потеряли актуальность.
// data types timeouts
$gpsdProxyTimeouts = array(  	
'AIS' => array(
	'status' => 86400, 	// Navigational status, one day сутки
	'accuracy' => 600, 	// Position accuracy
	'turn' => 60*5, 	// 
	'lon' => 60*10, 	// 
	'lat' => 60*10, 	// 
	'speed' => 60*5, 	// 
	'course' => 60*5, 	// 
	'heading' => 60*5, 	// 
	'maneuver' => 60*5 	// 
)
);
// Параметры подключения клиентов 
$inetAIShost = "0.0.0.0";	// Clients connection setting
$inetAISport = "3800";

// Системные параметры System
// имя интерпретатора PHP
$phpCLIexec = 'php'; 	// php-cli executed name on your OS
//$phpCLIexec = '/usr/bin/php-cli'; 	// php-cli executed name on your OS
?>
