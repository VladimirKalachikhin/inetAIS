<?php  
ob_start();
require_once('fGPSD.php');
require_once("params.php");

$TPV = getPosAndInfo();
//print_r($TPV);
ob_end_clean();
//echo json_encode($TPV);
echo serialize($TPV);
?>
