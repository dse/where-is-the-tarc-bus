/*jslint browser: true, vars: true, sloppy: true, regexp: true, white: true */
/*global jQuery, google, initializeExtraControls, getCookies */
/*global setCookies */
//-----------------------------------------------------------------------------
// Lines above are for jslint, the JavaScript verifier.  http://www.jslint.com/
//-----------------------------------------------------------------------------

var GOOGLE_MAPS_USE_SENSOR = true;
var GOOGLE_MAPS_API_KEY = "AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo";

// it's my mirror, which only pulls data every 30 seconds, I do what I
// want.  (I'm this program.)
var REFRESH_INTERVAL = 5;	// milliseconds

var CENTER_LAT =  38.186;
var CENTER_LNG = -85.676;
var DEFAULT_ZOOM     = 11;
var DEFAULT_GPS_ZOOM = 13;

var GOOGLE_MAPS_API_URL = "https://maps.googleapis.com/maps/api/js?key={API_KEY}&sensor={SENSOR}";
GOOGLE_MAPS_API_URL = GOOGLE_MAPS_API_URL.replace(/\{API_KEY\}/, encodeURIComponent(GOOGLE_MAPS_API_KEY));
GOOGLE_MAPS_API_URL = GOOGLE_MAPS_API_URL.replace(/\{SENSOR\}/, encodeURIComponent(String(GOOGLE_MAPS_USE_SENSOR)));

Object.forEach = function(obj, func, thisp) {
    var key, value;
    for (key in obj) {
		if (obj.hasOwnProperty(key)) {
			func.apply(thisp, [key, obj[key]]);
		}
    }
};

if (!Array.prototype.filter) {
    Array.prototype.filter = function(fun /*, thisArg */) {
		"use strict";
		if (this === void 0 || this === null) {
			throw new TypeError();
		}
		var t = Object(this);
		var len = t.length >>> 0;
		if (typeof fun !== "function") {
			throw new TypeError();
		}
		var res = [];
		var thisArg = arguments.length >= 2 ? arguments[1] : void 0;
		for (var i = 0; i < len; i++) {
			if (i in t) {
				var val = t[i];
				// NOTE: Technically this should Object.defineProperty at
				//       the next index, as push can be affected by
				//       properties on Object.prototype and Array.prototype.
				//       But that method's new, and collisions should be
				//       rare, so use the more-compatible alternative.
				if (fun.call(thisArg, val, i, t)) {
					res.push(val);
				}
			}
		}
		return res;
    };
}

function markerClickHandler(tracking) {
    return function() {
		var infoWindow;
		console.log(tracking);
		infoWindow = tracking.infoWindow;
		if (infoWindow) {
			infoWindow.setOptions({
				content: tracking.title
			});
		} else {
			infoWindow = tracking.infoWindow = new google.maps.InfoWindow({
				content: tracking.title
			});
			infoWindow.open(tracking.marker);
		}
    };
}

