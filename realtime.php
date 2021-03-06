<?php

$format = @$_REQUEST['format'];
$fake   = @$_REQUEST['fake'];

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

// $filename = '/var/tmp/realtime.json';
// file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

if ($format === 'text') {
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
            $record['stopNote'] = strtoupper($record['stopNote']);
            $asciiTable->rows[] = $record;
        }
        $asciiTable->rows[] = null;
    }
    header('Content-type: text/plain');
    print $asciiTable;
} else {
    $data = [];
    $routes = $realtimeData->getRouteNumbers();
    $data['routes'] = [];
    $data['recordsByRoute'] = [];
    foreach ($routes as $route) {
        $records = $realtimeData->getVehicleTripInfoByRoute($route);
        if (count($records)) {
            $data['routes'][] = $route;
            $isFirstRow = true;
            foreach ($records as &$record) {
                $record['isFirstRow'] = $isFirstRow;
                $isFirstRow = false;
                $record['classes'] = [];
                $record['classes'][] = 'realtimeTable__row--vehicleId-' . $record['vehicleId'];
                $record['classes'][] = 'realtimeTable__row--routeId-' . $record['routeId'];
                if (!$fake) {
                    if (is_new_vehicle($record['vehicleId'])) {
                        $record['classes'][] = 'realtimeTable__row--newVehicle';
                    }
                    if (is_unknown_vehicle($record['vehicleId'])) {
                        $record['classes'][] = 'realtimeTable__row--unknownVehicle';
                    }
                    if (is_rapid_tarc_vehicle($record['vehicleId'])) {
                        $record['classes'][] = 'realtimeTable__row--rapidTarcVehicle';
                    }
                }
                if ($record['tripId']) {
                    $record['tripIdLink'] = '/t/trip-update.php?tripid=' . urlencode($record['tripId']) . '&compact=1';
                }
                $record['vehicleIdDisplayed'] = intval($record['vehicleId']);
                if ($fake) {
                    $record['vehicleIdDisplayed']= fake_bus($record['vehicleIdDisplayed']);
                }
            }
            unset($record);
            $data['recordsByRoute'][$route] = $records;
        }
    }
    unset($route);
    include 'realtime.tpl.html';
}
