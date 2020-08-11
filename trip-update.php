<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Utility/getCachedContent.php';
require_once __DIR__ . '/GTFS/StaticData.php';

use GTFS\StaticData;

$url = 'http://googletransit.ridetarc.org/realtime/TripUpdate/TripUpdates.json';

$tripid = $_REQUEST['tripid'];
$pretty = @$_REQUEST['pretty'];

$jsonEncodeFlags = 0;
if ($pretty) {
    $jsonEncodeFlags |= JSON_PRETTY_PRINT;
}

$tripUpdates = getCachedContent($url, 15);
if (!$tripUpdates) {
    die("failed to get vehicle positions for some reason");
}

$outputData = [];

$tripUpdate = null;
foreach ($tripUpdates->entity as $entity) {
    if ($entity->id === $tripid) {
        $tripUpdate = $entity;
        $outputData['tripUpdate'] = &$tripUpdate;
    }
}

if ($tripUpdate) {
    $startDate = @$tripUpdate->trip_update->trip->start_date;
    if (isset($startDate)) {
        $outputData['startDate'] = $startDate;
    }
}
if ($startDate) {
    $staticData = new StaticData();
    $feedInstanceId = $staticData->getFeedInstanceId($startDate);
}
if ($feedInstanceId) {
    $outputData['feedInstanceId'] = $feedInstanceId;
    $tripRecord = $staticData->getTripRecord($feedInstanceId, $tripid);
    $tripStops = $staticData->getTripStops($feedInstanceId, $tripid);
}
if ($tripRecord) {
    $outputData['tripRecord'] = &$tripRecord;
}
if ($tripStops) {
    $lastStop = $tripStops[count($tripStops) - 1];
}
if ($lastStop) {
    $outputData['lastStop'] = &$lastStop;
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($outputData, $jsonEncodeFlags);
exit(0);
