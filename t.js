/*jslint browser: true, sloppy: true */
//-----------------------------------------------------------------------------
// Lines above are for jslint, the JavaScript verifier.  http://www.jslint.com/
//-----------------------------------------------------------------------------

var t;
jQuery(function($) {
	t = new WhereIsMyBus();
});

var GOOGLE_MAPS_USE_SENSOR = true;
var GOOGLE_MAPS_API_KEY = "AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo";
var REFRESH_INTERVAL = 5;	// milliseconds
var CENTER_LAT =  38.186;
var CENTER_LNG = -85.676;
var DEFAULT_ZOOM     = 11;
var DEFAULT_GPS_ZOOM = 13;
var GOOGLE_MAPS_API_URL = "https://maps.googleapis.com/maps/api/js?key={API_KEY}&sensor={SENSOR}";
GOOGLE_MAPS_API_URL = GOOGLE_MAPS_API_URL.replace(/\{API_KEY\}/, encodeURIComponent(GOOGLE_MAPS_API_KEY));
GOOGLE_MAPS_API_URL = GOOGLE_MAPS_API_URL.replace(/\{SENSOR\}/, encodeURIComponent(String(GOOGLE_MAPS_USE_SENSOR)));

function WhereIsMyBus() {
	this.init();
}

if (!Object.extend) {
	Object.extend = function(destination, source) {
		for (var property in source) {
			destination[property] = source[property];
		}
		return destination;
	}
}

var IS_MOBILE = /\b(ipad|iphone|android)\b/i.test(navigator.userAgent);

