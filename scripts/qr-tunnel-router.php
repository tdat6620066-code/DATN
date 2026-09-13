<?php

// This listener exposes only signed, read-only QR information pages.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    || ! is_string($path)
    || ! preg_match('~^/booking-qr/[0-9]+$~D', $path)
    || ! is_string($_GET['signature'] ?? null)
    || ! preg_match('/^[a-f0-9]{64}$/D', $_GET['signature'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Đường dẫn không hợp lệ. Vui lòng quét mã QR của đơn đặt sân.';
    return;
}

require dirname(__DIR__).'/public/index.php';
