<?php

$apikey = ""; // get your own apiKey from " https://babel-in.xyz "
$worldwide = "no"; // set yes if u wanna restream else no
$genreName = "Babel-IN"; // for m3u category
$cacheFolder = "_cache_"; // set cache folder
$cacheTime = 60; // set cacheTime in secs

$serverAddress = $_SERVER['HTTP_HOST'] ?? 'default.server.address';
$serverPort = $_SERVER['SERVER_PORT'] ?? '80';
$serverScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$serverIP = @file_get_contents('https://api.ipify.org');
$dirPath = dirname($requestUri);
$portPart = ($serverPort != '80' && $serverPort != '443') ? ":$serverPort" : '';

if (!is_dir($cacheFolder)) {
    mkdir($cacheFolder, 0755, true);
}

function getChannel($id) {
    $json = @file_get_contents("jup.json");
    if ($json === false) exit("Error: Could not retrieve channel data."); 

    $channels = json_decode($json, true);
    if ($channels === null) exit("Error: Invalid JSON data.");

    foreach ($channels as $channel) {
        if ($channel['id'] == $id) {
            return $channel;
        }
    }
    exit("Error: Channel not found.");
}

function fetchMPDManifest($url, $userAgent, $userIP) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'User-Agent: ' . $userAgent,
        'X-Forwarded-For: ' . $userIP,
    ]);
    $manifestContent = curl_exec($curl);
    if ($manifestContent === false) return null;
    curl_close($curl);
    return $manifestContent;
}

function extractVideoUrlFromManifest($manifestContent, $baseVideoUrl, $userAgent, $userIP) {
    $xml = @simplexml_load_string($manifestContent);
    if ($xml === false) return null;

    foreach ($xml->Period->AdaptationSet as $adaptationSet) {
        if (isset($adaptationSet['contentType']) && (string)$adaptationSet['contentType'] === 'audio') {
            foreach ($adaptationSet->Representation as $representation) {
                if (isset($representation->SegmentTemplate)) {
                    $media = (string)$representation->SegmentTemplate['media'];
                    $startNumber = isset($representation->SegmentTemplate['startNumber']) ? (int)$representation->SegmentTemplate['startNumber'] : 0;
                    $repeatCount = isset($representation->SegmentTemplate->SegmentTimeline->S['r']) ? (int)$representation->SegmentTemplate->SegmentTimeline->S['r'] : 0;
                    $modifiedStartNumber = $startNumber + $repeatCount;
                    $mediaFileName = str_replace(['$RepresentationID$', '$Number$'], [(string)$representation['id'], $modifiedStartNumber], $media);

                    $videoUrl = $baseVideoUrl . '/dash/' . $mediaFileName;
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                'User-Agent: ' . $userAgent,
                                'X-Forwarded-For: ' . $userIP,
                            ],
                        ],
                    ]);

                    $videoContent = @file_get_contents($videoUrl, false, $context);
                    if ($videoContent === false) return null;

                    $hexVideoContent = bin2hex($videoContent);
                    $psshMarker = "000000387073736800000000edef8ba979d64acea3c827dcd51d21ed000000";
                    $pos = strpos($hexVideoContent, $psshMarker);
                    if ($pos !== false) {
                        $psshEnd = strpos($hexVideoContent, "0000", $pos + strlen($psshMarker));
                        if ($psshEnd !== false) {
                            $psshHex = substr($hexVideoContent, $pos, $psshEnd - $pos - 12);
                            $psshHex = str_replace("000000387073736800000000edef8ba979d64acea3c827dcd51d21ed00000018", "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed00000012", $psshHex);
                            $kidHex = substr($psshHex, 68, 32);
                            return [
                                'pssh' => base64_encode(hex2bin($psshHex)),
                                'kid' => substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20)
                            ];
                        }
                    }
                }
            }
        }
    }
    return null;
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        return $ip;
    } else {
        return 'IP address not valid';
    }
}

if ($worldwide === "no") {
    $userIP = getUserIP();
} else {
    $userIP = $serverIP;
}