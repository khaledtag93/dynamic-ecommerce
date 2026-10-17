<?php

/**
 * CI-only independent Laravel HTTP process for the same-email signup race.
 * Never invoke against an environment other than the isolated MySQL test DB.
 */
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if ($argc !== 4 || !in_array($argv[3] ?? '', ['A', 'B'], true)) {
    fwrite(STDERR, "Only synthetic A/B CI registration workers are supported.\n");
    exit(2);
}
[$script, $email, $dir, $device] = $argv;
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (
    !$app->environment('testing')
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'dynamic_test'
) {
    fwrite(STDERR, "Refusing to run registration race outside isolated test database.\n");
    exit(2);
}

// Never send an external provider message, including during future feature changes.
config(['mail.default' => 'array', 'queue.default' => 'sync']);
$kernel = $app->make(HttpKernel::class);
$server = [
    'SERVER_NAME' => 'localhost',
    'HTTP_HOST' => 'localhost',
    'REMOTE_ADDR' => '192.0.2.42',
    'HTTPS' => 'off',
];

$initial = Request::create('/register', 'GET', [], [], [], $server);
$get = $kernel->handle($initial);
$formStatus = $get->getStatusCode();
$csrfToken = session()->token();
$guestSessionId = session()->getId();
$cookieName = config('session.cookie');
$cookieValue = null;
foreach ($get->headers->getCookies() as $cookie) {
    if ($cookie->getName() === $cookieName) {
        $cookieValue = $cookie->getValue();
    }
}
$kernel->terminate($initial, $get);
if ($formStatus !== 200 || !is_string($cookieValue) || $cookieValue === '') {
    echo json_encode(['registrationFormStatus' => $formStatus, 'cookiePresent' => (bool) $cookieValue]);
    exit(2);
}

$passedValidationBeforeInsert = false;
User::creating(function (User $user) use ($email, $dir, $device, &$passedValidationBeforeInsert): void {
    if ($user->email !== $email) {
        return;
    }
    $passedValidationBeforeInsert = true;
    file_put_contents("$dir/$device.ready", 'validated');
    $until = microtime(true) + 25;
    while (!is_file("$dir/release")) {
        if (microtime(true) > $until) {
            throw new RuntimeException('Concurrent signup barrier timed out.');
        }
        usleep(10000);
    }
});

$post = Request::create('/register', 'POST', [
    '_token' => $csrfToken,
    'name' => "Synthetic Race $device",
    'email' => $email,
    'password' => 'synthetic-password-123',
    'password_confirmation' => 'synthetic-password-123',
], [$cookieName => $cookieValue], [], $server);

$response = $kernel->handle($post);
$body = (string) $response->getContent();
$status = $response->getStatusCode();
$errors = session()->get('errors');
$errorKeys = $errors instanceof \Illuminate\Support\ViewErrorBag
    ? array_keys($errors->getBag('default')->messages())
    : [];
$kernel->terminate($post, $response);

echo json_encode([
    'registrationFormStatus' => $formStatus,
    'guestSessionId' => $guestSessionId,
    'passedValidationBeforeInsert' => $passedValidationBeforeInsert,
    'postStatus' => $status,
    'errorKeys' => $errorKeys,
    'leakedSqlDetail' => (bool) preg_match('/SQLSTATE|Duplicate entry|INSERT INTO users/i', $body),
], JSON_THROW_ON_ERROR);
