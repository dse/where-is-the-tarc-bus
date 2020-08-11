<?php
namespace GTFS;

use GuzzleHttp\Client;
use \Exception;

require_once __DIR__ . "/../utilities.php";

class RealtimeData {
    public $vehiclePositionsJsonUrl = 'http://googletransit.ridetarc.org/realtime/Vehicle/VehiclePositions.json';
    public $realtimeFeedJsonUrl     = 'http://googletransit.ridetarc.org/realtime/GTFS-RealTime/TrapezeRealTimeFeed.json';
    public $response;
    public $body;
    public $data;
    public $client;
    public $staticData;
    public $timestamp;
    public function getVehiclePositionsJson() {
        return $this->getJson($this->vehiclePositionsJsonUrl);
    }
    public function getRealtimeFeedJson() {
        $result = $this->getJson($this->realtimeFeedJsonUrl);
        if ($result) {
            $this->timestamp = $result->header->timestamp;
        }
        return $result;
    }
    private $dataByUrl = [];
    public function getJson($url) {
        if (array_key_exists($url, $this->dataByUrl)) {
            return $this->dataByUrl[$url];
        }
        if (!$this->client) {
            $this->client = new Client();
        }
        $this->response = null;
        $this->body     = null;
        $this->data     = null;
        $response = $this->response = $this->client->request('GET', $url);
        if ($response->getStatusCode() >= 400) {
            throw new Exception(sprintf(
                '%s: %s %s', $url, $response->getStatusCode(), $response->getReasonPhrase()
            ));
        }
        $body = $this->body = (string)($response->getBody());
        $contentType = $response->getHeader('Content-Type');
        $data = $this->data = json_decode($body);
        return $this->dataByUrl[$url] = $data;
    }

