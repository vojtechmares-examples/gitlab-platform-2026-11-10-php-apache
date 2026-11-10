<?php

declare(strict_types=1);

use Examples\App;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$log = new Logger('app');
$log->pushHandler(new StreamHandler('php://stderr'));

try {
    $app = new App($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'local', $root . '/build.json');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $response = $app->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    $log->error($e->getMessage());
    $response = ['status' => 500, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"error":"internal error"}'];
}

http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) {
    header("$name: $value");
}
echo $response['body'];
