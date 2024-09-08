<?php

include "config.php";
error_reporting(0);
date_default_timezone_set('Asia/Kolkata');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
 
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$id = $_GET['id'] ?? exit("Error: ID not provided.");
$data = $_GET['data'] ?? exit("Error: data not provided.");
$channelInfo = getChannel($id);
$userAgent = 'Mozilla/5.0';
$dashUrl = $channelInfo['streamData']['initialUrl'] ?? exit("Error: Stream URL not found.");
$mpd = str_replace("master.mpd", "dash/$data", $dashUrl);

echo fetchMPDManifest($mpd,$userAgent,$userIP);