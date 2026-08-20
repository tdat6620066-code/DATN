<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VNPay Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình thanh toán VNPay (môi trường sandbox).
    |
    */

    // Mã website / Terminal ID
    'tmn_code' => env('VNPAY_TMN_CODE'),

    // Secret key dùng để tạo và xác thực checksum
    'hash_secret' => env('VNPAY_HASH_SECRET'),

    // URL thanh toán môi trường TEST
    'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),

    // Version API
    'version' => '2.1.0',

    // Tiền tệ
    'currency' => 'VND',

    // Locale
    'locale' => 'vn',
];