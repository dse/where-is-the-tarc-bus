where-is-the-tarc-bus
=====================

Web-based thing that tells you where the TARC buses are.

Uses the following:

- https://github.com/dse/route-icons --- for creating .png
  route-number icons.

- https://github.com/dse/HTTP-Cache-Transparent --- My fork of
  HTTP::Cache::Transparent, used by Geo::GTFS2 for caching.

- https://github.com/dse/geo-gtfs-modules-2 --- Geo::GTFS2, for
  downloading and decoding GTFS-Realtime data.

# notes to self:

initially, as root:

    mkdir /var/www/.geo-gtfs2
    chown www-data.www-data /var/www/.geo-gtfs2
    chsh -s /bin/bash www-data

initially, as user postgres:

    createuser --interactive --pwprompt
    createdb -O geo-gtfs geo-gtfs

periodically, as user www-data:

    cd /www/webonastick.com/htdocs/t
    ~dse/bin/gtfs2 url ridetarc.org http://googletransit.ridetarc.org/feed/google_transit.zip
    ~dse/bin/gtfs2 realtime-url ridetarc.org http://googletransit.ridetarc.org/realtime/GTFS-RealTime/TrapezeRealTimeFeed.json

