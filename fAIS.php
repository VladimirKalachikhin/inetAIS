<?php
// Функции получения посылок AIS из данных AIS

function getAISData($aisDates=null){
/* Приводит данные к формату сообщений NMEA AIS
$aisDates -- массив mmsi => aisData, часть массива $instrumentsData["AIS"], элементы котрого были изменены
Возвращает массив
*/
global $instrumentsData;
if(!$aisDates) $aisDates = $instrumentsData["AIS"];
$AISsentencies = array();
foreach($aisDates as $mmsi => $aisData){
	$aisData = toAISphrases($aisData['data']); 	// массив сообщений AIS, но в нормальных единицаъ измерения
	$AISsentencies = array_merge($AISsentencies,$aisData);	// плоский массив
}
return $AISsentencies;
} // end function getAISData

function toAISphrases($aisData){
/* Делает набор посылок AIS из данных AIS.
$aisData -- данные одного судна в нормальных единицах измерения
Возвращает массив строк AIS NMEA
*/
$AISformatA = array(
'1' => array(
	'MessageID' => str_pad(decbin(1), 6, '0', STR_PAD_LEFT),	// 6 bits Identifier for Message 18; always 18
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1),	// 30 bits User ID  	MMSI number
	'status' => array('num',4,1,15),	// 
	'turn' => array('num',8,1,128),
	//'turn' => str_pad(decbin(128), 8, '0', STR_PAD_LEFT),	// not available
	'speed' => array('num',10,((60*60)/1852)*10,1023),	// str_pad(decbin($speed), 10, '0', STR_PAD_LEFT) 10 SOG Speed over ground
	'accuracy' => array('num',1,1,0),
	'lon' => array('lon'),	// 
	'lat' => array('lat'),	// 
	'course' => array('num',12,10,3600),	// str_pad(decbin($course), 12, '0', STR_PAD_LEFT) 12 COG Course over ground in 1/10= (0-3599)
	//'course' => decbin(3600),
	'heading' => array('num',9,1,511),	// str_pad(decbin($heading), 9, '0', STR_PAD_LEFT) 9 True heading Degrees (0-359) (511 indicates not available = default)
	// всегда актуальные данные?
	'timestamp' => str_pad(decbin(0), 6, '0', STR_PAD_LEFT),	// 6 UTC second when the report was generated (0-59 or 60 if time stamp is not available, which should also be the default value or 62 if electronic position fixing system operates in estimated (dead reckoning) mode or 61 if positioning system is in manual input mode or 63 if the positioning system is inoperative) 
	'maneuver' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 0
	'Spare' => str_pad(decbin(0), 8, '0', STR_PAD_LEFT), //8 Not used. Should be set to zero. Reserved for future use
	'raim' => array('num',1,1,0), // 1 RAIM (Receiver autonomous integrity monitoring) flag of electronic position fixing device; 0 = RAIM not in use = default; 1 = RAIM in use
	'Communication_state' => '1100000000000000110' // 19 SOTDMA communication state (see § 3.3.7.2.1, Annex 2), if communication state selector flag is set to 0, or ITDMA communication state (see § 3.3.7.3.2, Annex 2), if communication state selector flag is set to 1 Because Class B “CS” does not use any Communication State information, this field should be filled with the following value: 1100000000000000110
),
'5' => array(
	'MessageID' => str_pad(decbin(5), 6, '0', STR_PAD_LEFT),	// 6 bits Identifier for Message 18; always 18
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1),	// str_pad(decbin($mmsi), 30, '0', STR_PAD_LEFT);// 30 bits User ID  	MMSI number
	'ais_version' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 0
	'imo' => array('num',30,1,0),
	'callsign' => array('str',7), 	// $ais->char2bin($callsign, 7) 42 Call sign of the MMSI-registered vessel. 7 x 6 bit ASCII characters,
	'shipname' => array('str',20), 	// $ais->char2bin('ASIAN JADE', 20); 	// 120 Name of the MMSI-registered vessel. Maximum 20 characters 6-bit ASCII, 
	'shiptype' => array('num',8,1,0), // str_pad(decbin($shiptype), 8, '0', STR_PAD_LEFT);//8 Type of ship and cargo type
	'to_bow' => array('num',9,1,0), 	// str_pad(decbin($to_bow), 9, '0', STR_PAD_LEFT);// Dimension to Bow Meters
	'to_stern' => array('num',9,1,0), 	// str_pad(decbin($to_stern), 9, '0', STR_PAD_LEFT);// Dimension to Stern Meters
	'to_port' => array('num',6,1,0), 	// str_pad(decbin($to_port), 6, '0', STR_PAD_LEFT);// Dimension to Port Meters
	'to_starboard' => array('num',6,1,0), 	// str_pad(decbin($to_starboard), 6, '0', STR_PAD_LEFT);// Dimension to Starboard Meters
	'TypeOfElectronicPositionFixingDevice' => str_pad(decbin(6), 4, '0', STR_PAD_LEFT),	// 0
	'eta' => array('num',20,1,2460),	//eta мы никак не изменяем это поле при получении, и это число
	'draught' => array('num',8,10,0),	//
	'destination' => array('str',20),	//
	'dte' => array('num',1,1,1),	//
	'Spare' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), //8 Not used. Should be set to zero. Reserved for future use
)
);
$AISformatB = array(
'18' => array(
	'MessageID' => str_pad(decbin(18), 6, '0', STR_PAD_LEFT),	// 6 bits Identifier for Message 18; always 18
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1),	// str_pad(decbin($mmsi), 30, '0', STR_PAD_LEFT);// 30 bits User ID  	MMSI number
	'Spare' => str_pad(decbin(0), 8, '0', STR_PAD_LEFT), //8 Not used. Should be set to zero. Reserved for future use
	'speed' => array('num',10,((60*60)/1852)*10,1023),	// 10 SOG Speed over ground
	//'PositionAccuracy' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT),	// 1
	'accuracy' => array('num',1,1,0),
	'lon' => array('lon'),	// $ais->mk_ais_lon($lon) 28
	'lat' => array('lat'),	// $ais->mk_ais_lat($lat) 27
	'course' => array('num',12,10,3600),	// 12 COG Course over ground in 1/10= (0-3599)
	'heading' => array('num',9,1,511),	// str_pad(decbin($heading), 9, '0', STR_PAD_LEFT) 9 True heading Degrees (0-359) (511 indicates not available = default)
	'timestamp' => str_pad(decbin(0), 6, '0', STR_PAD_LEFT),	// 6 UTC second when the report was generated (0-59 or 60 if time stamp is not available, which should also be the default value or 62 if electronic position fixing system operates in estimated (dead reckoning) mode or 61 if positioning system is in manual input mode or 63 if the positioning system is inoperative) 
	'Spare1' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT), //2 Not used. Should be set to zero. Reserved for future use
	'Class_B_unit_flag' => str_pad(decbin(1), 1, '0', STR_PAD_LEFT), // 1 0 = Class B SOTDMA unit 1 = Class B “CS” unit
	'Class_B_display_flag' => str_pad(decbin(1), 1, '0', STR_PAD_LEFT), // 1 0 = No display available; not capable of displaying Message 12 and 14 1 = Equipped with integrated display displaying Message 12 and 14
	'Class_B_DSC_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 0 = Not equipped with DSC function 1 = Equipped with DSC function (dedicated or time-shared)
	'Class_B_band_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 0 = Capable of operating over the upper 525 kHz band of the marine band 1 = Capable of operating over the whole marine band (irrelevant if “Class B Message 22 flag” is 0)
	'Class_B_Message_22_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 0 = No frequency management via Message 22, operating on AIS 1, AIS 2 only 1 = Frequency management via Message 22 )
	'Mode_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 0 = Station operating in autonomous and continuous mode = default 1 = Station operating in assigned mode
	//'RAIM_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 RAIM (Receiver autonomous integrity monitoring) flag of electronic position fixing device; 0 = RAIM not in use = default; 1 = RAIM in use
	'raim' => array('num',1,1,0), // 1 RAIM (Receiver autonomous integrity monitoring) flag of electronic position fixing device; 0 = RAIM not in use = default; 1 = RAIM in use
	'Communication_state_selector_flag' => str_pad(decbin(1), 1, '0', STR_PAD_LEFT), // 1 0 = SOTDMA communication state follows 1 = ITDMA communication state follows       (always “1” for Class-B “CS”)
	'Communication_state' => '1100000000000000110' // 19 SOTDMA communication state (see § 3.3.7.2.1, Annex 2), if communication state selector flag is set to 0, or ITDMA communication state (see § 3.3.7.3.2, Annex 2), if communication state selector flag is set to 1 Because Class B “CS” does not use any Communication State information, this field should be filled with the following value: 1100000000000000110
),
'24A' => array(
	'MessageID' => str_pad(decbin(24), 6, '0', STR_PAD_LEFT), 	// 6 bits Identifier for Message 24; always 24
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT), 	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1), 	// str_pad(decbin($mmsi), 30, '0', STR_PAD_LEFT);// 30 bits User ID  	MMSI number
	'Part number' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT), 	// 2 Part number  always 0 for Part A
	'shipname' => array('str',20) 	// $ais->char2bin('ASIAN JADE', 20); 	// 120 Name of the MMSI-registered vessel. Maximum 20 characters 6-bit ASCII, 
),
'24B' => array(
	'MessageID' => str_pad(decbin(24), 6, '0', STR_PAD_LEFT), 	// 6 bits Identifier for Message 24; always 24
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT), 	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1), 	// str_pad(decbin($mmsi), 30, '0', STR_PAD_LEFT);// 30 bits User ID  	MMSI number
	'Part number' => str_pad(decbin(1), 2, '0', STR_PAD_LEFT), 	// 2 Part number  always 1 for Part B
	'shiptype' => array('num',8,1,0), // str_pad(decbin($shiptype), 8, '0', STR_PAD_LEFT);//8 Type of ship and cargo type
	'VendorID' => char2bin('', 7), 	// 42 Unique identification of the Unit by a number as defined by the manufacturer (option; “@@@@@@@” = not available = default)
	'callsign' => array('str',7), 	// $ais->char2bin($callsign, 7) 42 Call sign of the MMSI-registered vessel. 7 x 6 bit ASCII characters,
	'to_bow' => array('num',9,1,0), 	// str_pad(decbin($to_bow), 9, '0', STR_PAD_LEFT);// Dimension to Bow Meters
	'to_stern' => array('num',9,1,0), 	// str_pad(decbin($to_stern), 9, '0', STR_PAD_LEFT);// Dimension to Stern Meters
	'to_port' => array('num',6,1,0), 	// str_pad(decbin($to_port), 6, '0', STR_PAD_LEFT);// Dimension to Port Meters
	'to_starboard' => array('num',6,1,0), 	// str_pad(decbin($to_starboard), 6, '0', STR_PAD_LEFT);// Dimension to Starboard Meters
	'epfd' => array('num',4,1,0), //str_pad(decbin($epfd), 4, '0', STR_PAD_LEFT) // 4 Position Fix Type 0 = Undefined (default); 1 = GPS, 2 = GLONASS, 3 = combined GPS/GLONASS, 4 = Loran-C, 5 = Chayka, 6 = integrated navigation system, 7 = surveyed; 8 = Galileo, 9-14 = not used, 15 = internal GNSS
	'Spare' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT) //2 Not used. Should be set to zero. Reserved for future use
),
'27' => array( // Хотя у нас в Class_B_unit_flag указано 1, что означает CS. Class A and Class B "SO" shipborne mobile equipment outside base station coverage
	'MessageID' => str_pad(decbin(27), 6, '0', STR_PAD_LEFT),	// 6 bits Identifier for Message 18; always 18
	'Repeatindicator' => str_pad(decbin(0), 2, '0', STR_PAD_LEFT),	// 2 Repeat indicator 0 = default; 3 = do not repeat any more
	'mmsi' => array('num',30,1),	// str_pad(decbin($mmsi), 30, '0', STR_PAD_LEFT);// 30 bits User ID  	MMSI number
	'PositionAccuracy' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT),	// 1
	'RAIM_flag' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT), // 1 RAIM (Receiver autonomous integrity monitoring) flag of electronic position fixing device; 0 = RAIM not in use = default; 1 = RAIM in use
	'status' => array('num',4,1,15),	// str_pad(decbin($status), 1, '0', STR_PAD_LEFT) 4
	'lon' => array('lon10'),	// $ais->mk_ais_lon($lon)/1000 18 Longitude in 1/10 min!!!!!
	'lat' => array('lat10'),	// $ais->mk_ais_lat($lat)/1000 17 Latitude in 1/10 min
	'speed' => array('num',6,(60*60)/1852,63),	// str_pad(decbin($speed/10), 6, '0', STR_PAD_LEFT) 6 SOG В узлах!!! Speed over ground
	'course' => array('num',9,1,511),	// str_pad(decbin($course/10), 9, '0', STR_PAD_LEFT) 9 COG Course over ground in degrees
	'Position_latency' => str_pad(decbin(1), 1, '0', STR_PAD_LEFT), // 1 0 = Reported position latency is less than 5 seconds; 1 = Reported position latency is greater than 5 seconds = default
	'Spare' => str_pad(decbin(0), 1, '0', STR_PAD_LEFT) //1 Not used. Should be set to zero. Reserved for future use
)
);

