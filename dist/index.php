<?php // -*- mode: web -*-
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black"> <!-- default, black, black-translucent,  -->
        <title>Where's The Bus?</title>
        <link rel="stylesheet" href="styles/main.css">
        <!--
        <script>
            (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
                key: "AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo",
                v: "weekly",
                // Use the 'v' parameter to indicate the version to use (weekly, beta, alpha, etc.).
                // Add other bootstrap parameters as needed, using camel case.
            });
        </script>
        -->
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDORq7-X81z4tMI8GnQrzBQKzHZVyvpBMo&amp;sensor=true"></script>
        <script>
            /* HACK HACK HACK */
            const module = {};
        </script>
        <script src="scripts/markerwithlabel.js"></script>
        <script>
            /* HACK HACK HACK */
            const MarkerWithLabel = (module.exports)(google.maps);
        </script>
        <script src="scripts/main.js"></script>
    </head>
    <body>
        <div class="map" id="map"></div>
        <div class="controls gm-style-mtc-bbw" id="controls">
            <button class="gm-style-mtc control control--transit" id="transit">Transit</button>
            <button class="gm-style-mtc control control--bike" id="bike">Bike</button>
            <button class="gm-style-mtc control control--traffic" id="traffic">Traffic</button>
        </div>
    </body>
</html>
