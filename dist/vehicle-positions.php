<?php
$url = "http://googletransit.ridetarc.org/realtime/Vehicle/VehiclePositions.json";
$ch = curl_init($url);
if (!$ch) {
    http_response_code(500);
    exit(1);
}
curl_setopt($ch, CURLOPT_HEADER, TRUE);
curl_exec($ch);