if(@$aisData['shipname']) $aisData['shipname'] = strtoupper(rus2translit($aisData['shipname']));
$AISsentencies = array();
foreach($AISformatA as $type => $format){
	//echo "type=$type;\n\n";
	$aisSent = getNMEAsent($aisData,substr($type,0,2),$format);
	//echo "aisSent=$aisSent;\n";
	$AISsentencies[] = $aisSent;
	//if($aisData['turn'] and ($aisData['turn']!=-128)) {
	//	file_put_contents('digitrafficAIS.log',$aisSent,FILE_APPEND);
	//	echo "aisData['turn']={$aisData['turn']};\n";
	//}
}
//if(!$aisData['to_bow']) file_put_contents('digitrafficAIS.log',$AISsentencies,FILE_APPEND);

return $AISsentencies;
} // end function toAISphrases

function getNMEAsent($aisData,$type,$format) {
/* Возвращает строку -- выражение NMEA AIS типа $type 
$format: array('num',bits,multiplicator,default)
$format: array('str',bits)
*/ 
if(!is_numeric($aisData['mmsi'])) $aisData['mmsi'] = str_pad(substr(crc32($aisData['mmsi']),0,9),9,'0');
//echo "aisData['mmsi']={$aisData['mmsi']}\n";
$aisData['type'] = $type;

//print_r($aisData);
$aisSent = '';
//echo "type=$type; format:"; print_r($format); //echo "aisData:"; print_r($aisData);

foreach($format as $key => $field){	// каждое поле, требуемое в посылке данного типа

	//if($key=='turn') echo "[getNMEAsent] {$aisData['mmsi']} aisData[$key]={$aisData[$key]};\n";

	if(is_array($field)) {

		//if($key=='eta') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
		//if($key=='destination') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
		//if($key=='course') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
		//if($key=='heading') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n\n";
		//if($key=='shiptype') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n\n";
		//if($key=='status') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n\n";
		//if($aisData['mmsi']=='230985490'){
			//if($key=='course') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='heading') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='lon') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='lat') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='length') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='beam') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='to_bow') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='to_stern') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='to_port') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n";
			//if($key=='to_starboard') echo "[getNMEAsent] aisData[$key]={$aisData[$key]};\n\n";
		//}

		switch($field[0]){
		case 'num': 	// число
			//$field = str_pad(substr(decbin(round($aisData[$key]*$field[2])),-$field[1]), $field[1], '0', STR_PAD_LEFT);
			if(isset($aisData[$key]))	$field = str_pad(substr(decbin(round($aisData[$key]*$field[2])),-$field[1]), $field[1], '0', STR_PAD_LEFT);
			else $field = str_pad(decbin($field[3]), $field[1], '0', STR_PAD_LEFT);
			break;
		case 'str': 	// строка
			//echo "aisData[$key]={$aisData[$key]};\n";
			if(isset($aisData[$key]))	$field = char2bin($aisData[$key], $field[1]);
			else $field = char2bin(str_repeat('@',20), $field[1]);
			break;
		case 'lon': 	// долгота в 1/10000 градуса
			$field = str_pad(decbin(mk_ais_lon(@$aisData[$key])), 28, '0', STR_PAD_LEFT);
			//echo "lon=$field\n";
			break;
		case 'lat': 	// широта в 1/10000 градуса
			$field = str_pad(decbin(mk_ais_lat(@$aisData[$key])), 27, '0', STR_PAD_LEFT);
			//echo "lat=$field\n";
			break;
		case 'lon10': 	// долгота в 1/10 градуса
			$field = str_pad(decbin(mk_ais_lon(@$aisData[$key],10)), 18, '0', STR_PAD_LEFT);
			//echo "lon10=$field\n";
			break;
		case 'lat10': 	// широта в 1/10 градуса
			$field = str_pad(decbin(mk_ais_lat(@$aisData[$key],10)), 17, '0', STR_PAD_LEFT);
			//echo "lat10=$field\n";
			break;
		}
	}
	//else echo "$key: $field;\n\n";
	$aisSent .= $field;
}

$aisSent = mk_ais($aisSent);
return $aisSent;
} // end function getNMEAsent


