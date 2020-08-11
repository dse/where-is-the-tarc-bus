/*jslint browser: true, sloppy: true */
//-----------------------------------------------------------------------------
// Lines above are for jslint, the JavaScript verifier.  http://www.jslint.com/
//-----------------------------------------------------------------------------

function WhereIsMyBus() {
    this.init();
}

WhereIsMyBus.GOOGLE_MAPS_API_KEY = "AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo";
WhereIsMyBus.REFRESH_INTERVAL    = 5;	// milliseconds
WhereIsMyBus.CENTER_LAT          = 38.186;
WhereIsMyBus.CENTER_LNG          = -85.676;
WhereIsMyBus.DEFAULT_ZOOM        = 11;
WhereIsMyBus.DEFAULT_GPS_ZOOM    = 13;
WhereIsMyBus.GOOGLE_MAPS_API_URL = "https://maps.googleapis.com/maps/api/js?key={API_KEY}";
WhereIsMyBus.TEXT_MARKER_SIZE    = 15;           /* [A] see t.css */
WhereIsMyBus.GOOGLE_MAPS_API_URL = WhereIsMyBus.GOOGLE_MAPS_API_URL.replace(/\{API_KEY\}/, encodeURIComponent(WhereIsMyBus.GOOGLE_MAPS_API_KEY));
WhereIsMyBus.VEHICLE_DATA_URL    = '/t/vehicle-positions.php';
WhereIsMyBus.BUSFAN_MODE         = /\bbusfan\b/.test(location.search);
WhereIsMyBus.IS_MOBILE           = /\b(ipad|iphone|android)\b/i.test(navigator.userAgent);
WhereIsMyBus.TRIP_UPDATE_URL     = '/t/trip-update.php?tripid={TRIP_ID}';

WhereIsMyBus.TEXT_MARKER_MODE = true;
if (/\bimagemarker\b/.test(location.search)) {
    WhereIsMyBus.TEXT_MARKER_MODE = false;
}

