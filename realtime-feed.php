<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Utility/getCachedContent.php';

$url = 'http://googletransit.ridetarc.org/realtime/GTFS-RealTime/TrapezeRealTimeFeed.json';

$pretty = @$_REQUEST['pretty'];
$compact = @$_REQUEST['compact'];

$jsonEncodeFlags = 0;
if ($pretty) {
    $jsonEncodeFlags |= JSON_PRETTY_PRINT;
}

$data = getCachedContent($url, 15);
if (!$data) {
    die("failed to get data for some reason");
}

http_response_code(200);
header('Content-Type: application/json');
$json = json_encode($data, $jsonEncodeFlags);
if ($compact) {
    require_once __DIR__ . '/compactify.php';
    $json = compactify($json);
}
echo $json;
exit(0);