// This functions pick up from https://github.com/ais-one/phpais
// Copyright 2014 Aaron Gong Hsien-Joen <aaronjxz@gmail.com>

function mk_ais_lat($lat,$mes=10000) {
/* Делает AIS представление широты
широта -- в десятичных градусах 
Результат -- в десятитысячных минуты при умолчальном значении $mes
для сообщения № 27 $mes должна быть равна 10 -- результат в десятых минуты

результат надо кодировать в строку бит также, как и другие числа
*/
//$lat = 1.2569;
if(($lat === null) or ($lat === false)) $lat = 91;
if ($lat<0.0) {
	$lat = -$lat;
	$neg=true;
}
else $neg=false;
$latd = 0x00000000;
$latd = intval($lat * 60.0*$mes);
if ($neg==true) {
	$latd = ~$latd;
	$latd+=1;
	$latd &= 0x07FFFFFF;
}
//echo "[mk_ais_lat] lat=$lat; latd=$latd;\n";
return $latd;
};	// end function mk_ais_lat

function mk_ais_lon($lon,$mes=10000) {
/* Делает AIS представление долготы
долгота -- в десятичных градусах 
Результат -- в десятитысячных минуты при умолчальном значении $mes
для сообщения № 27 $mes должна быть равна 10 -- результат в десятых минуты

результат надо кодировать в строку бит также, как и другие числа
*/
//$lon = 103.851;
if(($lon === null) or ($lon === false)) $lon = 181;
if ($lon<0.0) {
	$lon = -$lon;
	$neg=true;
}
else $neg=false;
$lond = 0x00000000;
$lond = intval($lon * 60.0*$mes);
if ($neg==true) {
	$lond = ~$lond;
	$lond+=1;
	$lond &= 0x0FFFFFFF;
}
//echo "[mk_ais_lon] lat=$lon; latd=$lond;\n";
return $lond;
}; // end function mk_ais_lon

