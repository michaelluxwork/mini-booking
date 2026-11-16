<?php

// Synthetic isolated reliability probe, invoked by race-check.py only.
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
[$script, $mode, $value, $barrier] = $argv + [null, null, null, ''];
if ($barrier !== '') {
    $deadline = microtime(true) + 15;
    file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "barrier timeout\n");
            exit(2);
        }
        usleep(1000);
    }
}
if ($mode === 'create') {
    $request = Request::create('/api/bookings', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    ], json_encode(['slot_id' => (int) $value, 'customer_name' => 'Synthetic probe', 'customer_email' => 'probe@example.test']));
} elseif ($mode === 'delete') {
    $request = Request::create('/api/bookings/'.(int) $value, 'DELETE', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
} else {
    $request = Request::create('/api/slots', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
}
$response = $kernel->handle($request);
$payload = json_decode($response->getContent(), true);
$output = ['status' => $response->getStatusCode()];
if ($mode === 'create' && isset($payload['id'])) $output['booking_id'] = $payload['id'];
if ($mode === 'list') {
    $slot = collect($payload['data'] ?? [])->firstWhere('id', (int) $value);
    $output['slot_status'] = $slot['status'] ?? null;
    $output['booking_id'] = $slot['booking_id'] ?? null;
}
echo json_encode($output);
$kernel->terminate($request, $response);
