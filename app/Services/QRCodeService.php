<?php

namespace App\Services;

use App\Models\Booking;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    /**
     * Generate QR code for booking
     */
    public function generateQRCode(Booking $booking)
    {
        // Build QR code data
        $qrData = $this->buildQRData($booking);

        // Generate QR code
        $qrCode = QrCode::size(300)
            ->format('png')
            ->generate($qrData);

        return $qrCode;
    }

    /**
     * Generate QR code and save to file
     */
    public function generateAndSaveQRCode(Booking $booking)
    {
        $qrData = $this->buildQRData($booking);
        
        $filename = 'qr_' . $booking->booking_code . '.png';
        $path = storage_path('app/public/qrcodes/' . $filename);

        // Create directory if not exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        QrCode::size(300)
            ->format('png')
            ->save($path);

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => asset('storage/qrcodes/' . $filename)
        ];
    }

    /**
     * Build QR code data string
     */
    private function buildQRData(Booking $booking)
    {
        $details = [];
        
        foreach ($booking->bookingDetails as $detail) {
            $details[] = [
                'court' => $detail->court->name,
                'date' => $detail->booking_date->format('d/m/Y'),
                'time' => $detail->timeSlot->name,
            ];
        }

        // Build JSON data
        $data = [
            'booking_code' => $booking->booking_code,
            'customer' => $booking->user->name,
            'customer_phone' => $booking->user->email,
            'amount' => $booking->total_amount,
            'details' => $details,
            'status' => $booking->status,
        ];

        return json_encode($data);
    }

    /**
     * Verify QR code data
     */
    public function verifyQRCode($qrData)
    {
        try {
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
