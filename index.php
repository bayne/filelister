<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

$app = new Silex\Application();
$app['debug'] = false;
$app->register(new \Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/view'
));
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new \Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/silex.log'
));
$app['basePath'] = __DIR__.'/data/';

$app->get('/', function() use ($app) {
   return $app->redirect($app['url_generator']->generate('list'));
});

$app->get('/list{path}', function(Request $request, $path) use ($app) {
    $app['logger']->info($request->getClientIp());

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
    if (file_exists($app['basePath'].$path)) {
        return $app->sendFile($app['basePath'].$path, 200,array(),'attachment');
    } else {
        return $app->abort(404, 'File not found');
    }
})->value('path', '')->assert('path','.+')->bind('download');

$app->run();
