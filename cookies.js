/*jslint browser: true, vars: true, sloppy: true, regexp: true, white: true */
//-----------------------------------------------------------------------------
// Lines above are for jslint, the JavaScript verifier.  http://www.jslint.com/
//-----------------------------------------------------------------------------

// stolen from the rhino book 6th ed.
function getCookies() {
	var cookies, all, list, i, cookie, p, name, value;

	cookies = {}; // The object we will return
	all = document.cookie; // Get all cookies in one big string
	if (all === "") { // If the property is the empty string
		return cookies; // return an empty object
	}
	list = all.split("; "); // Split into individual name=value pairs
	for(i = 0; i < list.length; i += 1) { // For each cookie
		cookie = list[i];
		p = cookie.indexOf("="); // Find the first = sign
		name = cookie.substring(0,p); // Get cookie name
		value = cookie.substring(p+1); // Get cookie value
		value = decodeURIComponent(value); // Decode the value
		cookies[name] = value; // Store name and value in object
	}
	return cookies;
}
function setCookie(name, value, daysToLive) {
	var cookie;

	cookie = name + "=" + encodeURIComponent(value);
	if (typeof daysToLive === "number") {
		cookie += "; max-age=" + (daysToLive*60*60*24);
	}
	document.cookie = cookie;
}

