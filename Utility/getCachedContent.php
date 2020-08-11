<?php

require_once __DIR__ . '/../vendor/autoload.php';

function getCachedContent($url, $expiry) {
    $mc = new Memcache();
    $mc->connect('localhost', 11211) or die("i cannot has memcache sry");
    $vp = $mc->get($url);
    if ($vp) {
        return $vp;
    }
    $client = new GuzzleHttp\Client();
    $response = $client->request('GET', $url);
    $statusCode = $response->getStatusCode();
    $contentType = $response->getHeaderLine('Content-Type');
    $body = $response->getBody();
    if ($statusCode > 399) {
        return null;
    }
    if ($contentType !== 'application/json') {
        return null;
    }
    $o = json_decode($body);
    $mc->set($url, $o, 0, $expiry);
    return $o;
}