Object.extend(WhereIsMyBus.prototype, {
	init: function() {
		this.initLocationZoom = true;
		this.cookies = getCookies();
		this.initMap();
		this.setEvents();
		this.fetchData();
		this.initLayerBindings();
	},
	mapOptions: {
		center: new google.maps.LatLng(CENTER_LAT, CENTER_LNG),
		zoom: DEFAULT_ZOOM,
		"mapTypeId" : google.maps.MapTypeId.ROADMAP,
		"mapTypeControlOptions": {
			"mapTypeIds": [ google.maps.MapTypeId.HYBRID,
							google.maps.MapTypeId.ROADMAP,
							google.maps.MapTypeId.SATELLITE,
							google.maps.MapTypeId.TERRAIN ],
			"style": google.maps.MapTypeControlStyle.HORIZONTAL_BAR // DROPDOWN_MENU, HORIZONTAL_BAR, or DEFAULT
		},
		"scaleControl": true,
		"overviewMapControl": !IS_MOBILE,
		"panControl": !IS_MOBILE,
		"streetViewControl": false,
		"zoomControl": !IS_MOBILE
	},
	showMap: function() {
		this.mapContainer = $(".mapContainer").get(0);
		if (!this.mapContainer) {
			return;
		}
		this.mainMap = new google.maps.Map(this.mapContainer, this.mapOptions);
	},
	initMap: function() {
		var that = this;
		this.showMap();
		if (navigator.geolocation !== undefined) {
			var onsuccess = this.setLocationFromGPS.bind(this);
			var onerror   = this.setLocationFromCookiesOrDefaults.bind(this);
			navigator.geolocation.getCurrentPosition(onsuccess, onerror);
		} else {
			this.initializeFromCookiesOrDefaults();
		}
	},
	setLocationFromGPS: function(position) {
		if (this.initLocationZoom) {
			this.mainMap.setCenter(new google.maps.LatLng(position.coords.latitude,
														  position.coords.longitude));
			if (this.cookies.zoom !== undefined) {
				console.log("zoom from cookie " + this.cookies.zoom);
				this.mainMap.setZoom(Number(this.cookies.zoom));
			} else {
				console.log("default zoom for gps");
				this.mainMap.setZoom(DEFAULT_GPS_ZOOM);
			}
		}
	},
	setLocationFromCookiesOrDefaults: function() {
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
	setEvents: function() {
		google.maps.event.addListener(this.mainMap, "center_changed", this.updateCookies.bind(this));
		google.maps.event.addListener(this.mainMap, "zoom_changed", this.updateCookies.bind(this));
	},
	updateCookies: function() {
		this.initLocationZoom = false;
		var center  = this.mainMap.getCenter();
		var zoom    = this.mainMap.getZoom();
		setCookie("lat",  center.lat());
		setCookie("lng",  center.lng());
		setCookie("zoom", zoom);
	},
	fetchData: function() {
		console.log("this is fetchData.");
		setTimeout(this.fetchData.bind(this), 5000);
		$.ajax({
			url: "vehicle_data.mhtml?agencyid=1",
			dataType: "json",
			success: this.loadData.bind(this)
		});
	},
	loadData: function(data) {
		console.log("this is loadData");
		data.vehicle.forEach(this.placeVehicle.bind(this));
	},
	markers: {},
	vehicles: {},
	placeVehicle: function(vehicle) {
		var that = this;
		var label = vehicle.label;
		var latitude = vehicle.latitude;
		var longitude = vehicle.longitude;
		var route_id = vehicle.route_id;
		var timestamp = vehicle.timestamp;
		var trip_id = vehicle.trip_id;
		this.vehicles[label] = vehicle;
		var exclude = vehicle._exclude_;

		if (exclude) {
			if (this.markers[label]) {
				this.markers[label].setMap(null);
				delete this.markers[label];
			}
		} else {
			if (!this.markers[label]) {
				this.markers[label] = new google.maps.Marker({
					clickable: true,
					flat: true,
					map: this.mainMap,
					optimized: false, // eh?
					visible: true
				});
				google.maps.event.addListener(this.markers[label], 
											  "click", function() {
												  console.log("click event");
												  that.markerClick(that.vehicles[label]);
											  });
			}
			this.markers[label].setOptions({
				position: new google.maps.LatLng(latitude, longitude),
				icon: {
					url: this.getImageURL(vehicle),
					size: new google.maps.Size(15, 15),
					anchor: new google.maps.Point(7, 7)
				},
				title: this.markerTitle(vehicle)
			});
			if (this.infoWindowLabel === label && this.infoWindow) {
				this.infoWindow.setOptions({
					content: this.infoWindowContent(vehicle)
				});
			}
		}
	},
	infoWindowLabel: null,
	markerClick: function(vehicle) {
		if (this.infoWindow) {
			this.infoWindow.close();
			this.infoWindowLabel = null;
		}
		this.infoWindow = new google.maps.InfoWindow({
			content: this.infoWindowContent(vehicle)
		});
		var marker = this.markers[vehicle.label];
		this.infoWindow.open(this.mainMap, marker);
		this.infoWindowLabel = vehicle.label;
	},
	markerTitle: function(vehicle) {
		var content = "{short} to {headsign} [bus {label}]"
			.replace(/{short}/, vehicle.route_short_name)
			.replace(/{headsign}/, vehicle.trip_headsign)
			.replace(/{label}/, vehicle.label);
		return content;
	},
	tripDetailsURL: function(vehicle) {
		return "trip_details.mhtml?agencyid=1&tripid=%s".replace(/%s/, vehicle.trip_id);
	},
	tripDetailsDataURL: function(vehicle) {
		return "trip_details_data.mhtml?agencyid=1&tripid=%s".replace(/%s/, vehicle.trip_id);
	},
	infoWindowContent: function(vehicle) {
		var content = "";
		content += "<h1 style='margin-top: 0;'>" + vehicle.route_short_name + " to " + vehicle.trip_headsign + "</h1>\n";
		content += "<p>Bus " + vehicle.label + ".</p>";
		if (vehicle.next_stop && vehicle.next_stop.delay_minutes) {
			content += "<p>Approx. " + vehicle.next_stop.delay_minutes + " minutes late.</p>";
		} else {
			content += "<p>On time.</p>";
		}
		content += "<p><a href='%s' target='_blank'>Trip Details</a>\n".replace(/%s/, this.tripDetailsURL(vehicle));
		content += "   | <a href='%s' target='_blank'>Trip Data</a></p>\n".replace(/%s/, this.tripDetailsDataURL(vehicle));

		return content;
	},
	getImageURL: function(vehicle) {
		var imageURL = "http://webonastick.com/route-icons/target/route-icons/png/{colorScheme}/{route_id}.png";
		imageURL = imageURL.replace(/{colorScheme}/, this.getColorScheme(vehicle));
		imageURL = imageURL.replace(/{route_id}/, vehicle.route_id);
		return imageURL;
	},
	getColorScheme: function(vehicle) {
		console.log(vehicle);
		var express = /\bexpress\b/i.test(vehicle.trip_headsign);
		if (vehicle.route_id == "94" || vehicle.route_id == "95") {
			return "white-on-red";
		}
		if (/^13[56789]\d$/.test(vehicle.label)) {
			return express ? "blue-on-white" : "white-on-blue";
		}
		if (/^14\d\d$/.test(vehicle.label)) {
			return express ? "blue-on-white" : "white-on-blue";
		}
		return express ? "black-on-yellow" : "white-on-black";
	},
	showTransitLayer: function(bool) {
		if (bool === undefined || bool === null) { bool = true; }
		console.log(bool);
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
	showTrafficLayer: function(bool) {
		if (bool === undefined || bool === null) { bool = true; }
		console.log(bool);
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
	showBicyclingLayer: function(bool) {
		if (bool === undefined || bool === null) { bool = true; }
		console.log(bool);
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
	initLayerBindings: function() {
		var that = this;
		$(":checkbox[name='showTransitLayer']").change(function() {
			that.showTransitLayer(this.checked);
		}).trigger("change");
		$(":checkbox[name='showTrafficLayer']").change(function() {
			that.showTrafficLayer(this.checked);
		}).trigger("change");
		$(":checkbox[name='showBicyclingLayer']").change(function() {
			that.showBicyclingLayer(this.checked);
		}).trigger("change");
	}
});

