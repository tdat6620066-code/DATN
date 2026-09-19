<?php

namespace App\Services;

use App\Models\Booking;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    /**
     * Generate QR code for booking.
     *
     * Dùng SVG để không phụ thuộc extension Imagick (không có trên máy chủ).
     */
    public function generateQRCode(Booking $booking)
    {
        // Build QR code data
        $qrData = $this->buildQRData($booking);

        // Generate QR code (SVG markup)
        return QrCode::size(300)
            ->margin(4)
            ->format('svg')
            ->generate($qrData);
    }

    /**
     * Generate QR code and save to file
     */
    public function generateAndSaveQRCode(Booking $booking)
    {
        $qrData = $this->buildQRData($booking);
        
        $filename = 'qr_' . $booking->booking_code . '.svg';
        $path = storage_path('app/public/qrcodes/' . $filename);

        // Create directory if not exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        QrCode::size(300)
            ->margin(4)
            ->format('svg')
            ->generate($qrData, $path);

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => asset('storage/qrcodes/' . $filename)
        ];
    }

    /**
     * Build QR code data string
     */
    public function buildQRData(Booking $booking): string
    {
        // A cloned generator can retain a RouteUrlGenerator bound to the
        // original request host. Build an isolated generator for stable QR URLs.
        $urls = new \Illuminate\Routing\UrlGenerator(app('router')->getRoutes(), request());
        $urls->setKeyResolver(fn () => config('app.key'));
        $baseUrl = rtrim(config('qr.base_url') ?: config('app.url'), '/');
        $urls->forceRootUrl($baseUrl);
        $urls->forceScheme(parse_url($baseUrl, PHP_URL_SCHEME));
        return $urls->signedRoute('bookings.qr.scan', ['booking' => $booking->id]);
    }

    /**
     * Verify QR code data
     */
    public function verifyQRCode($qrData)
    {
        try {
            if (filter_var($qrData, FILTER_VALIDATE_URL)) {
                $request = \Illuminate\Http\Request::create($qrData);
                $route = app('router')->getRoutes()->match($request);
                if ($route->getName() !== 'bookings.qr.scan' || ! \Illuminate\Support\Facades\URL::hasValidSignature($request)) {
                    return ['valid' => false, 'message' => 'Mã QR không hợp lệ.'];
                }
                $booking = Booking::find($route->parameter('booking'));
                return $booking
                    ? ['valid' => true, 'booking' => $booking, 'data' => ['booking_code' => $booking->booking_code]]
                    : ['valid' => false, 'message' => 'Không tìm thấy đơn đặt sân.'];
            }
            $data = json_decode($qrData, true);

            if (!isset($data['booking_code'])) {
                return [
                    'valid' => false,
                    'message' => 'QR code không hợp lệ'
                ];
            }

            $booking = Booking::where('booking_code', $data['booking_code'])
                ->where('status', 'CONFIRMED')
                ->first();

            if (!$booking) {
                return [
                    'valid' => false,
                    'message' => 'Booking không tìm thấy hoặc không hợp lệ'
                ];
            }

            return [
                'valid' => true,
                'booking' => $booking,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Lỗi xác minh QR code: ' . $e->getMessage()
            ];
        }
    }
}
