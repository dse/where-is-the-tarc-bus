<?php // -*- web -*-
namespace GTFS;

use \PDO;
use \Exception;

require_once __DIR__ . '/../.env.php';
require_once __DIR__ . "/../utilities.php";

class StaticData {
    const GROUP_BY_ROUTE_ID = 1 << 0;
    const INDEX_BY_TRIP_ID  = 1 << 1;

    public $dsn = getenv('GTFS_STATIC_DATA_DSN');
    public $username = getenv('GTFS_STATIC_DATA_USERNAME');
    public $password = getenv('GTFS_STATIC_DATA_PASSWORD');
    public $agencyName = 'ridetarc.org';
    public $feedUrl = 'http://googletransit.ridetarc.org/feed/google_transit.zip';
    private $dbh;

    function __construct() {
        $this->dbh = new PDO($this->dsn, $this->username, $this->password);
    }

    function getAgencyId() {
        $sql = '
            select id
            from geo_gtfs_agency
            where name = :name
        ';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':name' => $this->agencyName ]);
        $result = $sth->fetchColumn();
        if (!isset($result)) {
            throw new Exception("no agency id for {$this->agencyName}");
        }
        return $result;
    }

    function getFeedId() {
        $sql = '
            select geo_gtfs_feed.id as id
            from geo_gtfs_feed
            join geo_gtfs_agency on geo_gtfs_feed.geo_gtfs_agency_id = geo_gtfs_agency.id
            where geo_gtfs_feed.url = :url
                and geo_gtfs_agency.name = :name
                and geo_gtfs_feed.is_active != 0
        ';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':url' => $this->feedUrl, ':name' => $this->agencyName ]);
        $result = $sth->fetchColumn();
        if (!isset($result)) {
            throw new Exception("no active feed id for {$this->agencyName}");
        }
        return $result;
    }

