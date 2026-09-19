<?php

namespace Tests\Feature;

use App\Models\{Booking, User};
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_qr_is_stable_across_request_hosts_and_cannot_be_tampered_with(): void
    {
        config(['qr.base_url' => 'https://booking.example.test', 'app.url' => 'http://localhost']);
        $booking = Booking::create(['booking_code'=>'QR-PUBLIC', 'user_id'=>User::factory()->create()->id, 'status'=>'CONFIRMED', 'payment_status'=>'PAID', 'total_amount'=>120000]);
        route('home'); // Warm the shared route generator using the local origin.
        $service = app(QRCodeService::class);
        $url = $service->buildQRData($booking);
        $this->assertStringStartsWith('https://booking.example.test/booking-qr/', $url);
        $this->assertStringNotContainsString('booking.example.test', route('home'));
        $this->assertStringContainsString('<svg', (string) $service->generateQRCode($booking));
        $this->get($url)->assertOk()->assertSee('QR-PUBLIC');
        $this->assertSame($url, $service->buildQRData($booking));
        $this->assertTrue($service->verifyQRCode($url)['valid']);
        $this->get($url.'&unexpected=1')->assertForbidden();
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $booking->update(['status'=>'CANCELLED']);
        $this->get($url)->assertOk()->assertSee('Đã hủy');
        $this->assertSame($url, $service->buildQRData($booking));
    }
}
