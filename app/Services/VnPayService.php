<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class VnPayService
{
    /**
     * Tạo URL thanh toán VNPay cho booking.
     *
     * Thuật toán băm theo đúng chuẩn VNPay:
     * - Sắp xếp tham số bằng ksort
     * - Nối chuỗi bằng urlencode() (RFC1738, dấu cách -> '+')
     * - Băm HMAC-SHA512 (không in hoa)
     */
    public function createPaymentUrl(Booking $booking, string $returnUrl): string
    {
        $inputData = [
            'vnp_Version' => config('vnpay.version', '2.1.0'),
            'vnp_TmnCode' => config('vnpay.tmn_code'),
            'vnp_Amount' => $this->formatAmount($booking->total_amount),
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes(15)->format('YmdHis'),
            'vnp_CurrCode' => config('vnpay.currency', 'VND'),
            'vnp_IpAddr' => request()->ip(),
            'vnp_Locale' => config('vnpay.locale', 'vn'),
            'vnp_OrderInfo' => $this->buildOrderInfo($booking),
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $this->buildTxnRef($booking),
        ];

        ksort($inputData);

        [$query, $hashdata] = $this->buildQueryAndHashData($inputData);

        $secureHash = $this->hashData($hashdata);

        $url = config('vnpay.url') . '?' . $query . 'vnp_SecureHash=' . $secureHash;

        Log::info('VNPay payment URL created', [
            'booking_id' => $booking->id,
            'txn_ref' => $inputData['vnp_TxnRef'],
            'amount' => $inputData['vnp_Amount'],
            'hash' => $secureHash,
        ]);

        return $url;
    }

    /**
     * Xác thực checksum của response trả về (return URL hoặc IPN).
     */
    public function verifyResponse(array $data): bool
    {
        if (empty($data['vnp_SecureHash'])) {
            return false;
        }

        $receivedHash = $data['vnp_SecureHash'];
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);

        ksort($data);

        [, $hashdata] = $this->buildQueryAndHashData($data);

        return hash_equals($this->hashData($hashdata), $receivedHash);
    }

    /**
     * Tạo chuỗi query (có dấu & ở cuối) và chuỗi hashdata (không có & ở cuối).
     *
     * @return array{0: string, 1: string}
     */
    private function buildQueryAndHashData(array $data): array
    {
        $query = '';
        $hashdata = '';
        $i = 0;

        foreach ($data as $key => $value) {
            $encodedKey = urlencode((string) $key);
            $encodedValue = urlencode((string) $value);

            if ($i === 1) {
                $hashdata .= '&' . $encodedKey . '=' . $encodedValue;
            } else {
                $hashdata .= $encodedKey . '=' . $encodedValue;
                $i = 1;
            }

            $query .= $encodedKey . '=' . $encodedValue . '&';
        }

        return [$query, $hashdata];
    }

    /**
     * Băm theo chuẩn VNPay: HMAC-SHA512, kết quả hex thường.
     */
    private function hashData(string $data): string
    {
        return hash_hmac('sha512', $data, config('vnpay.hash_secret'));
    }

    /**
     * VNPay yêu cầu số tiền theo VND, nhân 100, không có phần thập phân.
     */
    private function formatAmount($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * Mã giao dịch đảm bảo duy nhất cho từng lần thanh toán.
     *
     * VNPay chỉ chấp nhận ký tự chữ và số cho vnp_TxnRef, nên không dùng
     * dấu gạch dưới hoặc các ký tự phân cách khác.
     */
    private function buildTxnRef(Booking $booking): string
    {
        return $booking->booking_code . now()->format('YmdHis');
    }

    private function buildOrderInfo(Booking $booking): string
    {
        return 'Thanh toan dat san ' . $booking->booking_code;
    }
}
