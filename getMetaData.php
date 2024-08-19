<?php ob_start();
require_once("params.php");

$noMetaData = '';	// массив mmsi
$metaDataS = array(); 	// собираемые метаданные
do{
	$noMetaData .= trim(fgets(STDIN));
}while(!feof(STDIN));
//echo "noMetaData=$noMetaData;\n";
$noMetaData = unserialize($noMetaData);
//echo "noMetaData=";print_r($noMetaData);
if(!$noMetaData) return;

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Accept-Encoding: gzip, deflate, br',
'Cache-Control: no-cache',
'Connection: keep-alive'
));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, round($getDataTimeout/3));
curl_setopt($ch, CURLOPT_TIMEOUT, $getDataTimeout);
foreach($noMetaData as $mmsi){
	// Для всеъ целей оно в gzip, но для одной -- не сжато
	curl_setopt($ch, CURLOPT_URL,"$urlAISvessels/$mmsi");
	$metadata = curl_exec($ch);
	//print_r($info);
	$info = curl_getinfo($ch);
	if (!$metadata or curl_errno($ch) or substr($info['http_code'],0,1) !== '2') {
		echo "Failed to get metadata for mmsi $mmsi\n";
		continue;
	}
	$metaDataS[] = json_decode($metadata,true);
}
curl_close($ch);
ob_end_clean();
//echo json_encode($metaDataS);
echo serialize($metaDataS);
?>