function mk_ais_rot($rot){
/* rate of turn */

} // end function mk_ais_rat

function char2bin($name, $max_len) {
/* Кодирование строк.
В разных полях строки разной длины, и здесь дополняются @
 */
$len = strlen($name);
if ($len > $max_len) $name = substr($name,0,$max_len);
if ($len < $max_len) $pad = str_repeat('0', ($max_len - $len) * 6);
else $pad = '';
$rv = '';
$ais_chars = array(
	'@'=>0, 'A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'E'=>5, 'F'=>6, 'G'=>7, 'H'=>8, 'I'=>9,
	'J'=>10, 'K'=>11, 'L'=>12, 'M'=>13, 'N'=>14, 'O'=>15, 'P'=>16, 'Q'=>17, 'R'=>18, 'S'=>19,
	'T'=>20, 'U'=>21, 'V'=>22, 'W'=>23, 'X'=>24, 'Y'=>25, 'Z'=>26, '['=>27, '\\'=>28, ']'=>29,
	'^'=>30, '_'=>31, ' '=>32, '!'=>33, '\"'=>34, '#'=>35, '$'=>36, '%'=>37, '&'=>38, '\''=>39,
	'('=>40, ')'=>41, '*'=>42, '+'=>43, ','=>44, '-'=>45, '.'=>46, '/'=>47, '0'=>48, '1'=>49,
	'2'=>50, '3'=>51, '4'=>52, '5'=>53, '6'=>54, '7'=>55, '8'=>56, '9'=>57, ':'=>58, ';'=>59,
	'<'=>60, '='=>61, '>'=>62, '?'=>63
);
$_a = str_split($name);	// в PHP до 8.2.0 из пустой строки делается массив с одним значением. ПОсле -- пустой массив
if ($len) {
	foreach ($_a as $_1) {
		if (isset($ais_chars[$_1])) $dec = $ais_chars[$_1];
		else $dec = 0;
		$bin = str_pad(decbin( $dec ), 6, '0', STR_PAD_LEFT);
		$rv .= $bin;
		//echo "$_1 $dec ($bin)<br/>";
	}
}
return $rv.$pad;
}; // end function char2bin

