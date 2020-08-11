<?php

$format = @$_REQUEST['format'];

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/GTFS/RealtimeData.php';
require_once __DIR__ . '/GTFS/StaticData.php';
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/Utility/ASCIITable.php';

use GuzzleHttp\Client;
use GTFS\RealtimeData;
use GTFS\StaticData;

$realtimeData = new RealtimeData();
$staticData = new StaticData();
$realtimeData->staticData = & $staticData;
$data = $realtimeData->getRealtimeFeedJson();

if ($format === '0') {
    // To make sure the rest of the system can see that file you wrote:
    // -   Edit /etc/systemd/system/multi-user.target.wants/apache2.service
    // -   Comment out the line that says "PrivateTmp=true"
    // -   Restart systemd, then Apache:
    //     -   sudo systemctl daemon-reload
    //     -   sudo systemctl restart apache2
    //         (reload will not suffice)
    $output = json_encode($data, JSON_PRETTY_PRINT);
    $filename = '/var/tmp/realtime.json';
    if (file_put_contents($filename, $output) === false) {
        http_status_code(500);
        exit;
    }
    header('Content-type: text/plain');
    printf("%d %s\n", strlen($output), $filename);
    exit;
}

$filename = '/var/tmp/realtime.json';
file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

$asciiTable = new ASCIITable();
$asciiTable->headings = [
    'Route', 'Headsign', 'Vehicle', 'Due', 'Expected', 'Delay', 'Stop', 'Note'
];
$asciiTable->keys = [
    'routeId', 'tripHeadsign', 'vehicleId', 'dueArrival', 'expectedArrival', 'arrivalDelayMinutes', 'nextStopName', 'stopNote'
];
$asciiTable->maxColumnWidth['tripHeadsign'] = 32;

$routes = $realtimeData->getRouteNumbers();
foreach ($routes as $route) {
    $records = $realtimeData->getVehicleTripInfoByRoute($route);
    foreach ($records as $record) {
        $asciiTable->rows[] = $record;
    }
    $asciiTable->rows[] = null;
}

header('Content-type: text/plain');
print $asciiTable;
