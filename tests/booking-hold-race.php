<?php

// Run with: php tests/booking-hold-race.php
// Uses a uniquely named temporary MySQL database; never migrates the application database.
use App\Models\{Court, CourtType, TimeSlot, User};
use App\Services\BookingService;
use Illuminate\Support\Facades\{Artisan, DB};
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'broadcasting.default' => 'null']);
$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? $argv[2] : 'smashzone_hold_test_'.bin2hex(random_bytes(6));
if (! preg_match('/^smashzone_hold_test_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid temporary database name.');
}
if ($worker) {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
    DB::purge('mysql');
    echo "READY\n";
    flush();
    try {
        app(BookingService::class)->createBooking((int) $argv[3], [[
            'court_id' => (int) $argv[4], 'time_slot_id' => (int) $argv[5], 'booking_date' => $argv[6],
        ]]);
        echo "CREATED\n";
    } catch (Exception $exception) {
        $errors = json_decode($exception->getMessage(), true);
        if (! is_array($errors) || ! str_contains($errors[0]['message'] ?? '', 'đang được giữ')) throw $exception;
        echo "CONFLICT\n";
    }
    exit;
}

$admin = DB::connection('mysql');
$admin->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$processes = [];
try {
    config(['database.connections.hold_race' => array_replace(config('database.connections.mysql'), ['database' => $database, 'url' => null]), 'database.default' => 'hold_race']);
    Artisan::call('migrate', ['--database' => 'hold_race', '--force' => true]);
    $users = User::factory()->count(2)->create();
    $type = CourtType::create(['name' => 'Race test', 'status' => 'ACTIVE']);
    $court = Court::create(['code' => 'RACE', 'name' => 'Race', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
    $slot = TimeSlot::create(['name' => '19-20', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
    $court->prices()->create(['time_slot_id' => $slot->id, 'price' => 150000, 'effective_from' => today(), 'status' => 'ACTIVE']);
    DB::beginTransaction();
    Court::whereKey($court->id)->lockForUpdate()->firstOrFail();
    foreach ($users as $user) {
        $process = new Process([PHP_BINARY, __FILE__, '--worker', $database, (string) $user->id, (string) $court->id, (string) $slot->id, today()->addDay()->toDateString()], dirname(__DIR__));
        $process->setTimeout(30);
        $process->start();
        $processes[] = $process;
    }
    $deadline = microtime(true) + 15;
    while (count(array_filter($processes, fn ($process) => str_contains($process->getOutput(), 'READY'))) < 2) {
        if (microtime(true) > $deadline) throw new RuntimeException('Workers failed to start.');
        usleep(20000);
    }
    // Both independent requests must block behind the same database row lock.
    usleep(300000);
    foreach ($processes as $process) {
        if (! $process->isRunning()) throw new RuntimeException('Request did not wait for the court lock: '.$process->getOutput().$process->getErrorOutput());
    }
    DB::commit();
    $output = '';
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) throw new RuntimeException($process->getErrorOutput().$process->getOutput());
        $output .= $process->getOutput();
    }
    if (substr_count($output, 'CREATED') !== 1 || substr_count($output, 'CONFLICT') !== 1 || App\Models\Booking::count() !== 1) {
        throw new RuntimeException('Expected exactly one reservation and one conflict: '.$output);
    }
    echo "PASS: two concurrent MySQL requests; one hold created, one conflict.\n";
} finally {
    foreach ($processes as $process) if ($process->isRunning()) $process->stop();
    if (DB::connection('hold_race')->transactionLevel() > 0) DB::connection('hold_race')->rollBack();
    DB::disconnect('hold_race');
    $admin->statement("DROP DATABASE `$database`");
}
