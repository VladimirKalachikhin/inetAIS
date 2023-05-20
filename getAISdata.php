<?php
/* a:3:{s:8:"latitude";d:60.1688;s:9:"longitude";d:24.939;s:6:"radius";i:5;}
*/
require_once("params.php");

$poi = '';
do{
	$poi .= trim(fgets(STDIN));
}while(!feof(STDIN));
$poi = unserialize($poi);
//print_r($poi);

$url = $urlAISlocations;
if($poi) $url .= "?latitude={$poi['latitude']}&longitude={$poi['longitude']}&radius={$poi['radius']}";
$ch = curl_init();
// Оно всегда отдаёт в gzip, и не хочет отдавать, если просить не в gzip.
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Accept-Encoding: gzip, deflate, br',
'Cache-Control: no-cache',
'Connection: keep-alive'
));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL,$url);
$AISlocations = curl_exec($ch);
$info = curl_getinfo($ch);
//print_r($info);
if (curl_errno($ch) || substr($info['http_code'],0,1) !== '2') {
	echo "Не удалось получить координаты целей AIS                        \n";
}
else echo serialize(json_decode(gzdecode($AISlocations),true));	// оно, конечно, странно serialize(json_decode, но для единообразия
?>
