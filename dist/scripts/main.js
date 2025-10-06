"use strict";
/*global google, MarkerWithLabel, console */

// const GOOGLE_MAPS_API_KEY = "AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo";
// let GOOGLE_MAPS_API_URL = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}`;

// const REFRESH_INTERVAL    = 5000; // milliseconds
const CENTER_LAT          = 38.186;
const CENTER_LNG          = -85.676;
const DEFAULT_ZOOM        = 11;
const DEFAULT_GPS_ZOOM    = 13;
const TEXT_MARKER_SIZE    = 21; /* [A] see .text-marker rules in t.css */
const VEHICLE_DATA_URL    = "example-vehicle-positions.json";
// const TRIP_UPDATE_URL     = "trip-updates.php";
const IS_MOBILE           = /\b(ipad|iphone|android)\b/i.test(navigator.userAgent);
// const BUSFAN_MODE         = /\bbusfan\b/.test(location.search);

let map;
let mapElement;
// let transitLayer;
// let trafficLayer;
// let bikeLayer;
// let transitLayerEnabled;
// let trafficLayerEnabled;
// let bikeLayerEnabled;
const layers = {};
const layerEnabled = {};
const markers = {};

function init() {
    initMap();
    locateMap();
    fetchData();
}

function initMap() {
    mapElement = $("#map");
    map = new google.maps.Map(mapElement, {
        "center": new google.maps.LatLng(CENTER_LAT, CENTER_LNG),
        "zoom": DEFAULT_ZOOM,
        "mapTypeId" : google.maps.MapTypeId.ROADMAP,
        "mapTypeControlOptions": {
            "mapTypeIds": [
                google.maps.MapTypeId.HYBRID,
                google.maps.MapTypeId.ROADMAP,
                google.maps.MapTypeId.SATELLITE,
                google.maps.MapTypeId.TERRAIN
            ],
            "style": google.maps.MapTypeControlStyle.HORIZONTAL_BAR // DROPDOWN_MENU, HORIZONTAL_BAR, or DEFAULT
        },
        "scaleControl": true,
        "overviewMapControl": true,
        "panControl": true,
        "streetViewControl": false,
        "zoomControl": true,
    });
    $("#transit").addEventListener("click", toggleTransitLayer);
    $("#bike").addEventListener("click", toggleBikeLayer);
    $("#traffic").addEventListener("click", toggleTrafficLayer);
}

function toggleBikeLayer() {
    toggleLayer(google.maps.BicyclingLayer, "bike");
}
function toggleTransitLayer() {
    toggleLayer(google.maps.TransitLayer, "transit");
}
function toggleTrafficLayer() {
    toggleLayer(google.maps.TrafficLayer, "traffic");
}
function toggleLayer(layerClass, layerName) {
    if (!layers[layerName]) {
        layers[layerName] = new layerClass();
        layers[layerName].setMap(map);
        layerEnabled[layerName] = true;
    } else {
        layerEnabled[layerName] = !layerEnabled[layerName];
        if (layerEnabled[layerName]) {
            layers[layerName].setMap(map);
        } else {
            layers[layerName].setMap(null);
        }
    }
    if (layerEnabled[layerName]) {
        $(`#${layerName}`).classList.add("control--enabled");
    } else {
        $(`#${layerName}`).classList.remove("control--enabled");
    }
}

function locateMap() {
    if (navigator?.geolocation != null) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            map.setCenter(new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude));
            map.setZoom(DEFAULT_GPS_ZOOM);
        });
    }
}

function fetchData() {
    fetch(VEHICLE_DATA_URL).then(resp => resp.json())
                           .catch((error) => {
                               console.warn(error);
                               setTimeout(fetchData, 5000);
                           })
                           .then(data => {
                               loadData(data);
                               setTimeout(fetchData, 5000);
                           })
                           .catch(error => {
                               console.warn(error);
                           });
}

function loadData(data) {
    if (data == null) {
        return;
    }
    if ("Entities" in data) {
        for (const entity of data.Entities) {
            const {
                "TripUpdate": tripUpdate,
                "Vehicle": {
                    "Trip": {
                        "TripId": tripId,
                        "RouteId": routeId,
                        "schedule_relationship": scheduleRelationship,
                    },
                    "Vehicle": {
                        "Id": vehicleId,
                        "Label": vehicleLabel,
                    },
                    "Position": {
                        "Latitude": lat,
                        "Longitude": lng,
                        "Bearing": bearing,
                        "Speed": speed,
                    },
                    "CurrentStopSequence": currentStopSequence,
                    "StopId": stopId,
                    "CurrentStatus": currentStatus,
                    "Timestamp": timestamp,
                    "congestion_level": congestionLevel,
                    "occupancy_status": occupancyStatus,
                },
                "Alert": alert,
            } = entity;
            if (!(vehicleId in markers)) {
                markers[vehicleId] = new MarkerWithLabel({
                    clickable: true,
                    flat: true,
                    map: map,
                    optimized: false,
                    visible: true,
                });
            }
            markers[vehicleId].setOptions({
                position: new google.maps.LatLng(lat, lng),
                labelContent: routeId,
                labelClass: `text-marker text-marker--route-${routeId}`,
                labelAnchor: new google.maps.Point(TEXT_MARKER_SIZE / 2, TEXT_MARKER_SIZE / 2),
                title: `bus ${vehicleLabel} on route ${routeId}`,
                icon: { url: "about:blank" },
            });
        }
    }
}

function $(selector) {
    return document.querySelector(selector);
}

function $$(selector) {
    return [...document.querySelectorAll(selector)];
}

if (document.readyState === "complete") {
    init();
} else {
    window.addEventListener("load", init);
}
