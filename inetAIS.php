<?php
/*
https://meri.digitraffic.fi/api/ais/v1/locations?latitude=60.1688&longitude=24.939&radius=30

version 0.1
*/
require_once("fCommon.php");
require_once("fAIS.php");
require_once("params.php");

// Входящее соединение для клиентов
$inSocket = stream_socket_server("tcp://$inetAIShost:$inetAISport",$errno,$errstr);
if(!$inSocket) exit("Imposible to create inbound socket: $errstr\n");

$instrumentsData = array('AIS'=>array());	//  собственно собираемые / кешируемые данные

$getDataTimeout = min(min($gpsdProxyTimeouts['AIS']),$getDataTimeout);
echo "Gets data from AIS source every $getDataTimeout sec.\n";
echo "Sends TPV every {$AISintervals['TPV']} sec, and other info every {$AISintervals['metainfo']} sec.\n";

$rotateBeam = array("|","/","-","\\");
$rBi = 0;

$inboundConnects = array();	// потоки входящих соединений, array
$externalProcesses = array();	// индексный массив внешних процессов, вида: array("key or id" => array("process"=>resource,"pipes"=>array(),'inString'=>""))
$outPipes = array();	// массив исходящих потоков
$errPipes = array();		// массив ошибочных потоков
$mesNMEA = array();	// массив сообщений AIS для отправки клиентам, воспринимается как очередь
$lastGetFromSource = 0;
do{
	$inPipes = $inboundConnects;	// будем слушать уже открытые потоки
	$inPipes[] = $inSocket;	// будем слушать входной сокет
	foreach($externalProcesses as $process){	// для каждого запущенного внешнего процесса
		$inPipes[] = $process["pipes"][1]; // будем слушать поток его стандартного вывода (чисто для памяти: там лежат переменные, которые являются ссылками на ресурсы)
		$inPipes[] = $process["pipes"][2]; // будем слушать поток его stderr, потому что внешние процессы у нас -- скрипты php, и stderr собственно скрипта выводится в stdout php
	}
	$errPipes = $inboundConnects;	// проверять будем только клиентские потоки, потому что см. выше

	if($inboundConnects) {
		$timeout = $getDataTimeout;
		
		echo($rotateBeam[$rBi]);	// вращающаяся палка
		//echo " Изменилось $nStreams потоков. Недавно изменённых целей ";
		echo " Connected ".(count($inboundConnects))." clients. Recently changed targets ";
		if(count($recievedMMSI)) $countrecievedMMSI = count($recievedMMSI);	// таким образом, в $countrecievedMMSI количество последних когда-то изменённых целей, а не факт, что за последний оборот ничего не произошло
		echo "$countrecievedMMSI.            \r";
		$rBi++;
		if($rBi>=count($rotateBeam)) $rBi = 0;
	}
	else {
		$timeout = null;
		echo "No inbound connections, waiting            \r";
	}
		
	//$timeout = $getDataTimeout;	// для целей тестирования
	$nStreams = stream_select($inPipes,$outPipes,$errPipes,$timeout);
	
	// Проблемы. С нашей стороны?
	if($errPipes) {
		// всё так просто, потому что мы следим только за клиентскими потоками, не за потоками
		// внешних процессов
		foreach($errPipes as $streem){
			unset($inboundConnects[array_search($streem, $inboundConnects)]);	// удалим проблемный поток из списка действующих
			if(is_resource($streem)) fclose($streem);
			unset($streem);
			echo "There is a problem with the client streem, the streem is closed and deleted                      \n";
		}
	}
	// Чтение
	$recievedMMSI = array();	// массив изменившихся целей вида array($mmsi)
	if($inPipes) {
		//echo "\n inPipes:";var_dump($inPipes);echo "\n";
		//echo "\n externalProcesses:";var_dump($externalProcesses);echo "\n";
		//echo "\n inboundConnects:";var_dump($inboundConnects);echo "        \n
		$toDie = array();
		foreach($inPipes as $pipe){
			//echo "\ninPipes pipe="; var_dump($pipe);echo "          \n";
			if($pipe === $inSocket){	 //echo "во входной сокет кто-то постучался                                  \n";
				$inboundConnects[] = stream_socket_accept($inSocket, -1);
				continue;	// к следующему потоку
			}
			if(($procID = isExternalPipe($pipe,2))!==false){	// поток из stderr внешнего процесса
				$externalProcesses[$procID]['inString'] .= stream_get_contents($pipe);
				if(feof($pipe)) {
					echo "The problem {$externalProcesses[$procID]['inString']} with external process $procID              \n";
					//echo "Проблема с внешним процессом $procID              \n";
					$toDie[] = $procID;
				}
				continue;	// к следующему потоку
			}
			if(($procID = isExternalPipe($pipe,1))!==false){	// поток из stduot внешнего процесса
				//echo "внешний процесс $procID что-то вернул                           \n";
				$externalProcesses[$procID]['inString'] .= trim(stream_get_contents($pipe));
				if(feof($pipe)) {
					$AISvessels = unserialize($externalProcesses[$procID]['inString']);
					//$AISvessels = json_decode($externalProcesses[$procID]['inString'],true);
					//echo "AISvessels=";print_r($AISvessels);echo ";\n";
					if(!is_array($AISvessels)){
						echo "The problem '{$externalProcesses[$procID]['inString']}' with external process $procID             \n";
					}
					$toDie[] = $procID;
					// updInstrumentsData понимает как набор с координатами, так и набор с метаинформацией
					if($AISvessels) {
						$recievedMMSI = array_unique(array_merge($recievedMMSI,updInstrumentsData($AISvessels)));	// плоский массив
						//echo "имеется целей AIS в instrumentsData ".count($instrumentsData['AIS'])."\n";
						list($noMetaData,$deletedMMSI) = chkFreshOfData();	// Проверим актуальность данных и получим список тех, для кого нет полной информации. При этом в $recievedMMSI могли бы остаться mmsi удалённых в этом процессе объектов
						//echo "осталось свежих целей AIS в instrumentsData ".count($instrumentsData['AIS'])."\n";
						$recievedMMSI = array_diff($recievedMMSI,$deletedMMSI);	// теперь в $recievedMMSI mmsi изменённых целей AIS, оставшихся в $instrumentsData
					}
					$AISvessels = '';
				}
				continue;	// к следующему потоку
			}
			echo "Other streems with inbound data:                     \n";
			// вообще-то, там ничего не должно приходить, но telnet, например, присылает сообщение о закрытии соединения
			$res = fgets($pipe,2048);	// обязательно надо читать, иначе stream_socket_accept сразу будет возвращать поток, в котором что-то есть
			echo "res=$res;\n";
			if($res === false) closeClient($pipe);	// клиент отвалился
		}
		// Поскольку каждый внешний процесс имеет несколько потоков, нужно прочесть все потоки
		// прежде чем убивать внешний процесс и его потоки.
		// После прочтения всех потоков убиваем те внешние процессы, на которые указали
		array_walk(array_unique($toDie),'closeProcess');
		$externalProcesses = array_merge($externalProcesses);	// перенумеруем процессы с начала, чтобы их номера не увеличивались бесконечно
	}
	// Выполнение
	if((time()-$lastGetFromSource)>=$getDataTimeout) {	// спрашивать данные у источника не чаще указанного, а не каждый оборот
		$lastGetFromSource = time();
		// 	Получение координат
		foreach($AISinterestPoints as $label => $poi){	// опросим все точки
			//echo "Get AIS targets for $label point                 \n";
			openProcess("$phpCLIexec getAISdata.php",serialize($poi));
		}
		if($noMetaData and !is_resource($externalProcesses['getMetaDataProcess']['process'])){	// не запущен процесс получения метаданных
			echo "Has ".count($noMetaData)." AIS targets without full metadata                 \n";
			//echo "noMetaData=";print_r($noMetaData);
			openProcess("$phpCLIexec getMetaData.php",serialize($noMetaData),'getMetaDataProcess');
			$noMetaData = null;
		}
	}
	// Запись
	if($inboundConnects and $recievedMMSI){	// есть клиенты и есть, что передавать
		$mesNMEA = array_merge($mesNMEA,getAISData(array_intersect(array_keys($instrumentsData["AIS"]),$recievedMMSI)));
		//echo "$mesNMEA\n"; 
	};
	sendAIS();
	
	END_LOOP:
}while(true);
curl_close($ch);
?>
