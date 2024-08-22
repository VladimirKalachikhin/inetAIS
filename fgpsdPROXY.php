<?php
$SEEN_GPS = 0x01; $SEEN_AIS = 0x08;
$AISdevice = array(
'class' => 'DEVICE',
'path' => 'inetAIS',
'activated' => date('c'),
'flags' => $SEEN_AIS,
'stopbits' => 1
);

function sendAIStogpsdPROXY(){
global $instrumentsData,$gpsdPROXYsocket,$AISdevice;

//echo "\naisData before send to gpsdPROXY: >"; print_r($instrumentsData);
$AISdata = array();
foreach($instrumentsData['AIS'] as $mmsi => $value){
	$AISdata[$mmsi] = $value['data'];
};
$msg = array('class'=>'netAIS','device'=>$AISdevice['path'],'data'=>$AISdata);
//echo "\naisData to send to gpsdPROXY: >"; print_r($msg);
$msg = json_encode($msg);
$msg .= "\n";
//echo "\naisData to send to gpsdPROXY ".strlen($msg)." bytes:\n";
//echo "$msg\n";
$res = @socket_write($gpsdPROXYsocket, $msg, strlen($msg)); 	// шлём данные
if($res === FALSE) { 	// клиент умер
	socket_close($gpsdPROXYsocket);	// 
	$connected = FALSE;
	echo "Failed to write data to gpsdPROXY socket by: " . @socket_strerror(socket_last_error($gpsdPROXYsocket)) . "\n";
	$gpsdPROXYsocket = null;
};
}; // end function sendAIStogpsdPROXY

function gpsdPROXYconnect($gpsdPROXYhost='127.0.0.1',$gpsdPROXYport='3838',$greeting='{"class":"VERSION","release":"inetAIS","rev":"1","proto_major":5,"proto_minor":3}'){
/* соединяется с gpsdPROXY как сервер gpsd в режиме WATCH посредством команды CONNECT 

*/
global $AISdevice;
if(!$gpsdPROXYhost) $gpsdPROXYhost='127.0.0.1'; 
if(!$gpsdPROXYport) $gpsdPROXYport='3838';
$gpsdPROXYsock = createSocketClient($gpsdPROXYhost,$gpsdPROXYport); 	// Соединение с gpsdPROXY
//echo "\ngpsdPROXYsock=$gpsdPROXYsock;\n"; var_dump($gpsdPROXYsock);
if($gpsdPROXYsock === FALSE) { 	// клиент умер
	$connected = FALSE;
	echo "\nFailed to connect to gpsdPROXY \n";
	return false;
};
$res = socket_write($gpsdPROXYsock, "\n\n", 2);	// gpsgPROXY не вернёт greeting, если не получит что-то. Ну, так получилось
$buf = socket_read($gpsdPROXYsock, 2048, PHP_NORMAL_READ); 	// читаем VERSION, PHP_NORMAL_READ -- ждать \n
//echo "buf: |$buf|\n";

$msg = "?CONNECT;\n"; 	// ?CONNECT={"host":"","port":""};
//echo "Send CONNECT\n";
$res = socket_write($gpsdPROXYsock, $msg, strlen($msg)); 	// шлём команду подключиться к нам как к gpsd
// handshaking as some gpsd
$msg = "$greeting\n"; 	// 
//echo "Send greeting\n";
$res = socket_write($gpsdPROXYsock, $msg, strlen($msg)); 	// шлём приветствие
$zeroCount = 0;	// счётчик пустых строк
do{		// 
	$buf = socket_read($gpsdPROXYsock, 2048, PHP_NORMAL_READ); 	// PHP_NORMAL_READ -- ждать \n
	//echo "buf: |$buf|\n";
	if($buf === FALSE) {
		$connected = FALSE;
		echo "\nBroke socket $gpsdPROXYsock during handshaking \n";
		break;
	};
	if(!$buf=trim($buf)) {	// пустые строки
		$zeroCount++;
		continue;
	};
	if($buf[0]!='?') { 	// это не команда протокола gpsd
		$zeroCount++;
		continue;
	};
	$buf = rtrim(substr($buf,1),';');	// ? ;
	list($command,$params) = explode('=',$buf);
	$params = trim($params);
	//echo "\nClient command=$command; params=$params;\n";
	if($params) $params = json_decode($params,TRUE);
	switch($command){
	case 'WATCH':
		$msg = json_encode(array('class' => 'DEVICES', 'devices' => array($AISdevice)));
		$msg .= "\n";
		//echo "Send DEVICES $msg \n";
		$res = socket_write($gpsdPROXYsock, $msg, strlen($msg)); 	// шлём DEVICES

		$msg = '{"class":"WATCH","enable":true,"json":true,"nmea":false,"raw":0,"scaled":true,"split24":true,"timing":false,"pps":false,"device":"'.$netAISdevice['path'].'","remote":"'.$netAISdevice['path'].'.php"}';
		$msg .= "\n";
		//echo "Send WATCH\n";
		$res = socket_write($gpsdPROXYsock, $msg, strlen($msg)); 	// шлём WATCH
		break;
	};
	$connected = TRUE;
	break;
}while($zeroCount<10);
if($connected) return $gpsdPROXYsock;
else return FALSE;
}; // end function gpsdPROXYconnect

function createSocketClient($host,$port){
/* создаёт сокет, соединенный с $host,$port на другом компьютере */
$sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(!$sock) {
	echo "[createSocketClient] Failed to create client socket by reason: " . socket_strerror(socket_last_error()) . "\n";
	return FALSE;
};
if(! @socket_connect($sock,$host,$port)){ 	// подключаемся к серверу
	echo "[createSocketClient] Failed to connect to remote server $host:$port by reason: " . socket_strerror(socket_last_error()) . "\n";
	return FALSE;
};
echo "Connected to $host:$port \n";
return $sock;
} // end function createSocketClient

?>
