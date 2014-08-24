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
Object.extend(WhereIsMyBus, {
	init: function() {
		this.showMap();
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
		"overviewMapControl": false,
		"panControl": false,
		"streetViewControl": false,
		"zoomControl": false
	},
	showMap: function() {
		this.mapContainer = $(".mapContainer").get(0);
		if (!this.mapContainer) {
			return;
		}
		this.mainMap = new google.maps.Map(this.mapContainer, this.mapOptions);
	},
});

