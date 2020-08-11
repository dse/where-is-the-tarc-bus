<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Utility/getCachedContent.php';

$url = 'http://googletransit.ridetarc.org/realtime/Vehicle/VehiclePositions.json';

$pretty = @$_REQUEST['pretty'];

$jsonEncodeFlags = 0;
if ($pretty) {
    $jsonEncodeFlags |= JSON_PRETTY_PRINT;
}

$vp = getCachedContent($url, 15);
if (!$vp) {
    die("failed to get vehicle positions for some reason");
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($vp, $jsonEncodeFlags);
exit(0);