Object.assign(WhereIsMyBus.prototype, {

    init: function () {
        this.initLocationZoom = true;
        this.cookies = getCookies();
        this.initMap();
        this.setEvents();
        this.fetchData();
        this.initLayerBindings();
    },

    mapOptions: {
        center: new google.maps.LatLng(WhereIsMyBus.CENTER_LAT, WhereIsMyBus.CENTER_LNG),
        zoom: WhereIsMyBus.DEFAULT_ZOOM,
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
        "overviewMapControl": !WhereIsMyBus.IS_MOBILE,
        "panControl": !WhereIsMyBus.IS_MOBILE,
        "streetViewControl": false,
        "zoomControl": !WhereIsMyBus.IS_MOBILE
    },

    showMap: function () {
        this.mapContainer = $(".mapContainer").get(0);
        if (!this.mapContainer) {
            return;
        }
        this.mainMap = new google.maps.Map(this.mapContainer, this.mapOptions);
    },

    initMap: function () {
        this.showMap();
        if (navigator.geolocation !== undefined) {
            var onsuccess = this.setLocationFromGPS.bind(this);
            var onerror   = this.setLocationFromCookiesOrDefaults.bind(this);
            navigator.geolocation.getCurrentPosition(onsuccess, onerror);
        } else {
            this.initializeFromCookiesOrDefaults();
        }
    },

    setLocationFromGPS: function (position) {
        if (this.initLocationZoom) {
            this.mainMap.setCenter(new google.maps.LatLng(position.coords.latitude,
                                                          position.coords.longitude));
            if (this.cookies.zoom !== undefined) {
                this.mainMap.setZoom(Number(this.cookies.zoom));
            } else {
                this.mainMap.setZoom(WhereIsMyBus.DEFAULT_GPS_ZOOM);
            }
        }
    },

    setLocationFromCookiesOrDefaults: function () {
        if (this.initLocationZoom) {
            if (this.cookies.lat !== undefined && this.cookies.lng !== undefined) {
                this.mainMap.setCenter(new google.maps.LatLng(Number(this.cookies.lat),
                                                              Number(this.cookies.lng)));
            }
            if (this.cookies.zoom !== undefined) {
                this.mainMap.setZoom(Number(this.cookies.zoom));
            }
        }
    },

    setEvents: function () {
        google.maps.event.addListener(this.mainMap, "center_changed", this.updateCookies.bind(this));
        google.maps.event.addListener(this.mainMap, "zoom_changed", this.updateCookies.bind(this));
    },

    updateCookies: function () {
        this.initLocationZoom = false;
        var center  = this.mainMap.getCenter();
        var zoom    = this.mainMap.getZoom();
        setCookie("lat",  center.lat());
        setCookie("lng",  center.lng());
        setCookie("zoom", zoom);
    },

    fetchData: function () {
        setTimeout(this.fetchData.bind(this), 5000);
        $.ajax({
            url: WhereIsMyBus.VEHICLE_DATA_URL,
            dataType: "json",
            success: this.loadData.bind(this)
        });
    },

    loadData: function (data) {
        if (data.vehicle) {
            data.vehicle.forEach(this.placeVehicle.bind(this));
        } else if (data.entity) {
            data.entity.forEach(this.placeVehicle.bind(this));
        }
    },

    markers: {},
    vehicles: {},

    placeVehicle: function (entity) {
        var vehicleId = entity.id;
        var latitude = entity.vehicle.position.latitude;
        var longitude = entity.vehicle.position.longitude;
        var routeId = entity.vehicle.trip.route_id;
        var timestamp = entity.vehicle.timestamp;
        var tripId = entity.vehicle.trip.trip_id;

        var routeDisplayed = routeId.replace(/^0+(?=[^0])/, '');
        var vehicleIdDisplayed = vehicleId.replace(/^0+(?=[^0])/, '');

        var vehicle = {
            vehicleId: vehicleId,
            latitude: latitude,
            longitude: longitude,
            routeId: routeId,
            tripId: tripId,
            routeDisplayed: routeDisplayed,
            vehicleIdDisplayed: vehicleIdDisplayed,
            timestamp: timestamp
        };

        this.vehicles[vehicleId] = vehicle;

        if (!this.markers[vehicleId]) {
            if (WhereIsMyBus.TEXT_MARKER_MODE) {
                this.markers[vehicleId] = new MarkerWithLabel({
                    clickable: true,
                    flat: true,
                    map: this.mainMap,
                    optimized: false, // eh?
                    visible: true
                });
            } else {
                this.markers[vehicleId] = new google.maps.Marker({
                    clickable: true,
                    flat: true,
                    map: this.mainMap,
                    optimized: false, // eh?
                    visible: true
                });
            }
            google.maps.event.addListener(this.markers[vehicleId],
                                          "click", function () {
                                              this.markerClick(this.vehicles[vehicleId]);
                                          }.bind(this));
        }

        if (WhereIsMyBus.TEXT_MARKER_MODE) {
            this.markers[vehicleId].setOptions({
                position: new google.maps.LatLng(latitude, longitude),
                labelContent: routeDisplayed,
                labelClass: this.getTextMarkerClass(vehicle),
                labelAnchor: new google.maps.Point(WhereIsMyBus.TEXT_MARKER_SIZE / 2, WhereIsMyBus.TEXT_MARKER_SIZE / 2),
                title: this.markerTitle(vehicle),
                icon: {
                    url: "about:blank"
                }
            });
        } else {
            var image = this.getImageURLAndSize(vehicle);
            if (image.route.length > 2) {
                console.log(image);
            }
            this.markers[vehicleId].setOptions({
                position: new google.maps.LatLng(latitude, longitude),
                icon: {
                    url:    image.url,
                    size:   new google.maps.Size(image.width, image.height),
                    anchor: new google.maps.Point(image.cx, image.cy)
                },
                title: this.markerTitle(vehicle)
            });
        }

        if (this.infoWindowLabel === vehicleId && this.infoWindow) {
            this.infoWindow.setOptions({
                content: this.infoWindowContent(vehicle)
            });
        }
    },

    infoWindowLabel: null,

    markerClick: function (vehicle) {
        if (this.infoWindow) {
            this.infoWindow.close();
            this.infoWindowLabel = null;
        }
        this.infoWindow = new google.maps.InfoWindow({
            content: this.infoWindowContent(vehicle)
        });
        var marker = this.markers[vehicle.vehicleId];
        this.infoWindow.open(this.mainMap, marker);
        this.infoWindowLabel = vehicle.vehicleId;
    },

    markerTitle: function (vehicle) {
        var content = '';
        content += vehicle.vehicleIdDisplayed;
        content += ' on route ';
        content += vehicle.routeDisplayed;
        return content;
    },

    infoWindowContent: function (vehicle) {
        var content = '';
        content += '<p>';
        content += vehicle.vehicleIdDisplayed;
        content += ' on route ';
        content += vehicle.routeDisplayed;
        content += '</p>';
        return content;
    },

    getImageURLAndSize: function (vehicle) {
        var imageName, imageURL, colorScheme, width, height, cx, cy;

        imageName = String(vehicle.routeId).replace(/x$/i, "");

        if (imageName.length > 2) {
            width = 24;
            height = 16;
            cx = 12;
            cy = 8;
        } else {
            width = 16;
            height = 16;
            cx = 8;
            cy = 8;
        }

        colorScheme = this.getColorScheme(vehicle);

        imageURL = "https://webonastick.com/route-icons/target/route-icons/png/{colorScheme}/{imageName}.png";
        imageURL = imageURL.replace(/{colorScheme}/, colorScheme);
        imageURL = imageURL.replace(/{imageName}/, imageName);
        return {
            url: imageURL,
            width: width,
            height: height,
            cx: cx,
            cy: cy,
            route: imageName
        };
    },

    getColorScheme: function (vehicle) {
        var express = /\bexpress\b/i.test(vehicle.trip_headsign) || /x$/i.test(vehicle.routeId);
        if (vehicle.routeId == "94") {
            return "white-on-red";
        }
        return express ? "black-on-yellow" : "white-on-black";
    },

    showTransitLayer: function (bool) {
        if (bool === undefined || bool === null) { bool = true; }
        if (bool) {
            if (!this.transitLayer) {
                this.transitLayer = new google.maps.TransitLayer();
            }
            this.transitLayer.setMap(this.mainMap);
        } else {
            if (this.transitLayer) {
                this.transitLayer.setMap(null);
            }
        }
    },

    showTrafficLayer: function (bool) {
        if (bool === undefined || bool === null) { bool = true; }
        if (bool) {
            if (!this.trafficLayer) {
                this.trafficLayer = new google.maps.TrafficLayer();
            }
            this.trafficLayer.setMap(this.mainMap);
        } else {
            if (this.trafficLayer) {
                this.trafficLayer.setMap(null);
            }
        }
    },

    showBicyclingLayer: function (bool) {
        if (bool === undefined || bool === null) { bool = true; }
        if (bool) {
            if (!this.bicyclingLayer) {
                this.bicyclingLayer = new google.maps.BicyclingLayer();
            }
            this.bicyclingLayer.setMap(this.mainMap);
        } else {
            if (this.bicyclingLayer) {
                this.bicyclingLayer.setMap(null);
            }
        }
    },

    initLayerBindings: function () {
        $(":checkbox[name='showTransitLayer']").change(function (event) {
            this.showTransitLayer(event.target.checked);
        }.bind(this)).trigger("change");
        $(":checkbox[name='showTrafficLayer']").change(function (event) {
            this.showTrafficLayer(event.target.checked);
        }.bind(this)).trigger("change");
        $(":checkbox[name='showBicyclingLayer']").change(function (event) {
            this.showBicyclingLayer(event.target.checked);
        }.bind(this)).trigger("change");
    },

    getTextMarkerClass: function (vehicle) {
        if (this.BUSFAN_MODE) {
            return this.getTextMarkerClassForBusFans(vehicle);
        }
        if (/x$/i.test(vehicle.routeId)) {
            return 'textMarker textMarker--express';
        }
        if (vehicle.routeId === '10') {
            return 'textMarker textMarker--rapidTarc';
        }
        return 'textMarker';
    },

    getTextMarkerClassForBusFans: function (vehicle) {
        var vehicleNumber = Number(vehicle.vehicleIdDisplayed);
        var className = 'textMarker';
        if (!isNaN(vehicleNumber)) {
            if      (vehicleNumber >= 2001 && vehicleNumber <= 2012) { /* do nothing */ }
            else if (vehicleNumber >= 2101 && vehicleNumber <= 2111) { /* do nothing */ }
            else if (vehicleNumber >= 2250 && vehicleNumber <= 2265) { /* do nothing */ }
            else if (vehicleNumber >= 2301 && vehicleNumber <= 2320) { /* do nothing */ }
            else if (vehicleNumber >= 2401 && vehicleNumber <= 2405) { /* do nothing */ }
            else if (vehicleNumber >= 2501 && vehicleNumber <= 2516) { /* do nothing */ }
            else if (vehicleNumber >= 2701 && vehicleNumber <= 2704) { /* do nothing */ }
            else if (vehicleNumber >= 2801 && vehicleNumber <= 2806) { /* do nothing */ }
            else if (vehicleNumber >= 2901 && vehicleNumber <= 2903) { /* do nothing */ }
            else if (vehicleNumber >= 2910 && vehicleNumber <= 2926) { /* do nothing */ }
            else if (vehicleNumber >= 1001 && vehicleNumber <= 1009) { /* do nothing */ }
            else if (vehicleNumber >= 1301 && vehicleNumber <= 1316) { /* do nothing */ }
            else if (vehicleNumber >= 1320 && vehicleNumber <= 1330) { /* do nothing */ }
            else if (vehicleNumber >= 1350 && vehicleNumber <= 1370) { /* do nothing */ }
            else if (vehicleNumber >= 1401 && vehicleNumber <= 1412) { /* do nothing */ }
            else if (vehicleNumber >= 1601 && vehicleNumber <= 1625) { /* do nothing */ }
            else if (vehicleNumber >= 1630 && vehicleNumber <= 1630) { /* do nothing */ }
            else if (vehicleNumber >= 1701 && vehicleNumber <= 1702) { /* do nothing */ }
            else if (vehicleNumber >= 1901 && vehicleNumber <= 1910) { /* do nothing */ }
            else if (vehicleNumber >= 1920 && vehicleNumber <= 1928) { className += ' textMarker--rapidTarc'; }
            else {
                className += ' textMarker--novelForBusFans';
            }
        }
        return className;
    }

});

jQuery(function ($) {
    var t = new WhereIsMyBus();
});