    private $feedInstanceId;
    function getFeedInstanceId($date) {
        $weekDay = strtolower(get_yyyymmdd_weekday($date, 'l')); // 'sunday' through 'saturday'
        if (!preg_match('/^\d\d\d\d\d\d\d\d$/', $date)) {
            throw new Exception('date must be in YYYYMMDD format');
        }
        if (!$this->feedInstanceId) {
            $this->feedInstanceId = [];
        }
        if (array_key_exists($date, $this->feedInstanceId)) {
            return $this->feedInstanceId[$date];
        }
        $sql = '
            select distinct geo_gtfs_feed_instance.id, last_modified
            from geo_gtfs_feed_instance
            join geo_gtfs_feed on geo_gtfs_feed_instance.geo_gtfs_feed_id = geo_gtfs_feed.id
            join geo_gtfs_agency on geo_gtfs_feed.geo_gtfs_agency_id = geo_gtfs_agency.id
            join gtfs_calendar on gtfs_calendar.geo_gtfs_feed_instance_id = geo_gtfs_feed_instance.id
            where geo_gtfs_feed.url = :url
                and geo_gtfs_agency.name = :name
                and start_date <= :date and end_date >= :date
                and (({weekDay} = 1) or
                (sunday = 0 and monday = 0 and tuesday = 0 and wednesday = 0 and
                thursday = 0 and friday = 0 and saturday = 0))
                order by last_modified desc
            limit 1
        ';
        $sql = preg_replace('/\\{weekDay\\}/', $weekDay, $sql);
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':url' => $this->feedUrl, ':name' => $this->agencyName, ':date' => $date ]);
        $feedInstanceId = $sth->fetchColumn();
        if (!isset($feedInstanceId)) {
            throw new Exception("no instance id for {$this->agencyName} on {$date}");
        }
        $this->feedInstanceId[$date] = $feedInstanceId;
        return $feedInstanceId;
    }

    private $tripRecords = [];
    function getTripRecord($feedInstanceId, $tripId) {
        if (($trip = @$this->tripRecords[$feedInstanceId][$tripId])) {
            return $trip;
        }
        $sql = '
            select *
            from gtfs_trips
            where geo_gtfs_feed_instance_id = :feedInstanceId and trip_id = :tripId;
        ';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':feedInstanceId' => $feedInstanceId, ':tripId' => $tripId ]);
        $result = $sth->fetch();
        return $this->tripRecords[$feedInstanceId][$tripId] = $result;
    }

    function getTripStops($feedInstanceId, $tripId) {
        $sql = '
            select gtfs_stops.stop_id, *
            from gtfs_trips
            join gtfs_stop_times on gtfs_trips.trip_id = gtfs_stop_times.trip_id
            join gtfs_stops      on gtfs_stop_times.stop_id = gtfs_stops.stop_id
            where gtfs_trips.geo_gtfs_feed_instance_id = :feedInstanceId
                and gtfs_stop_times.geo_gtfs_feed_instance_id = :feedInstanceId
                and gtfs_stops.geo_gtfs_feed_instance_id = :feedInstanceId
                and gtfs_trips.trip_id = :tripId
                order by stop_sequence asc;
        ';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':feedInstanceId' => $feedInstanceId, ':tripId' => $tripId ]);
        return $sth->fetchAll();
    }

    private $stops;
    function getStop($feedInstanceId, $stopId) {
        if (($stop = @$this->stops[$feedInstanceId][$stopId])) {
            return $stop;
        }
        $sql = 'select gtfs_stops.* from gtfs_stops where stop_id = :stopId and geo_gtfs_feed_instance_id = :feedInstanceId';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':feedInstanceId' => $feedInstanceId, ':stopId' => $stopId ]);
        return $this->stops[$feedInstanceId][$stopId] = $sth->fetch();
    }

    private $stops2;
    function getStop2($feedInstanceId, $tripId, $stopSequence) {
        if (($stop = @$this->stops2[$feedInstanceId][$tripId][$stopSequence])) {
            return $stop;
        }
        $sql = 'select gtfs_stops.*
                from gtfs_stops
                join gtfs_stop_times on gtfs_stops.stop_id = gtfs_stop_times.stop_id
                where gtfs_stops.geo_gtfs_feed_instance_id = :feedInstanceId
                    and gtfs_stop_times.geo_gtfs_feed_instance_id = :feedInstanceId
                    and gtfs_stop_times.trip_id = :tripId
                    and gtfs_stop_times.stop_sequence = :stopSequence';
        $sth = $this->dbh->prepare($sql);
        $sth->execute([ ':feedInstanceId' => $feedInstanceId, ':tripId' => $tripId, ':stopSequence' => $stopSequence ]);
        return $this->stops2[$feedInstanceId][$tripId][$stopSequence] = $sth->fetch();
    }

    // returns an array, indexed by serviceId, that contains serviceIds.
    function getServiceIds($feedIntanceId, $date) {
        $weekDay = strtolower(get_yyyymmdd_weekday($date, 'l')); // 'sunday' through 'saturday'
        $result = [];

        $sql1 = '
            select service_id
            from gtfs_calendar
            where geo_gtfs_feed_instance_id = :feedInstanceId
                and start_date <= :date
                and end_date >= :date
                and (({weekDay} = 1) or
                (sunday = 0 and monday = 0 and tuesday = 0 and wednesday = 0 and
                thursday = 0 and friday = 0 and saturday = 0))
        ';
        $sql1 = preg_replace('/\\{weekDay\\}/', $weekDay, $sql1);
        $sth1 = $this->dbh->prepare($sql1);
        $sth1->execute([ ':feedInstanceId' => $feedInstanceId, ':date' => $date ]);
        while ($serviceId = $sth1->fetchColumn()) {
            $result[$serviceId] = $serviceId;
        }

        $sql2 = '
            select service_id, exception_type
            from gtfs_calendar_dates
            where geo_gtfs_feed_instance_id = :feedInstanceId and date = :date
        ';
        $sth2 = $this->dbh->prepare($sql2);
        $sth2->execute([ ':feedInstanceId' => $feedInstanceId, ':date' => $date ]);
        while ($row = $sth1->fetch()) {
            $serviceId = $row['service_id'];
            $exceptionType = intval($row['exception_type']);
            if ($exceptionType === 1) {
                $result[$serviceId] = $serviceId;
            } else if ($exceptionType === 2) {
                unset($result[$serviceId]);
            }
        }

        if (!count($result)) {
            throw new Error("no service ids for {$this->agencyName} feed instance {$feedInstanceId} on {$date}");
        }

        return $result;
    }

    function getCurrentScheduledTrips($feedInstanceId, $date, $time, $flags = 0) {
        $serviceIds = $this->getServiceIds($feedInstanceId, $date);
        $params = [];
        $andServiceId = '';
        if (count($serviceIds)) {
            $index = 0;
            $placeholderNames = [];
            foreach ($serviceIds as $serviceId) {
                $placeholderName = ':serviceId' . $index;
                $placeholderNames[] = $placeholderName;
                $params[$placeholderName] = $serviceId;
                $index += 1;
            }
            $andServiceId = 'and gtfs_trips.service_id in ({serviceIds})';
            $andServiceId = preg_replace('/\\{serviceIds\\}/', join(', ', $placeholderNames));
        }
        $sql = '
            select
                T1.route_id,
                T1.trip_id,
                T1.direction_id,
                min(ST1.arrival_time) as start_arrival,
                min(ST1.departure_time) as start_departure,
                max(ST1.arrival_time) as end_arrival
            from
                gtfs_routes as R1
            join
                gtfs_trips as T1 on gtfs_routes.route_id = T1.route_id
            join
                gtfs_stop_times as ST1 on T1.trip_id = ST1.trip_id
            where
                gtfs_routes.geo_gtfs_feed_instance_id = :feedInstanceId
                and T1.geo_gtfs_feed_instance_id = :feedInstanceId
                and ST1.geo_gtfs_feed_instance_id = :feedInstanceId
                {andServiceId}
            group by
                T1.route_id,
                T1.trip_id,
                T1.direction_id
            having
                min(ST1.arrival_time) <= :time and
                max(ST1.arrival_time) >= :time
                order by
                T1.route_id asc,
                T1.direction_id asc,
                start_arrival asc,
                start_departure asc,
                end_arrival asc
        ';
        $sql = preg_replace('/\\{andServiceId\\}/', $andServiceId, $sql);
        $params[':feedInstanceId'] = $feedInstanceId;
        $params[':time'] = $time;
        $sth = $this->dbh->prepare($sql);
        $sth->execute($params);

        if ($flags & GROUP_BY_ROUTE_ID) {
            $rows = $sth->fetchAll();
            $groupedRows = [];
            foreach ($rows as $row) {
                $routeId = $row['route_id'];
                $groupedRows[$routeId][] = $row;
            }
            return $groupedRows;
        }

        if ($flags & INDEX_BY_TRIP_ID) {
            $rows = $sth->fetchAll();
            $rows2 = [];
            foreach ($rows as $row) {
                $tripId = $row['trip_id'];
                $rows2[$tripId] = $row;
            }
            return $rows2;
        }

        $rows = $sth->fetchAll();
        return $rows;
    }
}
