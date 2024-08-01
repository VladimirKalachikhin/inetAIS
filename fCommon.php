<?php
/*
updInstrumentsData($inInstrumentsDates)
chkFreshOfData()
createSocketServer($host,$port,$connections=2)
sendAIS()
isExternalPipe($pipe,$deskNum=1)
openProcess($cmd,$message='',$procID=null)
closeProcess($procID)
rus2translit($string)
*/

function updInstrumentsData($inInstrumentsDates){
global $instrumentsData;
if(is_string(array_keys($inInstrumentsDates)[0])){	// locations
	$inInstrumentsDates = $inInstrumentsDates['features'];
};
//echo "JSON AIS Data: "; print_r($inInstrumentsDates); echo "\n";
$recievedMMSI = array();	// mmsi in $inInstrumentsDates, для которых что-то изменилось
foreach($inInstrumentsDates as $inInstrumentsData){
	//echo "inInstrumentsData: "; var_dump($inInstrumentsData); echo "\n";
	$mmsi = trim((string)$inInstrumentsData['mmsi']);
	//$vehicle = 'm'.$mmsi;	//	невозможно сделать строковый ключ из одних цифр
	$vehicle = $mmsi;
	$instrumentsData['AIS'][$vehicle]['data']['mmsi'] = $mmsi;
	$now = (int)(@$inInstrumentsData['properties']["timestampExternal"]/1000);
	if(!$now) $now = time();
	//echo "now отличается от сейчас на ".((time()-$now)/60)."мин.\n";

	if(isset($inInstrumentsData['properties']['navStat'])) {
		$instrumentsData['AIS'][$vehicle]['data']['status'] = filter_var($inInstrumentsData['properties']['navStat'],FILTER_SANITIZE_NUMBER_INT); 	// Navigational status 0 = under way using engine, 1 = at anchor, 2 = not under command, 3 = restricted maneuverability, 4 = constrained by her draught, 5 = moored, 6 = aground, 7 = engaged in fishing, 8 = under way sailing, 9 = reserved for future amendment of navigational status for ships carrying DG, HS, or MP, or IMO hazard or pollutant category C, high speed craft (HSC), 10 = reserved for future amendment of navigational status for ships carrying dangerous goods (DG), harmful substances (HS) or marine pollutants (MP), or IMO hazard or pollutant category A, wing in ground (WIG);11 = power-driven vessel towing astern (regional use), 12 = power-driven vessel pushing ahead or towing alongside (regional use); 13 = reserved for future use, 14 = AIS-SART (active), MOB-AIS, EPIRB-AIS 15 = undefined = default (also used by AIS-SART, MOB-AIS and EPIRB-AIS under test)
		if($instrumentsData['AIS'][$vehicle]['data']['status'] == 15) $instrumentsData['AIS'][$vehicle]['data']['status'] = null;
		$instrumentsData['AIS'][$vehicle]['cachedTime']['status'] = $now;
		//echo "navStat={$inInstrumentsData['properties']['navStat']}; status={$instrumentsData['AIS'][$vehicle]['data']['status']};\n";
	}
	if(isset($inInstrumentsData['properties']['posAcc'])) {
		$instrumentsData['AIS'][$vehicle]['data']['accuracy'] = (int)filter_var($inInstrumentsData['properties']['posAcc'],FILTER_SANITIZE_NUMBER_INT); 	// Position accuracy The position accuracy (PA) flag should be determined in accordance with Table 50 1 = high (£ 10 m) 0 = low (>10 m) 0 = default
		$instrumentsData['AIS'][$vehicle]['cachedTime']['accuracy'] = $now;
	}
	if(isset($inInstrumentsData['properties']['rot'])){
		$instrumentsData['AIS'][$vehicle]['data']['turn'] = (int)filter_var($inInstrumentsData['properties']['rot'],FILTER_SANITIZE_NUMBER_INT); 	// тут чёта сложное...  Rate of turn ROTAIS 0 to +126 = turning right at up to 708° per min or higher 0 to –126 = turning left at up to 708° per min or higher Values between 0 and 708° per min coded by ROTAIS = 4.733 SQRT(ROTsensor) degrees per min where  ROTsensor is the Rate of Turn as input by an external Rate of Turn Indicator (TI). ROTAIS is rounded to the nearest integer value. +127 = turning right at more than 5° per 30 s (No TI available) –127 = turning left at more than 5° per 30 s (No TI available) –128 (80 hex) indicates no turn information available (default). ROT data should not be derived from COG information.
		if($instrumentsData['AIS'][$vehicle]['data']['turn'] == 0x80) $instrumentsData['AIS'][$vehicle]['data']['turn'] = null;	// -128 ?
		$instrumentsData['AIS'][$vehicle]['cachedTime']['turn'] = $now;
		//echo "inInstrumentsData['properties']['rot']={$inInstrumentsData['properties']['rot']}; instrumentsData['AIS'][$vehicle]['data']['turn']={$instrumentsData['AIS'][$vehicle]['data']['turn']};\n";
	}
	if(isset($inInstrumentsData['geometry']['coordinates'])){
		list($instrumentsData['AIS'][$vehicle]['data']['lon'],$instrumentsData['AIS'][$vehicle]['data']['lat']) = $inInstrumentsData['geometry']['coordinates'];
		$instrumentsData['AIS'][$vehicle]['cachedTime']['lon'] = $now;
		$instrumentsData['AIS'][$vehicle]['cachedTime']['lat'] = $now;
	}
	if(isset($inInstrumentsData['properties']['sog'])){
		if($inInstrumentsData['properties']['sog']>1022) $instrumentsData['AIS'][$vehicle]['data']['speed'] = NULL;
		//else $instrumentsData['AIS'][$vehicle]['data']['speed'] = (float)filter_var($inInstrumentsData['properties']['sog'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION)*185.2/3600; 	// SOG Speed over ground in m/sec 	(in 1/10 knot steps (0-102.2 knots) 1 023 = not available, 1 022 = 102.2 knots or higher)
		else $instrumentsData['AIS'][$vehicle]['data']['speed'] = (float)filter_var($inInstrumentsData['properties']['sog'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION)*1852/3600; 	// у этих людей скорость в узлах?
		$instrumentsData['AIS'][$vehicle]['cachedTime']['speed'] = $now;
	}
	if(isset($inInstrumentsData['properties']['cog'])){
		//if($inInstrumentsData['properties']['cog']==3600) $instrumentsData['AIS'][$vehicle]['data']['course'] = NULL;
		if($inInstrumentsData['properties']['cog']==360) $instrumentsData['AIS'][$vehicle]['data']['course'] = NULL;
		//else $instrumentsData['AIS'][$vehicle]['data']['course'] = (float)filter_var($inInstrumentsData['properties']['cog'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION)/10; 	// Путевой угол. COG Course over ground in degrees ( 1/10 = (0-3599). 3600 (E10h) = not available = default. 3601-4095 should not be used)
		else $instrumentsData['AIS'][$vehicle]['data']['course'] = (float)filter_var($inInstrumentsData['properties']['cog'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Путевой угол. В градусах?
		//$instrumentsData['AIS'][$vehicle]['data']['course'] = $instrumentsData['AIS'][$vehicle]['data']['heading'];
		$instrumentsData['AIS'][$vehicle]['cachedTime']['course'] = $now;
	}
	if(isset($inInstrumentsData['properties']['heading'])){
		if($inInstrumentsData['properties']['heading']==511) $instrumentsData['AIS'][$vehicle]['data']['heading'] = NULL;
		else $instrumentsData['AIS'][$vehicle]['data']['heading'] = (float)filter_var($inInstrumentsData['properties']['heading'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Истинный курс. True heading Degrees (0-359) (511 indicates not available = default)
		$instrumentsData['AIS'][$vehicle]['cachedTime']['heading'] = $now;
	}
	//if(isset($inInstrumentsData['properties']['timestampExternal'])) $instrumentsData['AIS'][$vehicle]['timestamp'] = (int)(filter_var($inInstrumentsData['properties']['timestampExternal'],FILTER_SANITIZE_NUMBER_INT)/1000);
	if(isset($inInstrumentsData['timestamp'])) {	// из метадаты
		$timestamp = (int)(filter_var($inInstrumentsData['timestamp'],FILTER_SANITIZE_NUMBER_INT)/1000);
		if($instrumentsData['AIS'][$vehicle]['timestamp'] < $timestamp) $instrumentsData['AIS'][$vehicle]['timestamp'] = $timestamp;
		$recievedMMSI[] = $vehicle;	// если понадобилось изменит metadata, то цель точно изменилась
	}
	if(isset($inInstrumentsData['properties']['timestamp'])){
		if($inInstrumentsData['properties']['timestamp']>59) $timestamp = $now;
		else $timestamp = $now - (int)filter_var($inInstrumentsData['properties']['timestamp'],FILTER_SANITIZE_NUMBER_INT); 	// Unis timestamp. Time stamp UTC second when the report was generated by the electronic position system (EPFS) (0-59, or 60 if time stamp is not available, which should also be the default value, or 61 if positioning system is in manual input mode, or 62 if electronic position fixing system operates in estimated (dead reckoning) mode, or 63 if the positioning system is inoperative)
		if(@$instrumentsData['AIS'][$vehicle]['timestamp'] != $timestamp) {
			$recievedMMSI[] = $vehicle;	// таким образо, в recievedMMSI только изменившиеся цели
			$instrumentsData['AIS'][$vehicle]['timestamp'] = $timestamp;
		}
	}
	if(isset($inInstrumentsData['raim'])) $instrumentsData['AIS'][$vehicle]['data']['raim'] = (int)filter_var($inInstrumentsData['raim'],FILTER_SANITIZE_NUMBER_INT); 	// RAIM-flag Receiver autonomous integrity monitoring (RAIM) flag of electronic position fixing device; 0 = RAIM not in use = default; 1 = RAIM in use. See Table 50
	if(isset($inInstrumentsData['radio'])) $instrumentsData['AIS'][$vehicle]['data']['radio'] = (string)$inInstrumentsData['radio']; 	// Communication state
	if(isset($inInstrumentsData['imo'])) {
		$instrumentsData['AIS'][$vehicle]['data']['imo'] = (string)$inInstrumentsData['imo']; 	// IMO number 0 = not available = default – Not applicable to SAR aircraft 0000000001-0000999999 not used 0001000000-0009999999 = valid IMO number; 0010000000-1073741823 = official flag state number.
		if($instrumentsData['AIS'][$vehicle]['data']['imo'] === '0') $instrumentsData['AIS'][$vehicle]['data']['imo'] = NULL;
}
	if(isset($inInstrumentsData['callSign'])){
		if($inInstrumentsData['callSign']=='@@@@@@@') $instrumentsData['AIS'][$vehicle]['data']['callsign'] = NULL;
		elseif($inInstrumentsData['callSign']) $instrumentsData['AIS'][$vehicle]['data']['callsign'] = (string)$inInstrumentsData['callSign']; 	// Call sign 7 x 6 bit ASCII characters, @@@@@@@ = not available = default. Craft associated with a parent vessel, should use “A” followed by the last 6 digits of the MMSI of the parent vessel. Examples of these craft include towed vessels, rescue boats, tenders, lifeboats and liferafts.
	}
	if(isset($inInstrumentsData['name'])){
		if($inInstrumentsData['name']=='@@@@@@@@@@@@@@@@@@@@') $instrumentsData['AIS'][$vehicle]['data']['shipname'] = NULL;
		elseif($inInstrumentsData['name']) $instrumentsData['AIS'][$vehicle]['data']['shipname'] = filter_var($inInstrumentsData['name'],FILTER_SANITIZE_STRING); 	// Maximum 20 characters 6 bit ASCII, as defined in Table 47 “@@@@@@@@@@@@@@@@@@@@” = not available = default. The Name should be as shown on the station radio license. For SAR aircraft, it should be set to “SAR AIRCRAFT NNNNNNN” where NNNNNNN equals the aircraft registration number.
	}
	if(isset($inInstrumentsData['shipType'])) $instrumentsData['AIS'][$vehicle]['data']['shiptype'] = (int)filter_var($inInstrumentsData['shipType'],FILTER_SANITIZE_NUMBER_INT); 	// Type of ship and cargo type 0 = not available or no ship = default 1-99 = as defined in § 3.3.2 100-199 = reserved, for regional use 200-255 = reserved, for future use Not applicable to SAR aircraft
	if(isset($inInstrumentsData['referencePointA'])) $instrumentsData['AIS'][$vehicle]['data']['to_bow'] = (float)filter_var($inInstrumentsData['referencePointA'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Reference point for reported position. Also indicates the dimension of ship (m) (see Fig. 42 and § 3.3.3) For SAR aircraft, the use of this field may be decided by the responsible administration. If used it should indicate the maximum dimensions of the craft. As default should A = B = C = D be set to “0”
	if(isset($inInstrumentsData['referencePointB'])) $instrumentsData['AIS'][$vehicle]['data']['to_stern'] = (float)filter_var($inInstrumentsData['referencePointB'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Reference point for reported position.
	if(isset($inInstrumentsData['referencePointC'])) $instrumentsData['AIS'][$vehicle]['data']['to_port'] = (float)filter_var($inInstrumentsData['referencePointC'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Reference point for reported position.
	if(isset($inInstrumentsData['referencePointD'])) $instrumentsData['AIS'][$vehicle]['data']['to_starboard'] = (float)filter_var($inInstrumentsData['referencePointD'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION); 	// Reference point for reported position.
	if($instrumentsData['AIS'][$vehicle]['data']['to_bow']===0 and $instrumentsData['AIS'][$vehicle]['data']['to_stern']===0 and $instrumentsData['AIS'][$vehicle]['data']['to_port']===0 and $instrumentsData['AIS'][$vehicle]['data']['to_starboard']===0){
		$instrumentsData['AIS'][$vehicle]['data']['to_bow'] = null;
		$instrumentsData['AIS'][$vehicle]['data']['to_stern'] = null;
		$instrumentsData['AIS'][$vehicle]['data']['to_port'] = null;
		$instrumentsData['AIS'][$vehicle]['data']['to_starboard'] = null;
	}
	if(isset($inInstrumentsData['eta'])) {
		$instrumentsData['AIS'][$vehicle]['data']['eta'] = (string)$inInstrumentsData['eta']; 	// ETA Estimated time of arrival; MMDDHHMM UTC Bits 19-16: month; 1-12; 0 = not available = default  Bits 15-11: day; 1-31; 0 = not available = default Bits 10-6: hour; 0-23; 24 = not available = default Bits 5-0: minute; 0-59; 60 = not available = default For SAR aircraft, the use of this field may be decided by the responsible administration
		if($instrumentsData['AIS'][$vehicle]['data']['eta'] === '0') $instrumentsData['AIS'][$vehicle]['data']['eta'] = null;
	}
	if(isset($inInstrumentsData['draught'])) {
		$instrumentsData['AIS'][$vehicle]['data']['draught'] = (float)filter_var($inInstrumentsData['draught'],FILTER_SANITIZE_NUMBER_INT)/10; 	// Maximum present static draught In m ( 1/10 m, 255 = draught 25.5 m or greater, 0 = not available = default; in accordance with IMO Resolution A.851 Not applicable to SAR aircraft, should be set to 0)
		if($instrumentsData['AIS'][$vehicle]['data']['draught'] == 0) $instrumentsData['AIS'][$vehicle]['data']['draught'] = null;
	}
	if(isset($inInstrumentsData['destination'])){
		$instrumentsData['AIS'][$vehicle]['data']['destination'] = filter_var($inInstrumentsData['destination'],FILTER_SANITIZE_STRING); 	// Destination Maximum 20 characters using 6-bit ASCII; @@@@@@@@@@@@@@@@@@@@ = not available For SAR aircraft, the use of this field may be decided by the responsible administration
		if($instrumentsData['AIS'][$vehicle]['data']['destination'] == '@@@@@@@@@@@@@@@@@@@@') $instrumentsData['AIS'][$vehicle]['data']['destination'] = null;
		$instrumentsData['AIS'][$vehicle]['cachedTime']['destination'] = $now;
	}
	if(isset($inInstrumentsData['dte'])) {
		$instrumentsData['AIS'][$vehicle]['data']['dte'] = (int)filter_var($inInstrumentsData['dte'],FILTER_SANITIZE_NUMBER_INT); 	// DTE Data terminal equipment (DTE) ready (0 = available, 1 = not available = default) (see § 3.3.1)
		if($instrumentsData['AIS'][$vehicle]['data']['dte'] == 1) $instrumentsData['AIS'][$vehicle]['data']['dte'] = null;
	}
	if(isset($inInstrumentsData['length'])) {
		$instrumentsData['AIS'][$vehicle]['data']['length'] = (float)filter_var($inInstrumentsData['length'],FILTER_SANITIZE_NUMBER_INT)/10; 	// Length of ship in m
		if(!$instrumentsData['AIS'][$vehicle]['data']['length']) $instrumentsData['AIS'][$vehicle]['data']['length'] = null;
	}
	if(isset($inInstrumentsData['beam'])) {
		$instrumentsData['AIS'][$vehicle]['data']['beam'] = (float)filter_var($inInstrumentsData['beam'],FILTER_SANITIZE_NUMBER_INT)/10; 	// Beam of ship in m ширина, длина бимса.
		if(!$instrumentsData['AIS'][$vehicle]['data']['beam']) $instrumentsData['AIS'][$vehicle]['data']['beam'] = null;
}

	//echo "$mmsi shipType={$inInstrumentsData['shipType']}; shiptype={$instrumentsData['AIS'][$vehicle]['data']['shiptype']};\n";
	//echo "$mmsi destination={$inInstrumentsData['destination']}; destination={$instrumentsData['AIS'][$vehicle]['data']['destination']};\n";
	//echo "$mmsi course путевой угол={$instrumentsData['AIS'][$vehicle]['data']['course']};\n";
	//echo "$mmsi heading курс=".$instrumentsData['AIS'][$vehicle]['data']['heading'].";\n\n";
	//if($mmsi=='230985490'){
		//echo "cog={$inInstrumentsData['properties']['cog']}; course путевой угол={$instrumentsData['AIS'][$vehicle]['data']['course']};\n";
		//echo "heading={$inInstrumentsData['properties']['heading']}; heading курс=".$instrumentsData['AIS'][$vehicle]['data']['heading'].";\n\n";
		//echo "metadata timestamp={$inInstrumentsData['timestamp']}; метка времени={$instrumentsData['AIS'][$vehicle]['timestamp']};\n";
		//echo "{$instrumentsData['AIS'][$vehicle]['data']['lon']},{$instrumentsData['AIS'][$vehicle]['data']['lat']}\n";
		//echo "length={$inInstrumentsData['length']}; длина={$instrumentsData['AIS'][$vehicle]['data']['length']};\n";
		//echo "beam={$inInstrumentsData['beam']}; ширина={$instrumentsData['AIS'][$vehicle]['data']['beam']};\n";
		//echo "referencePointA={$inInstrumentsData['referencePointA']}; to_bow={$instrumentsData['AIS'][$vehicle]['data']['to_bow']};\n";
		//echo "referencePointB={$inInstrumentsData['referencePointB']}; to_stern={$instrumentsData['AIS'][$vehicle]['data']['to_stern']};\n";
		//echo "referencePointC={$inInstrumentsData['referencePointC']}; to_port={$instrumentsData['AIS'][$vehicle]['data']['to_port']};\n";
		//echo "referencePointD={$inInstrumentsData['referencePointD']}; to_starboard={$instrumentsData['AIS'][$vehicle]['data']['to_starboard']};\n";
	//}
};
return $recievedMMSI;
}; // end function updInstrumentsData

function chkFreshOfData(){
/* Проверим актуальность всех данных */
global $instrumentsData,$gpsdProxyTimeouts,$noVehicleTimeout;
$now = time();
$noMetaData = array();
$deletedMMSI = array();
foreach($instrumentsData['AIS'] as $id => $vehicle){
	//echo "id=$id; vehicle:";print_r($vehicle);
	if(($now - $vehicle['timestamp'])>$noVehicleTimeout) {
		$deletedMMSI[] = $id;
		unset($instrumentsData['AIS'][$id]); 	// удалим цель, последний раз обновлявшуюся давно
		//if($id=='230997540'){ print_r($instrumentsData['AIS'][$id]);
		//echo "Данные AIS для судна ".$id." протухли на ".($now - $vehicle['timestamp'])." сек при норме $noVehicleTimeout       \n";
		//}
		continue;	// к следующей цели AIS
	}
	if($instrumentsData['AIS'][$id]['cachedTime']){ 	// поищем, не протухло ли чего
		foreach($instrumentsData['AIS'][$id]['cachedTime'] as $type => $cachedTime){
			if(!is_null($vehicle['data'][$type]) and @$gpsdProxyTimeouts['AIS'][$type] and (($now - $cachedTime) > $gpsdProxyTimeouts['AIS'][$type])) {
				$instrumentsData['AIS'][$id]['data'][$type] = null;
				//echo "Данные AIS ".$type." для судна ".$id." протухли на ".($now - $cachedTime)." сек                     \n";
			}
			elseif(is_null($vehicle['data'][$type]) and @$gpsdProxyTimeouts['AIS'][$type] and (($now - $cachedTime) > (2*$gpsdProxyTimeouts['AIS'][$type]))) {
				unset($instrumentsData['AIS'][$id]['data'][$type]);
				unset($instrumentsData['AIS'][$id]['cachedTime'][$type]);
				//echo "Данные AIS ".$type." для судна ".$id." совсем протухли на ".($now - $cachedTime)." сек                     \n";
			}
		}
	}
	if(!@$instrumentsData['AIS'][$id]['data']['shipname']) $noMetaData[] = $id;	// соберём mmsi тех, для кого ещё не получена полная информация
}
return array($noMetaData,$deletedMMSI);
} // end function chkFreshOfData


function createSocketServer($host,$port,$connections=2){
/* создаёт сокет, соединенный с $host,$port на своей машине, для приёма входящих соединений 
в Ubuntu $connections = 0 означает максимально возможное количество соединений, а в Raspbian (Debian?) действительно 0
*/
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(!$sock) {
	echo "Failed to create server socket by reason: " . socket_strerror(socket_last_error()) . "\n";
	//return FALSE;
	exit('1');
}
for($i=0;$i<100;$i++) {
	$res = @socket_bind($sock, $host, $port);
	if(!$res) {
		echo "Failed to binding to $host:$port by: " . socket_strerror(socket_last_error($sock)) . ", waiting $i\r";
		sleep(1);
	}
	else break;
}
if(!$res) {
	echo "Failed to binding to $host:$port by: " . socket_strerror(socket_last_error($sock)) . "\n";
	//return FALSE;
	exit('1');
}
$res = socket_listen($sock,$connections); 	// 
if(!$res) {
	echo "Failed listennig by: " . socket_strerror(socket_last_error($sock)) . "\n";
	//return FALSE;
	exit('1');
}
return $sock;
} // end function createSocketServer


function sendAIS(){
/* Отправляет одно первое сообщение AIS из массива $mesNMEA в каждый из потоков в массиве $сonnects
В случае проблем с потоками они закрываются и удаляются из общего списка $inboundConnects
*/
global $mesNMEA,$inboundConnects,$outPipes;

if(!$inboundConnects) {	// клиентов нет
	$mesNMEA = array();	// клиенты могли отвалиться до того, как им всё отослали
	return;
}
//echo "[sendAIS] имеется для отсылки ".count($mesNMEA)." сообщений NMEA";
$message = array_pop($mesNMEA);	// возьмём сообщение для отсылки
if($message === null) {	// нечего посылать
	$outPipes = array();	// укажем stream_select, что всё отослано
	return;
}
//file_put_contents('digitrafficAIS.log',$message,FILE_APPEND);
foreach($inboundConnects as $streem){
	if(is_resource($streem)) {
		$res = fwrite($streem, $message);
		//echo "[sendAIS] res=$res;\n"; //print_r($inboundConnects);
		if($res === false){	// клиент отвалился
			unset($inboundConnects[array_search($streem, $inboundConnects)]);
			fclose($streem);
			unset($streem);
		};
	}
	else{	// клиент отвалился
		unset($inboundConnects[array_search($streem, $inboundConnects) ]);
		unset($streem);
	};
};
$outPipes = $inboundConnects;
} // end function sendAIS()

function isExternalPipe($pipe,$deskNum=1){
/* $deskNum - номер потока в описании процесса $descriptorspec */
//echo "[isExternalPipe] deskNum=$deskNum; pipe:";var_dump($pipe);echo "            \n";
global $externalProcesses;
$res = false;
foreach($externalProcesses as $key=>$process){
	//echo "[isExternalPipe] key=$key; pipes:";var_dump($process["pipes"]);echo "            \n";
	if($pipe === $process["pipes"][$deskNum]){
		$res = $key;
		break;
	}
}
return $res;
} // end function isExternalPipe

function openProcess($cmd,$message='',$procID=null){
/* Создаёт внешний процесс, указанный в строке $cmd, 
кладёт его в $externalProcesses с ключём $procID или следующим номером
отсылает процессу сообщение $message в stdin и тут же закрывает поток, подключенный к stdin
Возвращает $procID или false, если что не так.
*/
global $externalProcesses;
$descriptorspec = array(
0 =>array('pipe', 'r'),	// childs stdin
1 => array('pipe', 'w'),	// childs stdout
//2 => array('file', "error.txt", "a"),	// childs stderr
2 => array('pipe', 'r'),	// childs stderr
);
if($procID) $externalProcesses[$procID]['pipes'] = array();
else {
	$externalProcesses[] = array('pipes' => array());
	end($externalProcesses);         // move the internal pointer to the end of the array
	$procID = key($externalProcesses);  // fetches the key of the element pointed to by the internal pointer
}
$externalProcesses[$procID]['process'] = proc_open($cmd, $descriptorspec, $externalProcesses[$procID]['pipes']);
if(!$externalProcesses[$procID]['process']){
	unset($externalProcesses[$procID]);
	echo "Failed to create the external process $cmd            \n";
	return false;
}
$res = fwrite($externalProcesses[$procID]['pipes'][0], $message);
fclose($externalProcesses[$procID]['pipes'][0]);
$len = strlen($message);
if($res !== $len){
	echo "Writing to stdin of the external process $cmd failed. Sended $res bytes instead $len           \n";
	closeProcess($procID);
	return false;
}
return $procID;
} // end function openProcess

function closeProcess($procID){
global $externalProcesses;
foreach ($externalProcesses[$procID]['pipes'] as $pipe) {
	if (is_resource($pipe))	fclose($pipe);
}
$res = proc_close($externalProcesses[$procID]['process']);
unset($externalProcesses[$procID]);	// помним, что unset не перенумеровывает ключи
} // end function closeProcess

function closeClient($streem){
global $inboundConnects;
unset($inboundConnects[array_search($streem, $inboundConnects)]);
fclose($streem);
unset($streem);
}

function rus2translit($string) {
$converter = array(
'а' => 'a',   'б' => 'b',   'в' => 'v',
'г' => 'g',   'д' => 'd',   'е' => 'e',
'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
'и' => 'i',   'й' => 'y',   'к' => 'k',
'л' => 'l',   'м' => 'm',   'н' => 'n',
'о' => 'o',   'п' => 'p',   'р' => 'r',
'с' => 's',   'т' => 't',   'у' => 'u',
'ф' => 'f',   'х' => 'h',   'ц' => 'c',
'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

'А' => 'A',   'Б' => 'B',   'В' => 'V',
'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
'И' => 'I',   'Й' => 'Y',   'К' => 'K',
'Л' => 'L',   'М' => 'M',   'Н' => 'N',
'О' => 'O',   'П' => 'P',   'Р' => 'R',
'С' => 'S',   'Т' => 'T',   'У' => 'U',
'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya'
);
return strtr($string, $converter);
}; // end function rus2translit

?>