jQuery(function($) {
    var mainMap, interval;
    var vehicle_tracking = {};
    var trafficLayer, transitLayer, bicyclingLayer;

    function setEvents() {
		google.maps.event.addListener(mainMap, "center_changed", function() {
			var center  = mainMap.getCenter();
			var zoom    = mainMap.getZoom();
			setCookie("lat",  center.lat());
			setCookie("lng",  center.lng());
			setCookie("zoom", zoom);
		});
    }

    function initializeAt(lat, lng, zoom) {
		var mapCanvas, mapOptions;
		mapOptions = {
			"center"    : new google.maps.LatLng(lat, lng),
			"zoom"      : zoom,
			"mapTypeId" : google.maps.MapTypeId.ROADMAP,
			"mapTypeControlOptions": {
				"mapTypeIds": [ google.maps.MapTypeId.HYBRID,
								google.maps.MapTypeId.ROADMAP,
								google.maps.MapTypeId.SATELLITE,
								google.maps.MapTypeId.TERRAIN ]
				//"style": google.maps.MapTypeControlStyle.HORIZONTAL_BAR // DROPDOWN_MENU, HORIZONTAL_BAR, or DEFAULT
			}
		};

		mapCanvas = $(".mapContainer").get(0);
		if (!mapCanvas) {
			return;
		}

		mainMap = new google.maps.Map(mapCanvas, mapOptions);
		mainMap.setZoom(zoom);
		setEvents();
		initializeExtraControls();

		loadJSON();
    }
    
    function initializeFromCookiesOrDefaults() {
		var lat, lng, cookies, zoom;
		cookies = getCookies();
		if (cookies.lat !== undefined && cookies.lng !== undefined) {
			lat = Number(cookies.lat);
			lng = Number(cookies.lng);
		} else {
			lat = CENTER_LAT;
			lng = CENTER_LNG;
		}
		if (cookies.zoom !== undefined) {
			zoom = Number(cookies.zoom);
		} else {
			zoom = DEFAULT_ZOOM;
		}
		initializeAt(lat, lng, zoom);
    }

    function initialize() {
		if (navigator.geolocation !== undefined) {
			var onsuccess = function(position) {
				initializeAt(position.coords.latitude,
							 position.coords.longitude,
							 DEFAULT_GPS_ZOOM);
			};
			navigator.geolocation.getCurrentPosition(onsuccess,
													 /* onerror */ initializeFromCookiesOrDefaults);
		} else {
			initializeFromCookiesOrDefaults();
		}
    }

    function loadData(data) {
		var vp, tu, vp_ts, tu_ts, record, ts, vehicle_label, tracking;
		if (!data) {
			console.log("!data");
			return;
		}
		if (data.entity) {
			vp = {
				entity: data.entity.filter(function(x) { return x.vehicle; }),
				header: data.header
			};
			tu = {
				entity: data.entity.filter(function(x) { return x.trip_update; }),
				header: data.header
			};
		} else {
			if (!(vp = data.vehicle_positions)) {
				console.log("!vp");
				return;
			}
			if (!(tu = data.trip_updates)) {
				console.log("!tu");
				return;
			}
		}
		vp_ts = vp.header && vp.header.timestamp;
		tu_ts = tu.header && tu.header.timestamp;
		Object.forEach(vehicle_tracking, function(vehicle_label, tracking) {
			tracking.vehicle_is_uptodate = false;
			tracking.trip_is_uptodate = false;
		});
		vp.entity.forEach(function(entity) {
			if (!entity) {
				return;
			}
			if (!(record = entity.vehicle)) {
				return;
			}
			if (!(vehicle_label = record.vehicle && record.vehicle.label)) {
				return;
			}
			tracking = vehicle_tracking[vehicle_label];
			if (!tracking) {
				tracking = vehicle_tracking[vehicle_label] = {};
			}
			tracking.vehicle_is_uptodate = true;
			tracking.vehicle = record;
		});
		tu.entity.forEach(function(entity) {
			if (!entity) {
				return;
			}
			if (!(record = entity.trip_update)) {
				return;
			}
			if (!(vehicle_label = record.vehicle && record.vehicle.label)) {
				return;
			}
			if (record.useful) {
				tracking = vehicle_tracking[vehicle_label];
				if (!tracking) {
					tracking = vehicle_tracking[vehicle_label] = {};
				}
				tracking.trip_is_uptodate = true;
				tracking.trip_update = record;
			} else {
				delete vehicle_tracking[vehicle_label];
				// not useful
			}
		});

		var delay, minutesLate, delayMessage, stop_time_updates, isReallyLate, route_id;
		var isRedRoute;
		var isEtranBus;
		var isExpress;
		var colorScheme;
		var imageURL;
		var trip_headsign;
		var lat, lng;
		var marker, title;
		var onclick;

		Object.forEach(vehicle_tracking, function(vehicle_label, tracking) {
			if (!tracking) { console.log(vehicle_label + " A"); return; }
			if (!(vehicle = tracking.vehicle)) { console.log(vehicle_label + " B"); return; }
			if (!(trip_update = tracking.trip_update)) { console.log(vehicle_label + " C"); return; }
			if (!tracking.vehicle_is_uptodate || !tracking.trip_is_uptodate) { console.log(vehicle_label + " D"); return; }
			if (!(stop_time_updates = trip_update.stop_time_update) || !stop_time_updates.length) { console.log(vehicle_label + " E"); return; }
			lat = vehicle.position && vehicle.position.latitude;
			lng = vehicle.position && vehicle.position.longitude;
			route_id = trip_update.trip && trip_update.trip.route_id;
			if (route_id === undefined || route_id === null || route_id === "" || route_id === "UN") { console.log(vehicle_label + " F"); return; }
			console.log(tracking, vehicle_label);
			trip_headsign = trip_update.trip_headsign;
			delay = stop_time_updates[0].delay;
			minutesLate  = Math.round(delay / 60);
			if (minutesLate >= 5)     { delayMessage = minutesLate + " minutes late"; }
			else if (minutesLate > 1) { delayMessage = "only " + minutesLate + " minutes late"; }
			else if (minutesLate)     { delayMessage = "only 1 minute late"; }
			else                      { delayMessage = "on time"; }
			isRedRoute = (route_id === "94");
			isEtranBus = (vehicle_label.length === 4 && vehicle_label >= "1350" && vehicle_label <= "1370");
			isExpress  = trip_headsign && /\bexpress\b/i.test(trip_headsign);
			if (isEtranBus) {
				colorScheme = isExpress ? "blue-on-white" : "white-on-blue";
			} else if (isRedRoute) {
				colorScheme = "white-on-black";
			} else {
				colorScheme = isExpress ? "black-on-yellow" : "white-on-black";
			}
			imageURL = "http://webonastick.com/route-icons/target/route-icons/png/{colorScheme}/{route_id}.png";
			imageURL = imageURL.replace(/{colorScheme}/, colorScheme);
			imageURL = imageURL.replace(/{route_id}/, route_id);
			title  = tracking.title = route_id + " " + trip_headsign;
			marker = tracking.marker;

			if (!marker) {
				marker = tracking.marker = new google.maps.Marker({
					clickable: true,
					flat: true,
					icon: {
						url: imageURL,
						size: new google.maps.Size(15, 15),
						anchor: new google.maps.Point(7, 7)
					},
					map: mainMap,
					optimized: false,
					position: new google.maps.LatLng(lat, lng),
					title: title,
					visible: true
				});
				google.maps.event.addListener(marker, "click", markerClickHandler(tracking));
			} else {
				marker.setOptions({
					position: new google.maps.LatLng(lat, lng),
					title: title
				});
			}

		});
    }
    function loadJSON() {
		$.ajax({
			url: "/t/realtime_data.mhtml?format=json",
			dataType: "json",
			success: function(data) {
				console.log("SUCCESS");
				loadData(data);
				setTimeout(loadJSON, 30000);
			},
			error: function() {
				console.log("FAILURE");
				setTimeout(loadJSON, 30000);
			}
		});
    }
    function initializeExtraControls() {
		$(".extraControlsForm").each(function() {
			$(this).find(":checkbox[name='showTransitLayer']").change(function() {
				var checked = $(this).is(":checked");
				if (checked) {
					if (!transitLayer) {
						transitLayer = new google.maps.TransitLayer();
					}
					transitLayer.setMap(mainMap);
				} else {
					if (transitLayer) {
						transitLayer.setMap(null);
					}
				}
			}).trigger("change");
			$(this).find(":checkbox[name='showTrafficLayer']").change(function() {
				var checked = $(this).is(":checked");
				if (checked) {
					if (!trafficLayer) {
						trafficLayer = new google.maps.TrafficLayer();
					}
					trafficLayer.setMap(mainMap);
				} else {
					if (trafficLayer) {
						trafficLayer.setMap(null);
					}
				}
			}).trigger("change");
			$(this).find(":checkbox[name='showBicyclingLayer']").change(function() {
				var checked = $(this).is(":checked");
				if (checked) {
					if (!bicyclingLayer) {
						bicyclingLayer = new google.maps.BicyclingLayer();
					}
					bicyclingLayer.setMap(mainMap);
				} else {
					if (bicyclingLayer) {
						bicyclingLayer.setMap(null);
					}
				}
			}).trigger("change");
		});
    }

    initialize();
});

jQuery(function($) {
    $(".closeLink").click(function() {
		$(this).closest(".box").hide();
    });
    if (!(/\b(iphone|ipad|android)\b/i.test(navigator.userAgent))) {
		$(".desktopOnly").show();
    }
});

// http://www.yourmapper.com/demo/gtfsrealtime/index.js also might have some goodies.