function mk_ais($_enc, $_part=1,$_total=1,$_seq='',$_ch='A') {
/* Здесь только формирование самого сообщения: кодирование и контрольная сумма
Содержательная часть в виде строки бит передаётся сюда уже готовой
$_enc строка бит всех полей сообщения
$_seq	sequential message ID for multi-sentence messages
*/
$len_bit = strlen($_enc);
$rem6 = $len_bit % 6;
$pad6_len = 0;
if ($rem6) $pad6_len = 6 - $rem6;
//echo  $pad6_len.'<br>';
$_enc .= str_repeat("0", $pad6_len); // pad the text...
$len_enc = strlen($_enc) / 6;
//echo $_enc.' '.$len_enc.'<br/>';

$itu = '';

for ($i=0; $i<$len_enc; $i++) {
	$offset = $i * 6;
	$dec = bindec(substr($_enc,$offset,6));
	if ($dec < 40) $dec += 48;
	else $dec += 56;
	//echo chr($dec)." $dec<br/>";
	$itu .= chr($dec);
}

// add checksum
$chksum = 0;
$itu = "AIVDM,$_part,$_total,$_seq,$_ch,".$itu.",0";

$len_itu = strlen($itu);
for ($i=0; $i<$len_itu; $i++) {
	$chksum ^= ord( $itu[$i] );
}

$hex_arr = array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F');
$lsb = $chksum & 0x0F;
if ($lsb >=0 && $lsb <= 15 ) $lsbc = $hex_arr[$lsb];
else $lsbc = '0';
$msb = (($chksum & 0xF0) >> 4) & 0x0F;
if ($msb >=0 && $msb <= 15 ) $msbc = $hex_arr[$msb];
else $msbc = '0';

$itu = '!'.$itu."*{$msbc}{$lsbc}\r\n";
return $itu;
}; // end function mk_ais

?>
