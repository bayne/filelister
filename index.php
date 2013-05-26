<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

$config = require __DIR__.'/config/config.php';

$app = new Silex\Application();
$app['debug'] = false;
$app->register(new \Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/view'
));
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new \Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/silex.log'
));
$app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) use ($config) {
    $monolog->pushHandler(new \Monolog\Handler\PushoverHandler(
        $config['pushover_api_key'],
        $config['pushover_user_key'],
        'seedbox',
        \Symfony\Bridge\Monolog\Logger::NOTICE
    ));
    return $monolog;
}));
$app->register(new \Geotools\Silex\GeotoolsServiceProvider());
$app['geocoder'] = $app->share(function() {
    $geocoder = new \Geocoder\Geocoder();
    $adapter  = new \Geocoder\HttpAdapter\CurlHttpAdapter();
    $geocoder->registerProviders(array(
        new \Geocoder\Provider\FreeGeoIpProvider($adapter),
        new \Geocoder\Provider\GeoipProvider($adapter)
    ));
    return $geocoder;
});

$app['basePath'] = __DIR__.'/data/';

$app->get('/', function() use ($app) {
   return $app->redirect($app['url_generator']->generate('list'));
});

$app->get('/list{path}', function(Request $request, $path) use ($app) {

    if ($path == '/') {
        $path = '';
    }
    $finder = new Finder();
    $directoryFinder = new Finder();
    $basePath = $app['basePath'];
    $files = $finder->files()->in($basePath.$path)->depth(0);
    $directories = $directoryFinder->directories()->in($basePath.$path)->depth(0);
    return $app['twig']->render('main.html.twig', array(
        'files' => $files,
        'directories' => $directories,
        'path' => $path
    ));
})->value('path', '')->assert('path','.+')->bind('list');

$app->get('/download{path}', function(Request $request, $path) use ($app) {
    $ipAddress = $request->getClientIp();
    $results = $app['geotools']->batch($app['geocoder'])->geocode($ipAddress)->parallel();
    $result = reset($results);
    $country = $result->getCountry();
    $region = $result->getRegion();
    $city = $result->getCity();
    $app['logger']->notice("Download - [$ipAddress|$country|$region|$city] - $path");
    if (file_exists($app['basePath'].$path)) {
        return $app->sendFile($app['basePath'].$path, 200,array(),'attachment');
    } else {
        return $app->abort(404, 'File not found');
    }
})->value('path', '')->assert('path','.+')->bind('download');

$app->run();
