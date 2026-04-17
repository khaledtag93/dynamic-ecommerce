<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$localSafeBootEnabled = filter_var($_ENV['LOCAL_SAFE_BOOT'] ?? $_SERVER['LOCAL_SAFE_BOOT'] ?? getenv('LOCAL_SAFE_BOOT') ?? false, FILTER_VALIDATE_BOOL);
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$traceFile = __DIR__.'/../storage/logs/local_boot_trace.log';

$trace = static function (string $message) use ($traceFile): void {
    try {
        $dir = dirname($traceFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($traceFile, '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL, FILE_APPEND);
    } catch (Throwable $e) {
        // Ignore trace failures.
    }
};

register_shutdown_function(static function () use ($trace): void {
    $error = error_get_last();
    if ($error) {
        $trace('shutdown_error: '.json_encode($error));
    } else {
        $trace('shutdown_ok');
    }
});

if ($localSafeBootEnabled && in_array($requestPath, ['/ping', '/_probe', '/api/ping'], true)) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "ok
";
    exit;
}

define('LARAVEL_START', microtime(true));
$trace('index_start path='.$requestPath);

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    $trace('maintenance_file_loaded');
    require $maintenance;
}

$trace('before_autoload');
require __DIR__.'/../vendor/autoload.php';
$trace('after_autoload');

$app = require_once __DIR__.'/../bootstrap/app.php';
$trace('after_bootstrap_app');

$kernel = $app->make(Kernel::class);
$trace('after_kernel_make');

$request = Request::capture();
$trace('after_request_capture');

$response = $kernel->handle($request);
$trace('after_kernel_handle');
$response->send();
$trace('after_response_send');

$kernel->terminate($request, $response);
$trace('after_kernel_terminate');
