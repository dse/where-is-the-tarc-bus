<?php

function chomp($string) {
    return preg_replace('/\\R\\z/', '', $string);
}

function indent_string($string, $prefix, $prefix2 = null) {
    if ($prefix2 === null) {
        $string = preg_replace('/^/m', $prefix, $string);
        $string = chomp($string) . "\n";
        return $string;
    }
    if ($prefix2 === '') {
        $prefix2 = str_repeat(' ', strlen($prefix));
    }
    $string = $prefix . $string;
    $string = preg_replace('/.^/ms', '$0' . $prefix2, $string);
    $string = chomp($string) . "\n";
    return $string;
}

function is_rapid_tarc_vehicle($vehicle) {
    $vehicle = intval($vehicle);
    return ($vehicle === 1370 || $vehicle >= 1920 && $vehicle <= 1928);
}

function is_unknown_vehicle($vehicle) {
    $vehicle = intval($vehicle);
    return !is_known_vehicle($vehicle) && !is_new_vehicle($vehicle);
}

function is_known_vehicle($vehicle) {
    $vehicle = intval($vehicle);
    return (($vehicle >= 1 && $vehicle <= 10) || // 35' Proterra
            ($vehicle >= 12 && $vehicle <= 17) || // 40' Proterra
            ($vehicle >= 2001 && $vehicle <= 2012) ||
            ($vehicle >= 2050 && $vehicle <= 2056) || // Gillig shorties
            ($vehicle >= 2101 && $vehicle <= 2111) ||
            ($vehicle >= 2250 && $vehicle <= 2266) || // Gillig shorties
            ($vehicle >= 2301 && $vehicle <= 2320) ||
            ($vehicle >= 2401 && $vehicle <= 2405) || // Hybrids
            ($vehicle >= 2501 && $vehicle <= 2516) ||
            ($vehicle >= 2701 && $vehicle <= 2704) || // Hybrids
            ($vehicle >= 2801 && $vehicle <= 2806) ||
            ($vehicle >= 2901 && $vehicle <= 2903) || // Hybrids
            ($vehicle >= 2910 && $vehicle <= 2926) ||
            ($vehicle >= 1001 && $vehicle <= 1009) || // Hybrids
            ($vehicle >= 1301 && $vehicle <= 1316) ||
            ($vehicle >= 1320 && $vehicle <= 1330) || // Hybrids
            ($vehicle >= 1350 && $vehicle <= 1370) || // BRT (1370 in RAPID TARC colors)
            ($vehicle >= 1401 && $vehicle <= 1412) ||
            ($vehicle >= 1601 && $vehicle <= 1625) ||
            ($vehicle === 1630) || // Hybrid
            ($vehicle >= 1701 && $vehicle <= 1702) || // 35-footers
            ($vehicle >= 1901 && $vehicle <= 1910) ||
            ($vehicle >= 1920 && $vehicle <= 1928));
}

$fakeMapping = [];

function add_fake_mapping($realRanges, $fakeRanges) {
    global $fakeMapping;
    $realBuses = [];
    $fakeBuses = [];
    foreach ($realRanges as $realRange) {
        $low = $realRange[0];
        $high = count($realRange) >= 2 ? $realRange[1] : $realRange[0];
        for ($i = $low; $i <= $high; $i += 1) {
            $realBuses[] = $i;
        }
    }
    foreach ($fakeRanges as $fakeRange) {
        $low = $fakeRange[0];
        $high = count($fakeRange) >= 2 ? $fakeRange[1] : $fakeRange[0];
        for ($i = $low; $i <= $high; $i += 1) {
            $fakeBuses[] = $i;
        }
    }
    while (count($realBuses) && count($fakeBuses)) {
        $real = array_pop($realBuses);
        $fake = array_pop($fakeBuses);
        $fakeMapping[$real] = $fake;
    }
}

// BEGIN MAJOR SHITS AND GIGGLES SHIT

add_fake_mapping([[1350, 1354],
                  [2001, 2012],
                  [2101, 2111],
                  [2301, 2320],
                  [2401, 2405],
                  [1355, 1359],
                  [2501, 2516],
                  [2701, 2704],
                  [2801, 2806],
                  [2901, 2903],
                  [2910, 2926],
                  [1360, 1364],
                  [1001, 1009],
                  [1301, 1316],
                  [1320, 1330],
                  [1365, 1369],
                  [1401, 1412],
                  [1601, 1625],
                  [1630, 1630],
                  [12, 17],
                  [1901, 1910],
                  [1701, 1702],
                  [2720, 2726]],
                 [[300, 336],
                  [350, 365],
                  [500, 556],
                  [600, 651],
                  [700, 761],
                  [800, 837]]);

add_fake_mapping([[1370, 1370], [1920, 1928]],
                 [[400, 402], [404, 406], [408, 414]]);
add_fake_mapping([[2250, 2266], [2930, 2932]],
                 [[1, 21], [100, 118]]);

function fake_bus($vehicle) {
    global $fakeMapping;
    $vehicle = intval($vehicle);
    if (array_key_exists($vehicle, $fakeMapping)) {
        return $fakeMapping[$vehicle];
    }
    return $vehicle;
}

// END MAJOR SHITS AND GIGGLES SHIT

function is_new_vehicle($vehicle) {
    $vehicle = intval($vehicle);
    return (
        ($vehicle >= 2720 && $vehicle <= 2726) ||
        ($vehicle >= 2930 && $vehicle <= 2932)
    );
}
function coalesce() {
    foreach (func_get_args() as $arg) {
        if (isset($arg) && $arg !== null) {
            return $arg;
        }
    }
    return null;
}
function get_yyyymmdd_timestamp($yyyymmdd, $hhmmss = '12:00:00') {
    if (!preg_match('/^(\d\d\d\d)-?(\d\d)-?(\d\d)$/', $yyyymmdd, $matches)) {
        throw new Exception("invalid date: $yyyymmdd");
    }
    $yyyy = $matches[1];
    $mm = $matches[2];
    $dd = $matches[3];
    $year = intval($yyyy);
    $month = intval($mm);
    $date = intval($dd);
    if (!preg_match('/^ ?(\d\d?):?(\d\d):?(\d\d)$/', $hhmmss, $matches)) {
        throw new Exception("invalid time: $hhmmss");
    }
    $hh = $matches[1];
    $mm = $matches[2];
    $ss = $matches[3];
    $hours = intval($hh);
    $minutes = intval($mm);
    $seconds = intval($ss);
    $time_t = mktime($hours, $minutes, $seconds, $month, $date, $year);
    return $time_t;
}
function get_yyyymmdd_weekday($yyyymmdd, $format = null) {
    $time_t = get_yyyymmdd_timestamp($yyyymmdd, '12:00:00');
    $localtime = localtime($time_t);
    if ($format === null) {
        $wday = $localtime[6];
        return $wday;
    }
    return date($format, $time_t);
}
function htmlnumber($string) {
    return preg_replace('/-/', '&minus;', htmlentities($string));
}
