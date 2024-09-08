<?php

include "config.php";
header("Cache-Control: max-age=84000, public");
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');

$channelInfo = json_decode(file_get_contents("jup.json"), true);
$inus_data = '#EXTM3U x-tvg-url="https://avkb.short.gy/tsepg.xml.gz"' . PHP_EOL . PHP_EOL;

foreach ($channelInfo as $entry) {
    $id = $entry['id'] ?? 'unknown';
    $name = $entry['channel_name'] ?? 'Unknown';
    $genre = $entry['channel_genre'][0] ?? 'Unknown';
    $logo = $entry['channel_logo'] ?? '';
    
    // Check if 'initialUrl' exists in 'streamData'
    $mpd = isset($entry['streamData']['initialUrl']) ? $entry['streamData']['initialUrl'] : '';
    $extension = pathinfo($mpd, PATHINFO_EXTENSION);
    $license_key_url = "$serverScheme://$serverAddress$dirPath/$id.key";

    // Generate playlist entry
    $inus_data .= '#EXTINF:-1 tvg-id="ts' . $id . '" tvg-logo="' . $logo . '" group-title="' . $genre . '", ' . $name . PHP_EOL;
    $inus_data .= '#KODIPROP:inputstream=inputstream.adaptive' . PHP_EOL;
    $inus_data .= '#KODIPROP:inputstreamaddon=inputstream.adaptive' . PHP_EOL;
    $inus_data .= '#KODIPROP:inputstream.adaptive.manifest_type=' . $extension . PHP_EOL;
    $inus_data .= '#KODIPROP:inputstream.adaptive.license_type=clearkey' . PHP_EOL;
    $inus_data .= '#KODIPROP:inputstream.adaptive.license_key=' . $license_key_url . PHP_EOL;

    // Append URL based on extension
    if ($extension === 'm3u8' && $mpd !== '') {
        $inus_data .= $mpd . PHP_EOL . PHP_EOL;
    } else {
        $mpd_url = "$serverScheme://$serverAddress$dirPath/$id.$extension";
        if ($mpd_url !== '') {
            $inus_data .= $mpd_url . "|X-Forwarded-For=$userIP" . PHP_EOL . PHP_EOL;
        }
    }
}

echo $inus_data;