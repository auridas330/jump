<?php
/**
 * Proxy requests to OpenWeather API and cache response.
 *
 * @author Dale Davies <dale@daledavies.co.uk>
 * @license MIT
 */

// Provided by composer for psr-4 style autoloading.
require __DIR__ .'/../vendor/autoload.php';

$config = new Jump\Config();
$cache = new Jump\Cache($config);

// Output header here so we can return early with a json response if there is a curl error.
header('Content-Type: application/json; charset=utf-8');

// Initialise a new session using the request object.
$session = new \Nette\Http\Session((new \Nette\Http\RequestFactory)->fromGlobals(), new \Nette\Http\Response);
$session->setName($config->get('sessionname'));
$session->setExpiration($config->get('sessiontimeout'));

// Get a Nette session section for CSRF data.
$csrfsection = $session->getSection('csrf');

// Has a CSRF token been set up for the session yet?
if (!$csrfsection->offsetExists('token')){
    http_response_code(401);
    die(json_encode(['error' => 'Session not fully set up']));
}

// Check CSRF token saved in session against token provided via request.
$token = isset($_GET['token']) ? $_GET['token'] : false;
if (!$token || !hash_equals($csrfsection->get('token'), $token)) {
    http_response_code(401);
    die(json_encode(['error' => 'API token is incorrect or missing']));
}

// Start of variables  we want to use.
$owmapiurlbase = 'https://api.openweathermap.org/data/2.5/weather';
$units = $config->parse_bool($config->get('metrictemp')) ? 'metric' : 'imperial';

// If we have either lat or lon query params then cast them to a float, if not then
// set the values to zero.
$lat = isset($_GET['lat']) ? (float) $_GET['lat'] : 0;
$lon = isset($_GET['lon']) ? (float) $_GET['lon'] : 0;

// Use the lat and lon values provided unless they are zero, this might mean that
// either they werent provided as query params or they couldn't be cast to a float.
// If they are zero then use the default latlong from config.
$latlong = [$lat, $lon];
if ($lat === 0 || $lon === 0) {
    $latlong = explode(',', $config->get('latlong', false));
}

// This is the API endpoint and params we are using for the query,
$url =  $owmapiurlbase
        .'?units=' . $units
        .'&lat=' . $latlong[0]
        .'&lon=' . $latlong[1]
        .'&appid=' . $config->get('owmapikey', false);

// Use the cache to store/retrieve data, make an md5 hash of latlong so it is not possible
// to track location history form the stored cache.
$weatherdata = $cache->load(cachename: 'weatherdata', key: md5(json_encode($latlong)), callback: function() use ($url) {
    // Ask the API for some data.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $response = curl_exec($ch);

    // Just in case something went wrong with the request we'll capture the error.
    if (curl_errno($ch)) {
        $curlerror = curl_error($ch);
    }
    curl_close($ch);
    // If we had an error then return the error message and exit, otherwise return the API response.
    if (isset($curlerror)) {
        http_response_code(400);
        die(json_encode(['error' => $curlerror]));
    }
    return $response;
});

// We made it here so output the API response as json.
echo $weatherdata;
