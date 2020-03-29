<?php

require dirname(__DIR__) . '/core/vendor/autoload.php';

$container = new \App\Container();
$app = new \Slim\App($container);
//$app->add($container->jwt);
$app->add(new RKA\Middleware\IpAddress());

$app->any('/api[/{name:.+}]', function ($request, $response, $args) use ($container) {
    $container->request = $request;
    $container->response = $response;

    return (new \App\Controllers\Api($container))->process($request, $response, $args);
});

$app->any('/image/{id:\d+}/{size:\d+x\d+}[/{params}]', function ($request, $response, $args) use ($container) {
    $container->request = $request;
    $container->response = $response;

    return (new \App\Controllers\Image($container))->process($request, $response, $args);
});

$app->get('/', function ($request, $response, $args) use ($container) {
    return (new \App\Controllers\Home($container))->process($args);
});

try {
    $app->run();
} catch (Throwable $e) {
    exit($e->getMessage());
}