    private $vehiclesByRoute;
    public function getVehiclesByRoute() {
        if ($this->vehiclesByRoute) {
            return $this->vehiclesByRoute;
        }
        $data = $this->getRealtimeFeedJson();
        $vehicles = [];
        foreach ($data->entity as $entity) {
            $vehicle = $entity->vehicle;
            if (!$vehicle) {
                continue;
            }
            $route = $vehicle->trip->route_id;
            if (!array_key_exists($route, $vehicles)) {
                $vehicles[$route] = [];
            }
            $vehicles[$route][] = $entity;
        }
        return $this->vehiclesByRoute = $vehicles;
    }
    private $tripUpdates = [];
    public function getTripUpdates() {
        if ($this->tripUpdates) {
            return $this->tripUpdates;
        }
        $data = $this->getRealtimeFeedJson();
        $tripUpdates = [];
        foreach ($data->entity as $entity) {
            $tripUpdate = $entity->trip_update;
            if (!$tripUpdate) {
                continue;
            }
            $stopTimeUpdateArray = $tripUpdate->stop_time_update;
            $tripId = $tripUpdate->trip->trip_id;
            $startDate = $tripUpdate->trip->start_date;
            $feedInstanceId = $this->staticData->getFeedInstanceId($startDate);
            $tripUpdates[$feedInstanceId][$tripId] = $tripUpdate;
        }
        return $this->tripUpdates = $tripUpdates;
    }
    private $routeNumbers;
    public function getRouteNumbers() {
        if ($this->routeNumbers) {
            return $this->routeNumbers;
        }
        $data = $this->getRealtimeFeedJson();
        $routes = [];
        foreach ($data->entity as $entity) {
            $vehicle = $entity->vehicle;
            if (!$vehicle) {
                continue;
            }
            $route = $vehicle->trip->route_id;
            $routes[$route] = true;
        }
        $routes = array_keys($routes);
        sort($routes, SORT_NUMERIC);
        return $this->routeNumbers = $routes;
    }
    public function getVehicleTripInfoByRoute($route) {
        $tripInfo = [];
        $vehiclesByRoute = $this->getVehiclesByRoute();
        $vehicles = $vehiclesByRoute[$route];
        foreach ($vehicles as $vehicle) {
            $tripInfo[] = $this->getVehicleTripInfoByVehicle($vehicle);
        }
        return $tripInfo;
    }
    public function getVehicleTripInfoByVehicle($vehicle) {
        $result = [];

        $vehicleId = $result['vehicleId']      = $vehicle->vehicle->vehicle->id;
        $tripId    = $result['tripId']         = $vehicle->vehicle->trip->trip_id;
        $routeId   = $result['routeId']        = $vehicle->vehicle->trip->route_id;
        $date      = $result['date']           = $vehicle->vehicle->trip->start_date;
        $fiid      = $result['feedInstanceId'] = $this->staticData->getFeedInstanceId($date);

        $tripUpdate = $this->getTripUpdates()[$fiid][$tripId];
        if (!$tripUpdate) {
            return result;
        }
        $tripRecord = $this->staticData->getTripRecord($fiid, $tripId);
        if ($tripRecord) {
            $result['tripHeadsign'] = $tripRecord['trip_headsign'];
        }

        $asOfTimestamp = $result['asOfTimestamp'] = $tripUpdate->timestamp;
        $asOf = $result['asOf'] = $asOfTimestamp ? strftime('%H:%M:%S', $asOfTimestamp) : null;

        $stopTimeUpdateArray = $tripUpdate->stop_time_update;
        if (!$stopTimeUpdateArray || !count($stopTimeUpdateArray)) {
            return result;
        }

        foreach ($stopTimeUpdateArray as $update) {
            $arrival = $update->arrival->time;
            $departure = $update->departure->time;
            if ((isset($arrival) && $arrival >= $this->timestamp) ||
                (isset($departure) && $departure >= $this->timestamp)) {
                $stopTimeUpdate = $update;
                break;
            }
        }
        if (empty($stopTimeUpdate)) {
            $stopTimeUpdate = $stopTimeUpdateArray[0];
        }
        if (empty($stopTimeUpdate)) {
            return $result;
        }

        $nextStopId = $result['nextStopId'] = $stopTimeUpdate->stop_id;
        $stopSequence = $result['stopSequence'] = $stopTimeUpdate->stop_sequence;
        $tripStops = $this->staticData->getTripStops($fiid, $tripId);
        $stopSequenceNumbers = array_map(function ($x) { return intval($x); }, array_column($tripStops, 'stop_sequence'));
        $firstStopSequence = min($stopSequenceNumbers);
        $lastStopSequence  = max($stopSequenceNumbers);

        foreach ($tripStops as $stop) {
            if ($stop['stop_id'] === $nextStopId) {
                $nextStop = $stop;
                break;
            }
        }
        if (empty($nextStop) || !$nextStop) {
            foreach ($tripStops as $stop) {
                if ($stop['stop_sequence'] === $stopSequence) {
                    $nextStop = $stop;
                    break;
                }
            }
        }
        if (empty($nextStop) || !$nextStop) {
            $nextStop = $this->staticData->getStop($fiid, $nextStopId);
        }
        if (empty($nextStop) || !$nextStop) {
            return $result;
        }
        $nextStopName = $result['nextStopName'] = $nextStop['stop_name'];
        if (!preg_match('/\S/', $nextStopName)) {
            $nextStopName = $result['nextStopName'] = $nextStop['stop_name'];
        }

        $expectedArrivalTimestamp   = $result['expectedArrivalTimestamp']   = $stopTimeUpdate->arrival->time;
        $expectedDepartureTimestamp = $result['expectedDepartureTimestamp'] = $stopTimeUpdate->departure->time;

        $dueArrivalTime             = $result['dueArrivalTime']        = @$nextStop['arrival_time'];
        $dueDepartureTime           = $result['dueDepartureTime']      = @$nextStop['departure_time'];
        $dueArrivalTimestamp        = $result['dueArrivalTimestamp']   = $dueArrivalTime   ? $this->getTimestamp($date, $dueArrivalTime)   : null;
        $dueDepartureTimestamp      = $result['dueDepartureTimestamp'] = $dueDepartureTime ? $this->getTimestamp($date, $dueDepartureTime) : null;

        $arrivalDelaySeconds   = $result['arrivalDelaySeconds']   = $stopTimeUpdate->arrival->delay;
        $departureDelaySeconds = $result['departureDelaySeconds'] = $stopTimeUpdate->departure->delay;
        if (!$arrivalDelaySeconds && $dueArrivalTimestamp) {
            $arrivalDelaySeconds = $expectedArrivalTimestamp - $dueArrivalTimestamp;
        }
        if (!$departureDelaySeconds && $dueDepartureTimestamp) {
            $departureDelaySeconds = $expectedDepartureTimestamp - $dueDepartureTimestamp;
        }
        $arrivalDelayMinutes   = $result['arrivalDelayMinutes']   = isset($arrivalDelaySeconds)   ? round($arrivalDelaySeconds   / 60) : null;
        $departureDelayMinutes = $result['departureDelayMinutes'] = isset($departureDelaySeconds) ? round($departureDelaySeconds / 60) : null;

        $expectedArrival   = $result['expectedArrival']   = $expectedArrivalTimestamp   ? strftime('%H:%M:%S', $expectedArrivalTimestamp)   : null;
        $expectedDeparture = $result['expectedDeparture'] = $expectedDepartureTimestamp ? strftime('%H:%M:%S', $expectedDepartureTimestamp) : null;
        $dueArrival        = $result['dueArrival']        = $dueArrivalTimestamp        ? strftime('%H:%M:%S', $dueArrivalTimestamp)        : null;
        $dueDeparture      = $result['dueDeparture']      = $dueDepartureTimestamp      ? strftime('%H:%M:%S', $dueDepartureTimestamp)      : null;

        $isFirstStop = $result['isFirstStop'] = ($firstStopSequence === $nextStop['stop_sequence']);
        $isLastStop  = $result['isLastStop']  = ($lastStopSequence === $nextStop['stop_sequence']);

        $result['stopNote'] = $isFirstStop ? 'FIRST STOP' : ($isLastStop ? 'LAST STOP' : null);

        return $result;
    }
    public function getTimestamp($yyyymmdd, $hhmmss = '12:00:00') {
        return get_yyyymmdd_timestamp($yyyymmdd, $hhmmss);
    }
}
