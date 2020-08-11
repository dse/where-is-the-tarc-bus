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
            ($vehicle >= 1921 && $vehicle <= 1928));
}
function is_new_vehicle($vehicle) {
    $vehicle = intval($vehicle);
    return (
        ($vehicle >= 2720 && $vehicle <= 2726)
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
